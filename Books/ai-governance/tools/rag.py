#!/usr/bin/env python3
"""Phase 18 — RAG corpus (MasterPrompt §54, §115) + search index (§78). Deterministic, from master/book.json.
Outputs: knowledge/chunks.jsonl (RAG), html/search/index.json (lexical search), knowledge/citations.jsonl"""
from __future__ import annotations
import hashlib, json, re, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

GLOS_PATH = ROOT / "translation_memory" / "glossary.json"
FA_STOP = set("و در به از که را با این برای است آن یا هم بر تا می اما اگر نیز شود شده های ها یک هر چه بین باید کند کرد شد بود نه ای".split())

def paragraphs(text: str) -> list[str]:
    parts, cur = [], []
    for line in text.split("\n"):
        if line.strip() == "" and cur:
            parts.append("\n".join(cur)); cur = []
        else:
            cur.append(line)
    if cur: parts.append("\n".join(cur))
    return parts

def split_chunks(text: str, target=220, maxw=420) -> list[str]:
    """Group paragraphs into retrieval chunks of ~target words; tables/headings stay atomic; never merge across headings."""
    out, cur, cw = [], [], 0
    for p in paragraphs(text):
        w = len(p.split())
        if p.lstrip().startswith("#") and cur:
            out.append("\n\n".join(cur)); cur, cw = [], 0
        if cw + w > maxw and cur:
            out.append("\n\n".join(cur)); cur, cw = [], 0
        cur.append(p); cw += w
        if cw >= target:
            out.append("\n\n".join(cur)); cur, cw = [], 0
    if cur: out.append("\n\n".join(cur))
    return out

def keywords(text: str, glos: dict, lang: str) -> list[str]:
    hits = []
    low = text.lower()
    for t in glos.values():
        term = (t.get("preferred_fa") if lang == "fa" else t.get("english")) or ""
        if term and (term.lower() in low if lang == "en" else term in text):
            hits.append(term)
    if lang == "fa":
        freq = {}
        for w in re.findall(r"[؀-ۿ‌]{3,}", text):
            if w not in FA_STOP: freq[w] = freq.get(w, 0) + 1
        hits += [w for w, _ in sorted(freq.items(), key=lambda x: -x[1])[:8]]
    return list(dict.fromkeys(hits))[:25]

def main():
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    glos = json.load(open(GLOS_PATH, encoding="utf-8"))["terms"] if GLOS_PATH.exists() else {}
    con = db.connect()
    en_by_chunk = {r["chunk_id"]: r["en"] for r in con.execute("SELECT chunk_id, '' en FROM chunks")}  # placeholder; EN source pulled from chunk files below
    out = ROOT / "knowledge" / "chunks.jsonl"; out.parent.mkdir(exist_ok=True)
    idx = []; n = 0; cites = []
    with open(out, "w", encoding="utf-8") as f:
        for P in book["parts"]:
            for C in P["chapters"]:
                for S in C["sections"]:
                    # English source text for the unit (concatenated chunk sources) — for bilingual retrieval
                    en_src = ""
                    for cid in S.get("chunk_ids", []):
                        d, ch = cid.split("::")
                        p = ROOT / "work" / "chunks" / d / f"{ch}.json"
                        if p.exists():
                            en_src += json.load(open(p, encoding="utf-8"))["en_text"] + "\n\n"
                    pieces = split_chunks(S["fa_text"])
                    for i, piece in enumerate(pieces, 1):
                        n += 1
                        rec = {"chunk_id": f"{S['content_id']}#r{i:02d}", "content_id": S["content_id"], "structural_id": S["structural_id"], "language": "fa",
                               "origin": S["origin"], "label_fa": S.get("label_fa"), "fa_text": piece, "en_source_text": en_src.strip() if i == 1 else "",
                               "part": P["title_fa"], "part_id": P["part_id"], "chapter": C["title_fa"], "chapter_id": C["chapter_id"], "section": S["title_fa"], "section_id": S["section_id"],
                               "source_documents": S["source_documents"], "source_pages": S["source_pages"], "source_block_ids": S["source_blocks"][:200], "concept_ids": [],
                               "derived_from_source_ids": S["source_documents"] if S["origin"] != "source_translation" else [], "derived_from_content_ids": S.get("derived_from_content_ids", []),
                               "keywords_fa": keywords(piece, glos, "fa"), "keywords_en": keywords(en_src, glos, "en") if i == 1 else [], "canonical_url": S["canonical_url"],
                               "content_hash": f"sha256:{hashlib.sha256(piece.encode('utf-8')).hexdigest()}", "unit_content_hash": S["content_hash"]}
                        f.write(json.dumps(rec, ensure_ascii=False) + "\n")
                    idx.append({"id": S["content_id"], "structural_id": S["structural_id"], "url": S["canonical_url"], "title_fa": S["title_fa"], "title_en": S["title_en"], "part": P["title_fa"],
                                "chapter": C["title_fa"], "origin": S["origin"], "docs": S["source_documents"], "keywords_fa": keywords(S["fa_text"], glos, "fa"), "keywords_en": keywords(en_src, glos, "en"),
                                "acronyms": sorted(set(re.findall(r"\b[A-Z]{2,8}\b", en_src)))[:20], "snippet": " ".join(S["fa_text"].split()[:40])})
                    cites.append({"content_id": S["content_id"], "citation_id": S["structural_id"], "sources": S["source_documents"], "pages": S["source_pages"]})
    (ROOT / "html" / "search").mkdir(parents=True, exist_ok=True)
    (ROOT / "html" / "search" / "index.json").write_text(json.dumps({"edition": book["edition_id"], "count": len(idx), "entries": idx}, ensure_ascii=False), encoding="utf-8")
    with open(ROOT / "knowledge" / "citations.jsonl", "w", encoding="utf-8") as f:
        for c in cites: f.write(json.dumps(c, ensure_ascii=False) + "\n")
    db.event("rag_built", {"rag_chunks": n, "index_entries": len(idx)}, con)
    print(f"RAG corpus: {n} chunks; search index: {len(idx)} entries")

if __name__ == "__main__":
    main()
