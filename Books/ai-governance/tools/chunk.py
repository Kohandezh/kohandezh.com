#!/usr/bin/env python3
"""Semantic chunk generation (MasterPrompt §24). Deterministic; no model calls.

Reads  sources/extracted/<doc>.blocks.jsonl
Writes work/chunks/<doc>/<CHxxxx>.json  and  chunks rows in state/project.db
Chunk = consecutive blocks of one section; target 1,200–3,000 words (smaller for flagged material).
Never splits: caption↔table/figure, list intro↔items, term↔definition, footnotes↔their paragraph group.
"""
from __future__ import annotations
import argparse, hashlib, json, re, sys
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

CFG = yaml.safe_load(open(ROOT / "config" / "translation.yaml", encoding="utf-8"))["chunking"]
SRC = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]
PARTS = {d["doc_key"]: d.get("part_id") for d in SRC}
FRONT_KEEP = re.compile(r"abstract|executive summary|preface|summary|note to reader|how to use|introduction|key ?words", re.I)
FRONT_DROP = re.compile(r"contents|list of (tables|figures)|acknowledg|disclaimer|authority|trademark|patent|copyright|submitting comments|audience|keywords|reports on computer|national institute of standards and technology special publication", re.I)
MODAL = re.compile(r"\b(must|shall|should|may|required|recommended|prohibited)\b", re.I)
DEFN = re.compile(r"\b(is|are)\s+defined\s+as\b|\brefers?\s+to\b|\bmeans\b|\bdefinition\b", re.I)
LEGAL = re.compile(r"\b(court|judicial|legal|law|statute|liability|jurisdiction|litigation|regulat|due process|plaintiff|defendant)\b", re.I)
SEC = re.compile(r"\b(attack|adversar|poison|evasion|exfiltrat|inversion|extraction|backdoor|jailbreak|prompt injection|threat|vulnerab|exploit|malicious)\b", re.I)
MATH = re.compile(r"[∑∫≤≥≈∈∀∃αβγδεθλμσ]|\\frac|\b(equation|probability|theorem|lemma|variance|expectation)\b", re.I)

def sha(s: str) -> str:
    return hashlib.sha256(s.encode("utf-8")).hexdigest()

def render_block(b: dict) -> str:
    t = b["block_type"]; x = b["text"]
    if t == "heading":
        return "#" * min(6, max(1, int(b.get("level") or 2))) + " " + x
    if t == "list_item":
        return "- " + re.sub(r"^\s*([-*•▪◦●■□➢➤►])\s+", "", x)
    if t == "table":
        h, *rows = x.split("\n")
        cols = h.split(" | ")
        out = ["| " + " | ".join(cols) + " |", "|" + "---|" * len(cols)] + ["| " + r.replace(" | ", " | ") + " |" for r in rows]
        return ("**" + b["caption"] + "**\n" if b.get("caption") else "") + "\n".join(out)
    if t == "figure":
        return f"[FIGURE {b.get('figure_id','')}] " + (b.get("caption") or "")
    if t == "caption":
        return "*" + x + "*"
    if t == "footnote":
        return f"[^{b.get('footnote_number','')}]: " + re.sub(r"^\s*\d{1,3}\s+", "", x)
    if t == "equation":
        return "$$ " + x + " $$"
    return x

def words(b: dict) -> int:
    return len(b["text"].split())

def flags_for(doc_key: str, blocks: list[dict]) -> list[str]:
    text = " ".join(b["text"] for b in blocks)
    w = max(1, len(text.split()))
    f = set()
    if PARTS.get(doc_key) == "P06" or len(LEGAL.findall(text)) / w > 0.006: f.add("legal")
    if doc_key in ("NIST-AI-100-2E2025", "NIST-AI-800-1-IPD", "NIST-SP-800-218A") or len(SEC.findall(text)) / w > 0.008: f.add("security_taxonomy")
    if any(b["block_type"] == "glossary_entry" for b in blocks) or len(DEFN.findall(text)) / w > 0.004: f.add("definitions")
    if len(MODAL.findall(text)) / w > 0.012: f.add("normative")
    if MATH.search(text) or any(b["block_type"] == "equation" for b in blocks): f.add("math")
    if any(b["block_type"] == "table" and (b.get("n_cols", 0) >= 4 or b.get("n_rows", 0) >= 15) for b in blocks): f.add("complex_table")
    if any(b["block_type"] == "table" for b in blocks): f.add("table")
    return sorted(f)

