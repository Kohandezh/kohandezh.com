#!/usr/bin/env python3
"""Structured source extraction (MasterPrompt §16–§17).

Input : sources/original/<file>.pdf  (+ manifests/sources.json for doc_key/page offsets/front matter)
Output: sources/extracted/<doc_key>.blocks.jsonl   one JSON block per line, source order preserved
        sources/tables/<doc_key>/T####.json         structured tables
        images/extracted/<doc_key>/F####.png        figure assets (raster or rendered vector region)
        manifests/extraction.json                    per-document stats
Blocks get immutable IDs:  <DOC_KEY>::P<page:04d>::SEC<section>::B<global:04d>
Block types: heading | paragraph | list_item | table | figure | caption | footnote | equation | quote | reference | glossary_entry | front_matter | page_marker
"""
from __future__ import annotations
import argparse, hashlib, json, re, statistics, sys
from collections import Counter, defaultdict
from pathlib import Path
import pymupdf  # PyMuPDF

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

BULLET_RE = re.compile(r"^\s*([•▪◦–—\-\*•▪◦●○■□➢➤►]|\(?[a-zA-Z0-9]{1,3}[\.\)]|[ivxIVX]{1,5}[\.\)])\s+")
NUM_HEADING_RE = re.compile(r"^\s*((?:Appendix\s+)?[A-Z]?\d*(?:\.\d+)*[A-Z]?)\s*[\.:\-–—]?\s+(\S.*)$")
APPENDIX_RE = re.compile(r"^\s*(Appendix|APPENDIX|Annex)\s+([A-Z0-9]+)\b[\s:.\-–—]*(.*)$")
CAPTION_RE = re.compile(r"^\s*(Table|Figure|Fig\.|Box|Exhibit)\s+([A-Z]?\d+(?:[\.\-]\d+)*)\s*[\.:\-–—]?\s*(.*)$", re.I)
FOOTNOTE_RE = re.compile(r"^\s*(\d{1,3})\s+\S")
LINE_NUM_RE = re.compile(r"^\s*\d{1,4}\s*$")
HYPHEN_END = re.compile(r"(\w)-\s*$")
PAGE_NUM_RE = re.compile(r"^\s*(?:Page\s+)?[ivxlcdmIVXLCDM\d]{1,6}\s*$")

def sha(s: str) -> str:
    return hashlib.sha256(s.encode("utf-8")).hexdigest()

def norm_ws(s: str) -> str:
    s = s.replace("­", "").replace("ﬁ", "fi").replace("ﬂ", "fl").replace("ﬀ", "ff").replace("ﬃ", "ffi").replace("ﬄ", "ffl")
    s = re.sub(r"[ \t ]+", " ", s)
    return s.strip()

class Line:
    __slots__ = ("text", "size", "bold", "italic", "x0", "y0", "x1", "y1", "font", "page")
    def __init__(self, text, size, bold, italic, bbox, font, page):
        self.text, self.size, self.bold, self.italic, self.font, self.page = text, size, bold, italic, font, page
        self.x0, self.y0, self.x1, self.y1 = bbox

def page_lines(page: pymupdf.Page, exclude_rects: list) -> list[Line]:
    """Lines with dominant span style; excludes text inside table rects."""
    out = []
    d = page.get_text("dict", flags=pymupdf.TEXT_PRESERVE_LIGATURES | pymupdf.TEXT_PRESERVE_WHITESPACE)
    for b in d["blocks"]:
        if b.get("type") != 0:
            continue
        for ln in b["lines"]:
            spans = [s for s in ln["spans"] if s["text"].strip()]
            if not spans:
                continue
            bbox = pymupdf.Rect(ln["bbox"])
            if any(r.intersects(bbox) and (r & bbox).get_area() > 0.5 * bbox.get_area() for r in exclude_rects):
                continue
            text = norm_ws("".join(s["text"] for s in ln["spans"]))
            if not text:
                continue
            big = max(spans, key=lambda s: len(s["text"]))
            bold = any(("Bold" in s["font"] or "Black" in s["font"] or "Heavy" in s["font"] or (s["flags"] & 16)) for s in spans if len(s["text"].strip()) > 2)
            italic = all((s["flags"] & 2) or "Italic" in s["font"] or "Oblique" in s["font"] for s in spans if len(s["text"].strip()) > 2)
            out.append(Line(text, round(big["size"], 1), bold, italic, tuple(bbox), big["font"], page.number + 1))
    out.sort(key=lambda l: (round(l.y0 / 3), l.x0))
    return out

