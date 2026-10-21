#!/usr/bin/env python3
"""Materialize `template` objects of edition-2 standards chapters, exactly as tools/editorial.py does for edition 1.

For every section that carries a `template` object:
  * write templates/<template_id>.json  (origin = editorial_template_ir, standard editorial note),
  * append the visible «نمونه فرم» field table to the section text once (idempotent).
Template IDs must follow TPL-<PART>-<n> and must not collide with a template written for another chapter.

    python3 tools/e2_materialize_templates.py --root <E2 root>
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

NOTE = "نمونه پیشنهادی تدوینگر بر پایه مفاهیم مطرح‌شده در منابع این فصل"
ID_RE = re.compile(r"^TPL-P\d{2}-\d+$")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    args = ap.parse_args()
    root = Path(args.root)
    (root / "templates").mkdir(exist_ok=True)
    owners: dict[str, str] = {}
    problems: list[str] = []
    written = 0
    for f in sorted((root / "work" / "editorial").glob("P*/C*.json")):
        doc = json.loads(f.read_text(encoding="utf-8"))
        if doc.get("kind") != "standards_integration":
            continue
        changed = False
        for s in doc.get("sections", []):
            t = s.get("template")
            if not t:
                continue
            tid = t.get("template_id", "")
            if not ID_RE.match(tid):
                problems.append(f"{f.name}: bad template_id {tid!r}")
                continue
            existing = root / "templates" / f"{tid}.json"
            key = f"{doc['part_id']}/{doc['chapter_id']}"
            if owners.setdefault(tid, key) != key or (existing.exists() and json.loads(existing.read_text(encoding="utf-8")).get("e2_chapter") not in (None, key)):
                problems.append(f"{f.name}: template id {tid} already used by another chapter")
                continue
            t["origin"] = "editorial_template_ir"
            t["derived_from"] = list(s.get("derived_from_source_ids", []))     # the site lists these; evidence item IDs must not leak into them
            t["note_fa"] = NOTE
            t["e2_chapter"] = key
            existing.write_text(json.dumps(t, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
            written += 1
            if t.get("fields") and f"**نمونه فرم — {t.get('title_fa', '')}**" not in s["fa_text"]:
                rows = "\n".join(f"| {x.get('name_fa', '')} | {x.get('name_en', '')} | {x.get('description_fa', '')} | {x.get('example_fa', '')} |" for x in t["fields"])
                s["fa_text"] = s["fa_text"].rstrip() + f"\n\n**نمونه فرم — {t.get('title_fa', '')}** ({NOTE})\n\n| فیلد | English | توضیح | نمونه |\n|---|---|---|---|\n{rows}\n"
                changed = True
        if changed:
            f.write_text(json.dumps(doc, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps({"templates_written": written, "problems": problems}, ensure_ascii=False))
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