def target_words(flags: list[str]) -> int:
    t = CFG["target_words"]["default"]
    for fl in flags:
        t = min(t, CFG["reduced_words_for_flags"].get(fl, t))
    return t

def group_atomic(blocks: list[dict]) -> list[list[dict]]:
    """Group blocks into atomic units that must never be split."""
    units, i = [], 0
    while i < len(blocks):
        b = blocks[i]; unit = [b]; j = i + 1
        if b["block_type"] == "caption":
            # caption + following table/figure
            while j < len(blocks) and blocks[j]["block_type"] in ("table", "figure"):
                unit.append(blocks[j]); j += 1
        elif b["block_type"] in ("table", "figure"):
            while j < len(blocks) and blocks[j]["block_type"] == "caption":
                unit.append(blocks[j]); j += 1
        elif b["block_type"] in ("paragraph", "glossary_entry") and b["text"].rstrip().endswith(":"):
            # intro line + its list
            while j < len(blocks) and blocks[j]["block_type"] == "list_item":
                unit.append(blocks[j]); j += 1
        elif b["block_type"] == "list_item":
            while j < len(blocks) and blocks[j]["block_type"] == "list_item":
                unit.append(blocks[j]); j += 1
        elif b["block_type"] == "heading":
            # keep heading with first following content unit
            if j < len(blocks) and blocks[j]["block_type"] != "heading":
                nxt = group_atomic(blocks[j:j + 12])[0]
                unit += nxt; j += len(nxt)
        # attach footnotes that immediately follow
        while j < len(blocks) and blocks[j]["block_type"] == "footnote":
            unit.append(blocks[j]); j += 1
        units.append(unit); i = j
    return units

