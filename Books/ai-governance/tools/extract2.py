#!/usr/bin/env python3
"""Structured source extraction v2 (MasterPrompt §16–§17).

Engine: PyMuPDF + pymupdf-layout (ONNX page-layout model) via pymupdf4llm page chunks.
Each page yields typed boxes (page-header, page-footer, title, section-header, text, list-item,
table, picture, caption, footnote, formula, …) with char offsets into the page markdown.
Blocks are emitted per box in reading order with immutable IDs  <DOC>::P<page>::SEC<sec>::B<n>.

Outputs per document:
  sources/extracted/<doc_key>.blocks.jsonl   ordered blocks
  sources/extracted/<doc_key>.md             page-tagged markdown (debug / human review)
  sources/tables/<doc_key>/T####.json        structured tables (header, rows)
  images/extracted/<doc_key>/*.png           figure assets (+ OCR text kept in block meta)
  manifests/extraction.json                  per-document stats
"""
from __future__ import annotations
import argparse, hashlib, json, re, shutil, statistics, sys
from collections import Counter
from pathlib import Path
import pymupdf
try:
    import pymupdf.layout  # noqa: F401  (pymupdf-layout)
    LAYOUT = True
except Exception:
    LAYOUT = False
import pymupdf4llm

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

NUMSEC = re.compile(r"^\s*((?:\d+)(?:\.\d+){0,4})\.?\s+(\S.{1,150})$")
APPX = re.compile(r"^\s*(Appendix|APPENDIX|Annex|ANNEX)\s+([A-Z0-9]{1,3})\b[\s:.\-–—]*(.*)$")
RMF = re.compile(r"^\s*(GOVERN|MAP|MEASURE|MANAGE)\s+(\d+(?:\.\d+)?)\b[:\s\-–—]*(.*)$")
SSDF = re.compile(r"^\s*((?:PO|PS|PW|RV)\.\d+(?:\.\d+)?)\b[:\s\-–—]*(.*)$")
CAPTION = re.compile(r"^\s*(Table|Figure|Fig\.|Box|Exhibit)\s+([A-Z]?\d+(?:[\.\-]\d+)*)\s*[\.:\-–—]?\s*(.*)$", re.I)
IMG = re.compile(r"!\[[^\]]*\]\(([^)]+)\)")
PICTXT = re.compile(r"<!--\s*Start of picture text\s*-->(.*?)<!--\s*End of picture text\s*-->", re.S)
EMPH = re.compile(r"(\*\*|__|(?<![\w/])[_*](?=[\w“\"(\[])|(?<=[\w.,;:”\")\]?!])[_*](?![\w/]))")
HYPH = re.compile(r"(\w)-$")
ONLY_NUM = re.compile(r"^\s*\d{1,4}\s*$")
LEADNUM = re.compile(r"^\s*\d{1,3}\s+(?=[A-Za-z(“\"])")
FOOTNUM = re.compile(r"^\s*(\d{1,3})\s+\S")

def sha(s: str) -> str:
    return hashlib.sha256(s.encode("utf-8")).hexdigest()

def clean(s: str) -> str:
    s = s.replace("­", "").replace("ﬁ", "fi").replace("ﬂ", "fl").replace("ﬀ", "ff").replace("ﬃ", "ffi").replace("ﬄ", "ffl")
    s = re.sub(r"<br\s*/?>", " ", s)
    s = re.sub(r"</?(?:mark|sup|sub|u|s|span|b|i|em|strong|font)\b[^>]*>", "", s)   # layout-engine inline markup → plain text
    s = re.sub(r"^\s*#{1,6}\s+", "", s)
    s = EMPH.sub("", s)
    s = re.sub(r"[ \t ]+", " ", s)
    return s.strip()

def margin_line_numbers(page: pymupdf.Page) -> list[int]:
    """Ordered margin line numbers of a draft page, detected from word positions (pure integers hugging the left/right margin)."""
    W = page.rect.width
    cands = [(w[1], int(w[4])) for w in page.get_text("words") if w[4].isdigit() and len(w[4]) <= 3 and (w[2] < W * 0.12 or w[0] > W * 0.88)]
    cands.sort()
    nums, last = [], 0
    for _, n in cands:
        if n > last and n - last <= 6:
            nums.append(n); last = n
    return nums if len(nums) >= 5 else []

