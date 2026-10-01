#!/usr/bin/env python3
"""Build manifests/tables.json, figures.json, pages.json from extracted blocks (MasterPrompt §13). Deterministic."""
from __future__ import annotations
import json, sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

EXTRACTED = ROOT / "sources" / "extracted"

def main():
    tables, figures, pages = [], [], defaultdict(lambda: {"doc": "", "page": 0, "blocks": 0, "words": 0, "has_table": False, "has_figure": False})
    for f in sorted(EXTRACTED.glob("*.blocks.jsonl")):
        doc = f.name.replace(".blocks.jsonl", "")
        for line in open(f, encoding="utf-8"):
            b = json.loads(line)
            for pg in range(b["page_start"], b["page_end"] + 1):
                r = pages[(doc, pg)]
                r["doc"] = doc; r["page"] = pg; r["blocks"] += 1; r["words"] += len(b["text"].split())
            if b["block_type"] == "table":
                for pg in range(b["page_start"], b["page_end"] + 1):
                    pages[(doc, pg)]["has_table"] = True
                tables.append({"table_id": b["block_id"], "doc_key": doc, "page": b["page_start"], "caption": b.get("caption") or "",
                               "n_rows": b.get("n_rows"), "n_cols": b.get("n_cols"), "continued": "(Continued)" in (b.get("caption") or ""),
                               "section_path": b.get("section_path"), "source_hash": b.get("sha256") or db.sha256_text(b["text"])})
            elif b["block_type"] == "figure":
                for pg in range(b["page_start"], b["page_end"] + 1):
                    pages[(doc, pg)]["has_figure"] = True
                figures.append({"figure_id": b.get("figure_id") or b["block_id"], "block_id": b["block_id"], "doc_key": doc, "page": b["page_start"],
                                "caption": b.get("caption") or "", "ocr_available": bool(b.get("ocr_text")), "asset": b.get("asset_path") or "",
                                "section_path": b.get("section_path")})
    pages_list = sorted(pages.values(), key=lambda r: (r["doc"], r["page"]))
    for name, data in (("tables.json", tables), ("figures.json", figures), ("pages.json", pages_list)):
        (ROOT / "manifests" / name).write_text(json.dumps({"generated": db.now(), "count": len(data), "items": data}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"tables={len(tables)} figures={len(figures)} pages={len(pages_list)}")

if __name__ == "__main__":
    main()