def chunk_document(doc_key: str) -> list[dict]:
    path = ROOT / "sources" / "extracted" / f"{doc_key}.blocks.jsonl"
    blocks = [json.loads(l) for l in open(path, encoding="utf-8")]
    # front matter: keep abstract/summary-type sections only
    keep = []
    for b in blocks:
        if b.get("front_matter"):
            st = b.get("section_title") or ""
            if b["block_type"] in ("heading",) and FRONT_KEEP.search(b["text"]) and not FRONT_DROP.search(b["text"]):
                keep.append(b); continue
            if FRONT_KEEP.search(st) and not FRONT_DROP.search(st) and b["block_type"] in ("paragraph", "list_item"):
                keep.append(b); continue
            continue
        if b["block_type"] in ("figure",) and not b.get("caption") and not b.get("ocr_text"):
            continue  # decorative/uncaptioned image; asset still recorded in blocks file
        keep.append(b)
    # split by section_path runs
    sections, run = [], []
    for b in keep:
        if run and b["section_path"] != run[-1]["section_path"] and b["block_type"] == "heading":
            sections.append(run); run = []
        run.append(b)
    if run: sections.append(run)
    chunks, n = [], 0
    heading_path: list[str] = []
    for sec in sections:
        heads = [b for b in sec if b["block_type"] == "heading"]
        if heads:
            lvl = int(heads[0].get("level") or 2)
            heading_path = heading_path[:max(0, lvl - 1)] + [heads[0]["text"]]
        flags = flags_for(doc_key, sec)
        tgt = target_words(flags); maxw = int(tgt * 1.6)
        units = group_atomic(sec)
        cur, cw = [], 0
        def emit(units_list):
            nonlocal n
            bl = [b for u in units_list for b in u]
            if not bl or all(b["block_type"] in ("heading",) for b in bl) and len(bl) == 1 and chunks:
                # lone heading: prepend to next chunk instead
                return bl
            n += 1
            cid = f"{doc_key}::CH{n:04d}"
            en = "\n\n".join(render_block(b) for b in bl)
            wc = sum(words(b) for b in bl)
            chunks.append({"chunk_id": cid, "doc_key": doc_key, "part_id": PARTS.get(doc_key), "section_path": bl[0]["section_path"], "heading": bl[0].get("section_title") or "",
                           "heading_path": list(heading_path), "block_ids": [b["block_id"] for b in bl], "block_types": [b["block_type"] for b in bl],
                           "pages": sorted({p for b in bl for p in range(b["page_start"], b["page_end"] + 1)}), "word_count": wc, "flags": flags_for(doc_key, bl),
                           "en_text": en, "sha256": sha(en), "order_no": n})
            return []
        carry = []
        for u in units:
            uw = sum(words(b) for b in u)
            if cw + uw > maxw and cur and cw >= tgt * 0.5:
                carry = emit(cur); cur, cw = list(carry), sum(words(b) for b in carry)
            cur.append(u); cw += uw
            if cw >= tgt:
                carry = emit(cur); cur, cw = [[b] for b in carry], sum(words(b) for b in carry)
        if cur:
            carry = emit(cur)
            if carry:  # lone heading at end of section → attach as its own tiny chunk
                n += 1
                en = "\n\n".join(render_block(b) for b in carry)
                chunks.append({"chunk_id": f"{doc_key}::CH{n:04d}", "doc_key": doc_key, "part_id": PARTS.get(doc_key), "section_path": carry[0]["section_path"], "heading": carry[0]["text"],
                               "heading_path": list(heading_path), "block_ids": [b["block_id"] for b in carry], "block_types": [b["block_type"] for b in carry],
                               "pages": [carry[0]["page_start"]], "word_count": sum(words(b) for b in carry), "flags": [], "en_text": en, "sha256": sha(en), "order_no": n})
    # merge tiny chunks (<25 words: lone headings, orphan captions/footnotes) into neighbors, then renumber
    merged: list[dict] = []
    for c in chunks:
        if c["word_count"] < 25 and merged:
            p = merged[-1]
            p["block_ids"] += c["block_ids"]; p["block_types"] += c["block_types"]
            p["en_text"] += "\n\n" + c["en_text"]; p["word_count"] += c["word_count"]
            p["pages"] = sorted(set(p["pages"]) | set(c["pages"])); p["flags"] = sorted(set(p["flags"]) | set(c["flags"]))
            p["sha256"] = sha(p["en_text"])
            continue
        merged.append(c)
    if len(merged) > 1 and merged[0]["word_count"] < 25:
        a, b = merged.pop(0), merged[0]
        b["block_ids"] = a["block_ids"] + b["block_ids"]; b["block_types"] = a["block_types"] + b["block_types"]
        b["en_text"] = a["en_text"] + "\n\n" + b["en_text"]; b["word_count"] += a["word_count"]
        b["pages"] = sorted(set(a["pages"]) | set(b["pages"])); b["flags"] = sorted(set(a["flags"]) | set(b["flags"]))
        b["sha256"] = sha(b["en_text"]); b["heading_path"] = a.get("heading_path") or b.get("heading_path")
        b["heading"] = a.get("heading") or b.get("heading")
    for i, c in enumerate(merged, 1):
        c["chunk_id"] = f"{doc_key}::CH{i:04d}"; c["order_no"] = i
    chunks = merged
    # adjacent context
    for i, c in enumerate(chunks):
        c["context_before"] = " ".join(chunks[i - 1]["en_text"].split()[-120:]) if i > 0 else ""
        c["context_after"] = " ".join(chunks[i + 1]["en_text"].split()[:120]) if i + 1 < len(chunks) else ""
    return chunks

def main():
    ap = argparse.ArgumentParser(); ap.add_argument("--doc"); a = ap.parse_args()
    con = db.connect()
    total = 0
    for d in SRC:
        k = d["doc_key"]
        if a.doc and k != a.doc: continue
        out = ROOT / "work" / "chunks" / k; out.mkdir(parents=True, exist_ok=True)
        for f in out.glob("*.json"): f.unlink()
        chunks = chunk_document(k)
        con.execute("DELETE FROM chunks WHERE doc_key=? AND status IN ('PENDING','QUEUED')", (k,))
        for c in chunks:
            (out / (c["chunk_id"].split("::")[1] + ".json")).write_text(json.dumps(c, ensure_ascii=False, indent=1), encoding="utf-8")
            con.execute("INSERT OR IGNORE INTO chunks(chunk_id,doc_key,section_path,heading,block_ids,word_count,sha256,flags,priority,order_no,status,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                        (c["chunk_id"], k, c["section_path"], c["heading"], json.dumps(c["block_ids"]), c["word_count"], c["sha256"], json.dumps(c["flags"]), 5, c["order_no"], "PENDING", db.now()))
        wc = [c["word_count"] for c in chunks]
        print(f"{k:32s} chunks={len(chunks):4d} words={sum(wc):6d} min={min(wc) if wc else 0:5d} median={sorted(wc)[len(wc)//2] if wc else 0:5d} max={max(wc) if wc else 0:5d}")
        total += len(chunks)
    db.event("chunks_generated", {"total": total}, con)
    print("TOTAL_CHUNKS", total)

if __name__ == "__main__":
    main()