def strip_line_numbers(seg: str, queue: list[int]) -> str:
    """Remove the queued margin numbers from a text box, in order. Numbers not found (they landed in another box) are skipped with a small lookahead."""
    if not queue:
        return seg
    out, pos = [], 0
    while queue:
        hit = None
        for k in range(min(4, len(queue))):
            n = queue[k]
            m = re.compile(rf"(?<![\w.\-/%$\[\(])({n})(?![\w.\-/%\]\)])").search(seg, pos)
            if m:
                hit = (k, m); break
        if not hit:
            break
        k, m = hit
        del queue[:k + 1]
        out.append(seg[pos:m.start()]); pos = m.end()
        if pos < len(seg) and seg[pos] == " ":
            pos += 1
        elif out and out[-1].endswith(" "):
            out[-1] = out[-1][:-1]
    out.append(seg[pos:])
    return re.sub(r"[ \t]{2,}", " ", "".join(out))

def parse_table(seg: str) -> tuple[list[str], list[list[str]]]:
    rows = []
    for l in seg.splitlines():
        l = l.strip()
        if not l.startswith("|"):
            continue
        inner = l.strip("|")
        if set(inner.replace("|", "").strip()) <= set("-: "):
            continue  # separator row
        rows.append([clean(c) for c in inner.split("|")])
    if not rows:
        return [], []
    return rows[0], rows[1:]