def detect_columns(lines: list[Line], page_w: float) -> int:
    xs = [l.x0 for l in lines if (l.x1 - l.x0) < 0.55 * page_w and len(l.text) > 25]
    if len(xs) < 12:
        return 1
    left = sum(1 for x in xs if x < page_w * 0.42)
    right = sum(1 for x in xs if x > page_w * 0.48)
    return 2 if left > 5 and right > 5 else 1

def order_two_columns(lines: list[Line], page_w: float) -> list[Line]:
    mid = page_w / 2
    full = [l for l in lines if (l.x1 - l.x0) > 0.6 * page_w]
    left = [l for l in lines if l not in full and l.x0 < mid]
    right = [l for l in lines if l not in full and l.x0 >= mid]
    # keep full-width lines (titles/abstract) at top by y, then left column, then right
    full.sort(key=lambda l: l.y0); left.sort(key=lambda l: l.y0); right.sort(key=lambda l: l.y0)
    return full + left + right

def find_repeating(pages_lines: dict[int, list[Line]], page_h: float, n_pages: int) -> set[str]:
    """Running headers/footers: identical short text near top/bottom on ≥ 30% of pages."""
    c = Counter()
    for pno, lines in pages_lines.items():
        seen = set()
        for l in lines:
            if (l.y0 < page_h * 0.08 or l.y1 > page_h * 0.92) and len(l.text) < 120:
                key = re.sub(r"\d+", "#", l.text)
                if key not in seen:
                    c[key] += 1; seen.add(key)
    return {k for k, v in c.items() if v >= max(3, 0.3 * n_pages)}

