#!/usr/bin/env python3
"""Refresh stale section anchors in taxonomy/book_structure.yaml against the current extraction (idempotent).

- A section whose block_id no longer exists is re-resolved through master.resolve_anchor (heading title match, then
  section_path); block_id / section_path / page are updated and title_en is replaced by the heading block's text
  (extraction already strips margin line numbers, the yaml generator did not).
- A chapter whose anchors all land in front matter ('FM' blocks) while the document has top-level body headings gets its
  sections regenerated from those headings (NIST-AI-800-1-IPD after the 2026-09-17 re-extraction).
Prints every change; writes the yaml only when something changed. Structure (parts/chapters/ids/slugs) is never altered.
"""
import sys
from pathlib import Path
import re
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import master  # noqa: E402


def main() -> int:
    p = ROOT / "taxonomy" / "book_structure.yaml"
    y = yaml.safe_load(open(p, encoding="utf-8")); changed = 0
    for part in y["parts"]:
        for ch in part["chapters"]:
            if ch.get("kind") != "source":
                continue
            dk = ch["source_doc"]; blocks = master.load_blocks(dk)
            bmap = {b["block_id"]: b for b in blocks}; order = {b["block_id"]: b["order_no"] for b in blocks}
            secs = ch.get("sections") or []; resolved = []
            for s in secs:
                a, how = master.resolve_anchor(s, blocks, order)
                if a and how != "block_id":
                    print(f"{ch['id']} {dk}: {s.get('title_en')!r} → {a} (by {how}); title_en → {bmap[a]['text'].strip()!r}")
                    s["block_id"] = a; s["section_path"] = bmap[a]["section_path"]; s["page"] = str(bmap[a]["page_start"]); s["title_en"] = bmap[a]["text"].strip()
                    changed += 1
                elif not a:
                    print(f"{ch['id']} {dk}: UNRESOLVED {s.get('title_en')!r}")
                resolved.append(a)
            if secs and all(a and bmap[a]["section_path"] == "FM" for a in resolved):
                tops, seen = [], set()
                for b in blocks:
                    sp = b["section_path"]
                    if b["block_type"] == "heading" and sp not in ("FM", "0") and re.fullmatch(r"[A-Z0-9]+", sp) and sp not in seen:
                        seen.add(sp); tops.append(b)
                if tops:
                    print(f"{ch['id']} {dk}: all {len(secs)} anchors are front matter → regenerating {len(tops)} sections from top-level headings: {[b['text'][:40] for b in tops]}")
                    ch["sections"] = [{"title_en": b["text"].strip(), "section_path": b["section_path"], "page": str(b["page_start"]), "block_id": b["block_id"]} for b in tops]
                    changed += 1
    if changed:
        p.write_text(yaml.safe_dump(y, allow_unicode=True, sort_keys=False, width=200), encoding="utf-8")
        print(f"wrote {p} ({changed} changes)")
    else:
        print("no changes")
    return 0


if __name__ == "__main__":
    sys.exit(main())