def extract_document(pdf: Path, doc_key: str, meta: dict) -> dict:
    doc = pymupdf.open(pdf)
    n = doc.page_count
    body_start = int(meta.get("body_start_page") or 1)
    has_line_numbers = bool(meta.get("has_line_numbers"))
    figs_dir = ROOT / "images" / "extracted" / doc_key
    tabs_dir = ROOT / "sources" / "tables" / doc_key
    for d in (figs_dir, tabs_dir):
        if d.exists():
            shutil.rmtree(d)
        d.mkdir(parents=True, exist_ok=True)
    chunks = pymupdf4llm.to_markdown(doc, page_chunks=True, write_images=True, image_path=str(figs_dir), image_format="png", dpi=150,
                                      filename=doc_key, image_size_limit=0.03, show_progress=False)
    blocks: list[dict] = []
    gidx = t_idx = f_idx = 0
    sec_path, sec_title = "0", ""
    cur: dict | None = None
    md_debug = []
    cls_counter = Counter()

    def push(b):
        nonlocal gidx
        gidx += 1
        b["order_no"] = gidx
        b["block_id"] = f"{doc_key}::P{b['page_start']:04d}::SEC{b['section_path']}::B{gidx:04d}"
        b["sha256"] = sha(b["text"])
        blocks.append(b)

    def flush():
        nonlocal cur
        if cur and cur["text"].strip():
            cur["text"] = cur["text"].strip()
            push(cur)
        cur = None

    def new(btype, text, page, **extra):
        b = {"doc_key": doc_key, "page_start": page, "page_end": page, "section_path": sec_path, "section_title": sec_title,
             "block_type": btype, "text": text}
        b.update(extra)
        return b

    def set_section(head_text: str, level: int | None):
        nonlocal sec_path, sec_title
        am, nm, rm, sm = APPX.match(head_text), NUMSEC.match(head_text), RMF.match(head_text), SSDF.match(head_text)
        if am:
            sec_path, sec_title = f"APP{am.group(2)}", head_text; level = level or 1
        elif rm:
            sec_path, sec_title = f"{rm.group(1)}-{rm.group(2)}", head_text; level = level or 3
        elif sm:
            sec_path, sec_title = sm.group(1), head_text; level = level or 3
        elif nm and re.match(r"^\d", nm.group(1)):
            sec_path, sec_title = nm.group(1), nm.group(2).strip(); level = level or (nm.group(1).count(".") + 1)
        else:
            sec_title = head_text; level = level or 2
            if level <= 2:
                sec_path = re.sub(r"[^A-Za-z0-9]+", "", head_text)[:28].upper() or sec_path
        return level

    for ch in chunks:
        md = ch["metadata"]
        pno = md.get("page_number") or md.get("page") or (chunks.index(ch) + 1)
        text = ch["text"]
        if has_line_numbers:
            # strip per box so the 'pos' offsets in page_boxes stay valid: rebuild text and boxes together
            queue = margin_line_numbers(doc[pno - 1])
            boxes_in = sorted(ch.get("page_boxes") or [], key=lambda b: b.get("index", 0))
            new_text, new_boxes, cursor = [], [], 0
            for box in boxes_in:
                s, e = box.get("pos", (0, 0))
                seg = text[s:e]
                seg2 = strip_line_numbers(seg, queue) if box.get("class") not in ("table", "picture") else seg
                new_boxes.append({**box, "pos": (cursor, cursor + len(seg2))}); new_text.append(seg2); cursor += len(seg2)
            if queue:  # numbers skipped because reading order ≠ vertical order: remove them wherever they stand in text boxes
                segs = [new_text[i] for i in range(len(new_boxes))]
                for n in list(queue):
                    pat = re.compile(rf"(?<![\w.\-/%$\[\(])({n})(?![\w.\-/%\]\)])")
                    for i, box in enumerate(new_boxes):
                        if box.get("class") in ("table", "picture"):
                            continue
                        m = pat.search(segs[i])
                        if m:
                            segs[i] = re.sub(r"[ \t]{2,}", " ", segs[i][:m.start()] + segs[i][m.end():].lstrip(" ")); queue.remove(n); break
                cursor = 0; rebuilt = []
                for i, box in enumerate(new_boxes):
                    rebuilt.append({**box, "pos": (cursor, cursor + len(segs[i]))}); cursor += len(segs[i])
                new_boxes, new_text = rebuilt, segs
            text = "".join(new_text); ch = {**ch, "page_boxes": new_boxes, "text": text}
        md_debug.append(f"\n\n<!-- PAGE {pno} -->\n" + text)
        boxes = sorted(ch.get("page_boxes") or [], key=lambda b: b.get("index", 0))
        toc_levels = {clean(t[1]).lower(): int(t[0]) for t in (ch.get("toc_items") or []) if len(t) >= 2}
        in_front = pno < body_start
        if in_front:
            sec_path_saved = sec_path
            sec_path, sec_title = "FM", "Front Matter"
        for box in boxes:
            cls = box.get("class", "text")
            cls_counter[cls] += 1
            s, e = box.get("pos", (0, 0))
            seg = text[s:e]
            if cls in ("page-header", "page-footer"):
                continue
            if cls == "picture":
                flush()
                m = IMG.search(seg)
                ocr = PICTXT.search(seg)
                asset = None
                if m:
                    asset = Path(m.group(1))
                    if not asset.is_absolute():
                        asset = ROOT / asset
                f_idx += 1
                push(new("figure", "", pno, figure_id=f"{doc_key}::F{f_idx:04d}", front_matter=in_front,
                         asset=str(asset.relative_to(ROOT)) if asset and asset.exists() else (m.group(1) if m else None),
                         asset_sha256=hashlib.sha256(asset.read_bytes()).hexdigest() if asset and asset.exists() else None,
                         ocr_text=clean(ocr.group(1)) if ocr else "", caption=""))
                continue
            if cls == "table":
                flush()
                header, rows = parse_table(seg)
                if not header:
                    push(new("paragraph", clean(seg), pno, layout_class="table-unparsed", front_matter=in_front)); continue
                t_idx += 1
                tid = f"T{t_idx:04d}"
                rec = {"table_id": f"{doc_key}::{tid}", "doc_key": doc_key, "page": pno, "bbox": list(box.get("bbox", ())),
                       "header": header, "rows": rows, "n_rows": len(rows), "n_cols": max(len(header), max((len(r) for r in rows), default=0))}
                rec["sha256"] = sha(json.dumps([header] + rows, ensure_ascii=False))
                (tabs_dir / f"{tid}.json").write_text(json.dumps(rec, ensure_ascii=False, indent=1), encoding="utf-8")
                cap = ""
                for b in reversed(blocks[-3:]):
                    if b["block_type"] == "caption" and b.get("caption_kind") == "table" and b["page_start"] == pno:
                        cap = b["text"]; break
                ttext = " | ".join(header) + "\n" + "\n".join(" | ".join(r) for r in rows)
                push(new("table", ttext, pno, table_id=rec["table_id"], table_json=str((tabs_dir / f"{tid}.json").relative_to(ROOT)),
                         caption=cap, n_rows=len(rows), n_cols=rec["n_cols"], front_matter=in_front))
                continue
            if cls == "caption":
                flush()
                line = clean(seg)
                cm = CAPTION.match(line)
                kind = ("table" if cm and cm.group(1).lower() == "table" else "figure") if cm else "figure"
                push(new("caption", line, pno, caption_kind=kind, caption_number=cm.group(2) if cm else "", front_matter=in_front))
                for b in reversed(blocks[-4:-1]):
                    if b["block_type"] in ("figure", "table") and not b.get("caption") and b["page_start"] == pno:
                        b["caption"] = line; break
                continue
            if cls in ("section-header", "title"):
                flush()
                for raw in seg.splitlines():
                    head = clean(raw)
                    if not head or ONLY_NUM.match(head):
                        continue
                    hashes = len(raw) - len(raw.lstrip("#")) if raw.lstrip().startswith("#") else 0
                    level = toc_levels.get(head.lower()) or (1 if cls == "title" else None) or (hashes if hashes else None)
                    if in_front:
                        push(new("heading", head, pno, level=level or 2, front_matter=True)); continue
                    level = set_section(head, level)
                    push(new("heading", head, pno, level=level))
                continue
            if cls == "footnote":
                flush()
                for raw in seg.splitlines():
                    line = clean(raw)
                    if not line:
                        continue
                    fm = FOOTNUM.match(line)
                    if fm or not blocks or blocks[-1]["block_type"] != "footnote":
                        push(new("footnote", line, pno, footnote_number=fm.group(1) if fm else "", front_matter=in_front))
                    else:
                        blocks[-1]["text"] += " " + line; blocks[-1]["sha256"] = sha(blocks[-1]["text"])
                continue
            if cls == "list-item":
                flush()
                for raw in seg.splitlines():
                    line = clean(raw)
                    if not line:
                        continue
                    if has_line_numbers and ONLY_NUM.match(line):
                        continue
                    starts_item = bool(re.match(r"^\s*([-*•▪◦●■□➢➤►]|\(?\d{1,3}[\.\)]|\(?[a-z][\.\)]|[ivx]{1,5}[\.\)])\s+", line, re.I)) or raw.lstrip().startswith(("-", "*", "•"))
                    if starts_item or not blocks or blocks[-1]["block_type"] != "list_item" or blocks[-1]["page_start"] != pno:
                        push(new("list_item", line, pno, indent=len(raw) - len(raw.lstrip()), front_matter=in_front))
                    else:
                        prev = blocks[-1]["text"]
                        blocks[-1]["text"] = (HYPH.sub(r"\1", prev) + line) if HYPH.search(prev) and line[:1].islower() else prev + " " + line
                        blocks[-1]["sha256"] = sha(blocks[-1]["text"])
                continue
            if cls == "formula":
                flush()
                push(new("equation", clean(seg), pno, front_matter=in_front)); continue
            # text / code / other → paragraphs split on blank lines
            paras = [p for p in re.split(r"\n\s*\n", seg) if p.strip()]
            for p in paras:
                lines = [clean(l) for l in p.splitlines()]
                lines = [l for l in lines if l and not (has_line_numbers and re.fullmatch(r"[\d\s]+", l))]
                if not lines:
                    continue
                if has_line_numbers:
                    lines = [LEADNUM.sub("", l) for l in lines]
                para = " ".join(lines)
                para = re.sub(r"(\w)- (?=[a-z])", r"\1", para)  # de-hyphenate soft breaks
                cm = CAPTION.match(para)
                if cm and len(para) < 320:
                    flush()
                    kind = "table" if cm.group(1).lower() == "table" else "figure"
                    push(new("caption", para, pno, caption_kind=kind, caption_number=cm.group(2), front_matter=in_front))
                    for b in reversed(blocks[-4:-1]):
                        if b["block_type"] in ("figure", "table") and not b.get("caption") and b["page_start"] == pno:
                            b["caption"] = para; break
                    continue
                if cur and cur["block_type"] == "paragraph" and cur["page_end"] < pno and (para[:1].islower() or HYPH.search(cur["text"]) or not re.search(r"[.!?:”\"\)\]]\s*$", cur["text"])):
                    cur["text"] = (HYPH.sub(r"\1", cur["text"]) + para) if HYPH.search(cur["text"]) else cur["text"] + " " + para
                    cur["page_end"] = pno
                    flush(); continue
                flush()
                # continuation of an earlier paragraph interrupted by a figure/caption/table/page break
                if para[:1].islower() or para[:1] in ",;)":
                    for b in reversed(blocks[-6:]):
                        if b["block_type"] in ("paragraph", "list_item") and b["page_start"] >= pno - 1 and not re.search(r"[.!?:”\"\)\]]\s*$", b["text"]):
                            b["text"] = (HYPH.sub(r"\1", b["text"]) + para) if HYPH.search(b["text"]) else b["text"] + " " + para
                            b["page_end"] = pno; b["sha256"] = sha(b["text"]); para = None; break
                        if b["block_type"] in ("heading",):
                            break
                    if para is None:
                        continue
                cur = new("paragraph", para, pno, layout_class=cls, front_matter=in_front)
                if re.search(r"[.!?:”\"\)\]]\s*$", para):
                    flush()
        if in_front:
            sec_path = sec_path_saved
            sec_title = ""
    flush()
    for b in blocks:  # tag references / glossary by section title
        st = (b.get("section_title") or "").lower()
        if b["block_type"] in ("paragraph", "list_item"):
            if re.search(r"\breferences?\b|bibliograph", st):
                b["block_type"] = "reference"
            elif re.search(r"glossary|terminolog|\bdefinitions\b", st):
                b["block_type"] = "glossary_entry"
    out = ROOT / "sources" / "extracted" / f"{doc_key}.blocks.jsonl"
    with open(out, "w", encoding="utf-8") as f:
        for b in blocks:
            f.write(json.dumps(b, ensure_ascii=False) + "\n")
    (ROOT / "sources" / "extracted" / f"{doc_key}.md").write_text("".join(md_debug), encoding="utf-8")
    words = sum(len(b["text"].split()) for b in blocks if not b.get("front_matter"))
    stats = Counter(b["block_type"] for b in blocks)
    return {"doc_key": doc_key, "pages": n, "blocks": len(blocks), "words_in_body_blocks": words, "layout_engine": "pymupdf.layout" if LAYOUT else "pymupdf4llm-basic",
            "tables": t_idx, "figures": f_idx, "types": dict(stats), "layout_classes": dict(cls_counter), "body_start_page": body_start,
            "headings": stats.get("heading", 0), "output": str(out.relative_to(ROOT))}

