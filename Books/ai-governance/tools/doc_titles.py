#!/usr/bin/env python3
"""Persian titles for the 18 source documents and for chapter headings (GLM first pass; flagged for Claude editorial QA).
Writes taxonomy/doc_titles_fa.json; master.py uses it for chapter titles when no translated heading exists."""
from __future__ import annotations
import json, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers, prompts  # noqa: E402

OUT = ROOT / "taxonomy" / "doc_titles_fa.json"

def main():
    src = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]
    items = [{"doc_key": d["doc_key"], "title": d.get("title", ""), "subtitle": d.get("subtitle", ""), "type": d.get("publication_type", "")} for d in src]
    gl = prompts.glossary_subset(" ".join(i["title"] + " " + i["subtitle"] for i in items), cap=80)
    system = "You are a senior Persian technical translator. Translate NIST publication titles into formal, publishable Persian book-chapter titles. Keep acronyms Latin. Use the glossary verbatim. Output strictly valid JSON."
    user = ("Glossary:\n" + "\n".join(f"- {t['english']} → {t['preferred_fa']}" for t in gl) + "\n\nReturn {\"titles\":[{\"doc_key\":..., \"title_fa\":..., \"subtitle_fa\":...}]} for:\n" + json.dumps(items, ensure_ascii=False))
    r = providers.glm_chat("glm-5.3-flash", system, user, purpose="translation", temperature=0.1, max_tokens=3000, json_mode=True)
    txt = r["text"]; data = json.loads(txt[txt.find("{"):txt.rfind("}") + 1])
    out = {t["doc_key"]: {"title_fa": t.get("title_fa", ""), "subtitle_fa": t.get("subtitle_fa", ""), "model": "zai-coding-plan/glm-5.3-flash", "status": "proposed_pending_claude_qa"} for t in data.get("titles", [])}
    OUT.write_text(json.dumps(out, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("doc_titles_proposed", {"count": len(out)})
    for k, v in out.items():
        print(f"{k:30s} {v['title_fa']}")

if __name__ == "__main__":
    main()
