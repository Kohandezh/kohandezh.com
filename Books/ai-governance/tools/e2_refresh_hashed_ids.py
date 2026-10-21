#!/usr/bin/env python3
"""Refresh content IDs that carry a hash in the working editorial data of a later edition.

A content ID (KDJ-AI-<edition>-Pxx-Cxx-Sxx-<HASH8>) depends on the section's canonical text and on the edition token, so the
base edition's hashed IDs cannot be migrated by token alone (see e2_migrate_editorial.py, which reports them). After a master
build has produced provenance/content_ids.json, this tool replaces each base-edition hashed ID by the new edition's content ID
for the SAME section position. It only rewrites an ID whose target section exists; anything else is reported and left alone.

The section a hashed ID points to must not itself depend on the file being edited, otherwise the build never reaches a fixed
point. The tool checks this by rebuilding nothing: run `master.py` again after a change and compare the reported ID.

    python3 tools/e2_refresh_hashed_ids.py --root <root> [--from 2026E1 --to 2026E2] [--dry-run]

Exit 0 = nothing left to refresh (or refreshed). Exit 3 = at least one hashed ID could not be resolved.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("--from", dest="old", default="2026E1")
    ap.add_argument("--to", dest="new", default="2026E2")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    root = Path(args.root)
    ids = json.loads((root / "provenance" / "content_ids.json").read_text(encoding="utf-8"))["ids"]
    by_structural = {v["structural_id"]: cid for cid, v in ids.items()}
    pat = re.compile(rf"KDJ-AI-{re.escape(args.old)}(-P\d{{2}}-C\d{{2}}-S\d{{2}})-[0-9A-F]{{8}}")
    changed, unresolved = [], []
    for f in sorted((root / "work" / "editorial").rglob("*.json")):
        text = f.read_text(encoding="utf-8")

        def sub(m: re.Match) -> str:
            target = by_structural.get(f"KDJ-AI-{args.new}{m.group(1)}")
            if not target:
                unresolved.append({"file": str(f.relative_to(root)), "id": m.group(0)})
                return m.group(0)
            return target

        # a `"note"` value is a historical QA record about the base edition: keep its IDs as written there
        parts = re.split(r'("note":\s*"(?:[^"\\]|\\.)*")', text)
        new_text = "".join(p if p.startswith('"note"') else pat.sub(sub, p) for p in parts)
        if new_text != text:
            changed.append(str(f.relative_to(root)))
            if not args.dry_run:
                f.write_text(new_text, encoding="utf-8")
    print(json.dumps({"changed_files": changed, "unresolved": unresolved, "dry_run": args.dry_run}, ensure_ascii=False, indent=1))
    return 3 if unresolved else 0


if __name__ == "__main__":
    sys.exit(main())