def load_db(doc_key: str, path: Path):
    con = db.connect()
    con.execute("DELETE FROM blocks WHERE doc_key=?", (doc_key,))
    core = ("block_id", "doc_key", "page_start", "page_end", "section_title", "section_path", "block_type", "order_no", "text", "sha256")
    with open(path, encoding="utf-8") as f:
        for line in f:
            b = json.loads(line)
            con.execute("INSERT INTO blocks(block_id,doc_key,page_start,page_end,section_path,section_number,block_type,order_no,text,sha256,meta_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                        (b["block_id"], b["doc_key"], b["page_start"], b["page_end"], b.get("section_title"), b.get("section_path"), b["block_type"], b["order_no"], b["text"], b["sha256"],
                         json.dumps({k: v for k, v in b.items() if k not in core}, ensure_ascii=False)))
    con.execute("UPDATE documents SET status='EXTRACTED', updated_at=? WHERE doc_key=?", (db.now(), doc_key))

def main():
    ap = argparse.ArgumentParser(); ap.add_argument("--doc"); ap.add_argument("--load-db", action="store_true"); a = ap.parse_args()
    src = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]
    mpath = ROOT / "manifests" / "extraction.json"
    res = json.load(open(mpath, encoding="utf-8")) if mpath.exists() else {}
    for s in src:
        if a.doc and s["doc_key"] != a.doc:
            continue
        r = extract_document(ROOT / "sources" / "original" / s["filename"], s["doc_key"], s)
        r["words_pdftotext"] = s.get("words_pdftotext"); r["coverage"] = round(r["words_in_body_blocks"] / max(1, s.get("words_pdftotext", 1)), 3)
        res[s["doc_key"]] = r
        print(json.dumps({k: r[k] for k in ("doc_key", "pages", "blocks", "headings", "tables", "figures", "words_in_body_blocks", "words_pdftotext", "coverage", "types")}, ensure_ascii=False), flush=True)
        if a.load_db:
            load_db(s["doc_key"], ROOT / r["output"]); db.event("document_extracted", r)
        mpath.write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding="utf-8")

if __name__ == "__main__":
    main()