def extract_document(pdf_path: Path, doc_key: str, meta: dict, out_dir: Path, tables_dir: Path, figs_dir: Path) -> dict:
    doc = pymupdf.open(pdf_path)
    n = doc.page_count
    page_w, page_h = doc[0].rect.width, doc[0].rect.height
    front_end = int(meta.get("front_matter_end_page") or 0)
    body_start = int(meta.get("body_start_page") or 1)
    has_line_numbers = bool(meta.get("has_line_numbers"))

    # ---- pass 1: tables, figures, lines per page
    tables_by_page: dict[int, list] = defaultdict(list)
    figs_by_page: dict[int, list] = defaultdict(list)
    pages_lines: dict[int, list[Line]] = {}
    tables_dir.mkdir(parents=True, exist_ok=True); figs_dir.mkdir(parents=True, exist_ok=True)
    t_idx = f_idx = 0
    for pno in range(n):
        page = doc[pno]
        excl = []
        try:
            tf = page.find_tables(strategy="lines_strict")
            tabs = list(tf.tables)
            if not tabs:
                tabs = [t for t in page.find_tables(strategy="text").tables if t.row_count >= 3 and t.col_count >= 2]
        except Exception:
            tabs = []
        for t in tabs:
            try:
                data = t.extract()
            except Exception:
                continue
            rows = [[norm_ws(c or "") for c in r] for r in data]
            rows = [r for r in rows if any(r)]
            if len(rows) < 2 or max(len(r) for r in rows) < 2:
                continue
            nonempty_cells = sum(1 for r in rows for c in r if c)
            if nonempty_cells < 4:
                continue
            t_idx += 1
            tid = f"T{t_idx:04d}"
            rect = pymupdf.Rect(t.bbox)
            excl.append(rect)
            rec = {"table_id": f"{doc_key}::{tid}", "doc_key": doc_key, "page": pno + 1, "bbox": list(rect),
                   "header": rows[0], "rows": rows[1:], "n_rows": len(rows) - 1, "n_cols": max(len(r) for r in rows)}
            rec["sha256"] = sha(json.dumps(rows, ensure_ascii=False))
            (tables_dir / f"{tid}.json").write_text(json.dumps(rec, ensure_ascii=False, indent=1), encoding="utf-8")
            tables_by_page[pno + 1].append(rec)
        # raster images
        for img in page.get_images(full=True):
            xref = img[0]
            try:
                rects = page.get_image_rects(xref)
            except Exception:
                rects = []
            for r in rects:
                if r.width < 60 or r.height < 40 or r.get_area() < 0.01 * page.rect.get_area():
                    continue  # logos / decorations
                f_idx += 1
                fid = f"F{f_idx:04d}"
                try:
                    pix = pymupdf.Pixmap(doc, xref)
                    if pix.n - pix.alpha >= 4:
                        pix = pymupdf.Pixmap(pymupdf.csRGB, pix)
                    pth = figs_dir / f"{fid}.png"; pix.save(pth)
                    h = hashlib.sha256(pth.read_bytes()).hexdigest()
                except Exception:
                    pth, h = None, None
                figs_by_page[pno + 1].append({"figure_id": f"{doc_key}::{fid}", "doc_key": doc_key, "page": pno + 1, "bbox": list(r),
                                              "asset": str(pth.relative_to(ROOT)) if pth else None, "sha256": h, "kind": "raster"})
        # vector drawings clusters → rendered region
        try:
            drawings = page.get_drawings()
        except Exception:
            drawings = []
        if drawings:
            big = [pymupdf.Rect(d["rect"]) for d in drawings if d.get("rect")]
            big = [r for r in big if r.width > 30 and r.height > 20]
            if len(big) >= 8:
                union = big[0]
                for r in big[1:]:
                    union |= r
                covered = sum(1 for t in tables_by_page[pno + 1] if pymupdf.Rect(t["bbox"]).intersects(union))
                if not covered and union.width > 120 and union.height > 80 and union.get_area() < 0.9 * page.rect.get_area():
                    f_idx += 1
                    fid = f"F{f_idx:04d}"
                    pth = figs_dir / f"{fid}.png"
                    clip = union + (-6, -6, 6, 6)
                    page.get_pixmap(dpi=150, clip=clip & page.rect).save(pth)
                    figs_by_page[pno + 1].append({"figure_id": f"{doc_key}::{fid}", "doc_key": doc_key, "page": pno + 1, "bbox": list(union),
                                                  "asset": str(pth.relative_to(ROOT)), "sha256": hashlib.sha256(pth.read_bytes()).hexdigest(), "kind": "vector_render"})
                    excl.append(union)
        lines = page_lines(page, excl)
        if has_line_numbers:
            lines = [l for l in lines if not (LINE_NUM_RE.match(l.text) and (l.x0 < page_w * 0.12 or l.x1 > page_w * 0.9))]
        pages_lines[pno + 1] = lines

    # ---- typography stats
    sizes = [l.size for ls in pages_lines.values() for l in ls if len(l.text) > 30]
    body_size = statistics.median(sizes) if sizes else 10.0
    repeating = find_repeating(pages_lines, page_h, n)

    # ---- pass 2: classify & assemble blocks
    blocks = []
    gidx = 0
    sec_path = "0"           # current numeric section (e.g. "2.3"); "FM" for front matter; "APPA" for appendix A
    sec_title = ""
    cur = None               # accumulating paragraph
    def flush():
        nonlocal cur, gidx
        if cur and cur["text"].strip():
            gidx += 1
            cur["order_no"] = gidx
            cur["block_id"] = f"{doc_key}::P{cur['page_start']:04d}::SEC{cur['section_path']}::B{gidx:04d}"
            cur["sha256"] = sha(cur["text"])
            blocks.append(cur)
        cur = None
    def emit(btype, text, page, extra=None):
        nonlocal gidx
        flush()
        gidx += 1
        b = {"block_id": f"{doc_key}::P{page:04d}::SEC{sec_path}::B{gidx:04d}", "doc_key": doc_key, "page_start": page, "page_end": page,
             "section_path": sec_path, "section_title": sec_title, "block_type": btype, "order_no": gidx, "text": text, "sha256": sha(text)}
        if extra: b.update(extra)
        blocks.append(b)
        return b

    for pno in range(1, n + 1):
        lines = pages_lines[pno]
        cols = detect_columns(lines, page_w)
        if cols == 2:
            lines = order_two_columns(lines, page_w)
        in_front = pno < body_start
        # tables/figures anchored to this page are emitted when we hit their caption or at end of page
        pending_tabs = list(tables_by_page.get(pno, []))
        pending_figs = list(figs_by_page.get(pno, []))
        for l in lines:
            key = re.sub(r"\d+", "#", l.text)
            if key in repeating or PAGE_NUM_RE.match(l.text):
                continue
            if (l.y0 < page_h * 0.06 or l.y1 > page_h * 0.94) and len(l.text) < 80 and not l.bold and l.size <= body_size:
                # likely header/footer noise not caught by repetition
                if re.search(r"NIST|Draft|Page|\bAI\s+\d{3}-\d\b|https?://doi", l.text) and len(l.text) < 80:
                    continue
            if in_front and pno <= max(front_end, body_start - 1):
                if cur and cur["block_type"] == "front_matter":
                    cur["text"] += "\n" + l.text; cur["page_end"] = pno
                else:
                    flush(); cur = {"doc_key": doc_key, "page_start": pno, "page_end": pno, "section_path": "FM", "section_title": "Front Matter", "block_type": "front_matter", "text": l.text}
                continue
            # caption?
            m = CAPTION_RE.match(l.text)
            if m and (l.bold or l.size <= body_size + 0.5) and len(l.text) < 300:
                kind = m.group(1).lower()
                cap = emit("caption", l.text, pno, {"caption_kind": "table" if kind == "table" else "figure", "caption_number": m.group(2)})
                if kind == "table" and pending_tabs:
                    t = pending_tabs.pop(0)
                    emit("table", "\n".join(" | ".join(t["header"])) + "\n" + "\n".join(" | ".join(r) for r in t["rows"]), pno,
                         {"table_id": t["table_id"], "table_json": str((tables_dir / (t["table_id"].split("::")[1] + ".json")).relative_to(ROOT)), "caption": l.text})
                elif kind != "table" and pending_figs:
                    f = pending_figs.pop(0)
                    emit("figure", l.text, pno, {"figure_id": f["figure_id"], "asset": f["asset"], "asset_sha256": f["sha256"], "caption": l.text})
                continue
            # footnote? (small font, bottom third, starts with number)
            if l.size < body_size - 1.2 and l.y0 > page_h * 0.6 and FOOTNOTE_RE.match(l.text):
                emit("footnote", l.text, pno, {"footnote_number": FOOTNOTE_RE.match(l.text).group(1)})
                continue
            if cur and cur["block_type"] == "footnote" and l.size < body_size - 1.2 and l.y0 > page_h * 0.6:
                cur["text"] += " " + l.text; continue
            # heading?
            is_heading = False
            if len(l.text) < 160 and not l.text.endswith((".", ";", ",")) or re.match(r"^\s*\d+(\.\d+)+\s+[A-Z]", l.text):
                if l.size >= body_size + 1.5 or (l.bold and l.size >= body_size - 0.3 and len(l.text) < 120):
                    is_heading = True
                am = APPENDIX_RE.match(l.text)
                if am and (l.bold or l.size >= body_size + 1):
                    is_heading = True
                nm = re.match(r"^\s*(\d+(?:\.\d+){0,3})\.?\s+([A-Z][^.]{2,120})$", l.text)
                if nm and (l.bold or l.size >= body_size + 0.5):
                    is_heading = True
            if is_heading:
                flush()
                am = APPENDIX_RE.match(l.text)
                nm = re.match(r"^\s*(\d+(?:\.\d+){0,3})\.?\s+(.+)$", l.text)
                if am:
                    sec_path = f"APP{am.group(2)}"; sec_title = l.text; level = 1
                elif nm:
                    sec_path = nm.group(1); sec_title = nm.group(2).strip(); level = sec_path.count(".") + 1
                else:
                    level = 1 if l.size >= body_size + 4 else (2 if l.size >= body_size + 2 else 3)
                    sec_title = l.text
                    if level == 1 and not re.match(r"^\d", sec_path):
                        sec_path = re.sub(r"[^A-Za-z0-9]+", "", l.text)[:24].upper() or sec_path
                emit("heading", l.text, pno, {"level": level, "font_size": l.size})
                continue
            # list item?
            if BULLET_RE.match(l.text) and len(l.text) > 3:
                flush()
                cur = {"doc_key": doc_key, "page_start": pno, "page_end": pno, "section_path": sec_path, "section_title": sec_title, "block_type": "list_item",
                       "text": l.text, "indent": round(l.x0)}
                continue
            # paragraph continuation or new paragraph
            if cur and cur["block_type"] in ("paragraph", "list_item"):
                prev = cur["text"]
                gap_new_para = False
                if cur.get("_last_y") is not None and cur.get("_last_page") == pno and (l.y0 - cur["_last_y"]) > l.size * 1.9:
                    gap_new_para = True
                starts_new = (prev.endswith((".", "?", "!", ":", "”", '"', ")")) and l.text[:1].isupper() and (l.x0 - cur.get("_x0", l.x0)) > 8) or gap_new_para
                if starts_new:
                    flush()
                else:
                    if HYPHEN_END.search(prev) and l.text[:1].islower():
                        cur["text"] = HYPHEN_END.sub(r"\1", prev) + l.text
                    else:
                        cur["text"] = prev + " " + l.text
                    cur["page_end"] = pno; cur["_last_y"] = l.y1; cur["_last_page"] = pno
                    continue
            cur = {"doc_key": doc_key, "page_start": pno, "page_end": pno, "section_path": sec_path, "section_title": sec_title, "block_type": "paragraph",
                   "text": l.text, "_last_y": l.y1, "_last_page": pno, "_x0": l.x0}
        # leftover tables/figures without captions
        for t in pending_tabs:
            emit("table", "\n".join(" | ".join(t["header"])) + "\n" + "\n".join(" | ".join(r) for r in t["rows"]), pno,
                 {"table_id": t["table_id"], "table_json": str((tables_dir / (t["table_id"].split("::")[1] + ".json")).relative_to(ROOT)), "caption": ""})
        for f in pending_figs:
            emit("figure", "", pno, {"figure_id": f["figure_id"], "asset": f["asset"], "asset_sha256": f["sha256"], "caption": ""})
    flush()
    for b in blocks:
        for k in ("_last_y", "_last_page", "_x0"):
            b.pop(k, None)
    # references / glossary tagging by section title
    for b in blocks:
        st = (b.get("section_title") or "").lower()
        if b["block_type"] in ("paragraph", "list_item"):
            if "reference" in st or "bibliograph" in st:
                b["block_type"] = "reference"
            elif "glossary" in st or "terminolog" in st or "definitions" in st:
                b["block_type"] = "glossary_entry"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / f"{doc_key}.blocks.jsonl"
    with open(out_path, "w", encoding="utf-8") as f:
        for b in blocks:
            f.write(json.dumps(b, ensure_ascii=False) + "\n")
    stats = Counter(b["block_type"] for b in blocks)
    words = sum(len(b["text"].split()) for b in blocks if b["block_type"] not in ("front_matter", "page_marker"))
    return {"doc_key": doc_key, "pages": n, "blocks": len(blocks), "words_in_blocks": words, "body_size_pt": body_size,
            "tables": t_idx, "figures": f_idx, "types": dict(stats), "running_lines_removed": sorted(repeating)[:10], "output": str(out_path.relative_to(ROOT))}

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--doc", help="doc_key to extract (default: all)")
    ap.add_argument("--load-db", action="store_true", help="also load blocks into state/project.db")
    a = ap.parse_args()
    sources = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]
    results = {}
    for s in sources:
        if a.doc and s["doc_key"] != a.doc:
            continue
        pdf = ROOT / "sources" / "original" / s["filename"]
        r = extract_document(pdf, s["doc_key"], s, ROOT / "sources" / "extracted", ROOT / "sources" / "tables" / s["doc_key"], ROOT / "images" / "extracted" / s["doc_key"])
        results[s["doc_key"]] = r
        print(json.dumps(r, ensure_ascii=False))
        if a.load_db:
            con = db.connect()
            con.execute("DELETE FROM blocks WHERE doc_key=?", (s["doc_key"],))
            with open(ROOT / r["output"], encoding="utf-8") as f:
                for line in f:
                    b = json.loads(line)
                    con.execute("INSERT OR REPLACE INTO blocks(block_id,doc_key,page_start,page_end,section_path,section_number,block_type,order_no,text,sha256,meta_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)",
                                (b["block_id"], b["doc_key"], b["page_start"], b["page_end"], b.get("section_title"), b.get("section_path"), b["block_type"], b["order_no"], b["text"], b["sha256"],
                                 json.dumps({k: v for k, v in b.items() if k not in ("block_id", "doc_key", "page_start", "page_end", "section_title", "section_path", "block_type", "order_no", "text", "sha256")}, ensure_ascii=False)))
            con.execute("UPDATE documents SET status='EXTRACTED', updated_at=? WHERE doc_key=?", (db.now(), s["doc_key"]))
            db.event("document_extracted", r, con)
    mpath = ROOT / "manifests" / "extraction.json"
    prev = json.load(open(mpath, encoding="utf-8")) if mpath.exists() else {}
    prev.update(results)
    mpath.write_text(json.dumps(prev, ensure_ascii=False, indent=1), encoding="utf-8")

if __name__ == "__main__":
    main()
