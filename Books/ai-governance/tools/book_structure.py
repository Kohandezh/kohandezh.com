#!/usr/bin/env python3
"""Phase 7 — book information architecture (MasterPrompt §31–§32). Deterministic.

Generates taxonomy/book_structure.yaml:
  parts (from config/publishing.yaml) → chapters → sections
  - source chapters: one per source document, or one per top-level source section for long documents (split_docs)
  - editorial chapters per part: راهنمای مطالعه (intro), جمع‌بندی تطبیقی (synthesis), برای مدیران (executive), لایه اجرایی ایران (localization)
  - sections of a source chapter = top-level headings (level ≤ 2) found in the extracted blocks, in source order
Persian titles are filled at master-build time from approved translations (heading blocks), never invented here.
"""
from __future__ import annotations
import json, re, sys
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

PUB = yaml.safe_load(open(ROOT / "config" / "publishing.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
SPLIT_DOCS = {"NIST-AI-RMF-PLAYBOOK": 1, "NIST-AI-100-2E2025": 1, "NIST-AI-100-4-IPD": 1, "NIST-SP-1270": 1}   # split into chapters at heading level 1
DROP_SECTIONS = re.compile(r"^(table of contents|contents|list of (tables|figures)|acknowledg\w*|disclaimer|trademark.*|patent|authority|submitting comments|note to reviewers|reports on computer systems technology|abstract|keywords|key words|audience|background|how to read.*|author contributions|author orcid.*|forward.*|foreword.*|publication history|additional information|nist technical series policies|predictive ai and generative ai taxonomy index|nist trustworthy and responsible ai)$", re.I)

def slugify(s: str) -> str:
    s = re.sub(r"[^A-Za-z0-9]+", "-", s).strip("-").lower()
    return s[:60] or "section"

def doc_outline(doc_key: str) -> list[dict]:
    p = ROOT / "sources" / "extracted" / f"{doc_key}.blocks.jsonl"
    if not p.exists():
        return []
    heads = []
    for line in open(p, encoding="utf-8"):
        b = json.loads(line)
        if b["block_type"] != "heading" or b.get("front_matter"):
            continue
        lvl = int(b.get("level") or 2)
        txt = b["text"].strip()
        if lvl <= 2 and not DROP_SECTIONS.match(txt) and not re.match(r"^[a-z\[]", txt):
            heads.append({"level": lvl, "title_en": b["text"], "section_path": b["section_path"], "page": b["page_start"], "block_id": b["block_id"]})
    # de-duplicate consecutive repeats (running titles that slipped through)
    out = []
    for h in heads:
        if out and out[-1]["title_en"].lower() == h["title_en"].lower():
            continue
        out.append(h)
    return out

def main():
    structure = {"edition_id": PUB["edition_id"], "book_title_fa": PUB["book_title_fa"], "book_title_en": PUB["book_title_en"], "generated": db.now(), "parts": []}
    cno = 0
    for p in PUB["parts"]:
        part = {"id": p["id"], "slug": p["slug"], "title_fa": p["title_fa"], "title_en": "", "sources": p["sources"], "chapters": []}
        cno += 1
        part["chapters"].append({"id": f"C{cno:02d}", "slug": f"{p['slug']}-reading-guide", "origin": "editorial_synthesis", "kind": "reading_guide", "label_fa": "راهنمای مطالعه", "title_fa": "", "title_en": f"Reading guide — {p['slug']}", "sections": []})
        for dk in p["sources"]:
            d = SRC.get(dk, {})
            outline = doc_outline(dk)
            if dk in SPLIT_DOCS and outline:
                # dynamic split level: docs whose front matter is level-1 have content chapters at level 2
                n_l1 = sum(1 for h in outline if h["level"] == 1)
                split_lvl = 1 if n_l1 >= 4 else 2
                topch = re.compile(r"^\d{1,2}\.\s+\S")  # "1. Introduction" — not "2.1. …" subsections
                cur = None
                for h in outline:
                    is_chapter = h["level"] == split_lvl and (split_lvl == 1 or topch.match(h["title_en"].strip()))
                    if is_chapter or cur is None:
                        cno += 1
                        cur = {"id": f"C{cno:02d}", "slug": f"{slugify(d.get('series_identifier', dk))}-{slugify(h['title_en'])}", "origin": "source_translation", "kind": "source",
                               "source_doc": dk, "title_fa": "", "title_en": h["title_en"], "source_section_root": h["section_path"], "sections": []}
                        part["chapters"].append(cur)
                        if is_chapter:
                            continue
                    cur["sections"].append({"title_en": h["title_en"], "section_path": h["section_path"], "page": h["page"], "block_id": h["block_id"]})
            else:
                cno += 1
                ch = {"id": f"C{cno:02d}", "slug": slugify(d.get("series_identifier", dk) + "-" + (d.get("title") or dk)[:50]), "origin": "source_translation", "kind": "source",
                      "source_doc": dk, "title_fa": "", "title_en": d.get("title", dk), "sections": [{"title_en": h["title_en"], "section_path": h["section_path"], "page": h["page"], "block_id": h["block_id"]} for h in outline]}
                part["chapters"].append(ch)
        for kind, label, slug in (("comparative_synthesis", "جمع‌بندی تطبیقی", "synthesis"), ("executive", "برای مدیران", "for-executives"), ("localization_ir", "کاربرد در سازمان‌های ایرانی", "iran-implementation")):
            cno += 1
            part["chapters"].append({"id": f"C{cno:02d}", "slug": f"{p['slug']}-{slug}", "origin": "editorial_localization_ir" if kind == "localization_ir" else "editorial_synthesis", "kind": kind, "label_fa": label, "title_fa": "", "title_en": f"{label} — {p['slug']}", "sections": []})
        structure["parts"].append(part)
    (ROOT / "taxonomy" / "book_structure.yaml").write_text(yaml.safe_dump(structure, allow_unicode=True, sort_keys=False, width=200), encoding="utf-8")
    n_ch = sum(len(p["chapters"]) for p in structure["parts"]); n_src = sum(1 for p in structure["parts"] for c in p["chapters"] if c["kind"] == "source"); n_sec = sum(len(c["sections"]) for p in structure["parts"] for c in p["chapters"])
    print(f"book structure: {len(structure['parts'])} parts, {n_ch} chapters ({n_src} source, {n_ch - n_src} editorial), {n_sec} source sections")
    for p in structure["parts"]:
        print(f"  {p['id']} {p['title_fa']}")
        for c in p["chapters"]:
            print(f"     {c['id']} [{c['kind'][:10]:10s}] {c['title_en'][:70]}  ({len(c['sections'])} sections)")

if __name__ == "__main__":
    main()
