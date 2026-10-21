#!/usr/bin/env python3
"""Migrate the edition token inside base-edition editorial data to the new edition (working copy only).

Why: structural/content IDs embed the edition (KDJ-AI-2026E1-P01-C02-S01). Editorial JSON, templates and inline
citations such as «[KDJ-AI-2026E1-P01-C02-S01]» must point at the NEW edition's IDs, otherwise derived-from edges and
in-text citations dangle after the edition changes. ONLY the edition token of IDs is rewritten; no prose is touched.

IDs that carry a content hash (…-S01-020FDB3B) cannot be migrated by token alone because the hash depends on the new
ID. Those are reported, not rewritten; resolve them after the master build (tools/e2_refresh_hashed_ids.py).

    python3 tools/e2_migrate_editorial.py --root <E2 root> [--from 2026E1 --to 2026E2] [--dry-run]

The base edition's own root is never modified by this tool; refuse to run if --root holds the base edition's config.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

import yaml

ID_TAIL = r"-P\d{2}(?:-C\d{2}(?:-S\d{2})?)?"          # section-, chapter- and part-level IDs
HASHED_TAIL = r"-P\d{2}-C\d{2}-S\d{2}"


def rewrite_text(text: str, old: str, new: str) -> tuple[str, int, list[str]]:
    """Rewrite structural IDs; return (new_text, count, hashed_ids_left_alone)."""
    hashed = re.compile(rf"KDJ-AI-{re.escape(old)}{HASHED_TAIL}-[0-9A-F]{{8}}")
    left = hashed.findall(text)
    count = 0

    def sub(m: re.Match) -> str:
        nonlocal count
        # a hashed ID is skipped (it continues with -XXXXXXXX right after the structural part)
        if re.match(r"-[0-9A-F]{8}\b", text[m.end():m.end() + 9]):
            return m.group(0)
        count += 1
        return m.group(0).replace(old, new, 1)

    return re.sub(rf"KDJ-AI-{re.escape(old)}{ID_TAIL}", sub, text), count, left


def walk(value, old: str, new: str, stats: dict, hashed: list):
    if isinstance(value, str):
        out, n, left = rewrite_text(value, old, new)
        stats["rewritten"] += n
        hashed.extend(left)
        return out
    if isinstance(value, list):
        return [walk(v, old, new, stats, hashed) for v in value]
    if isinstance(value, dict):
        return {k: walk(v, old, new, stats, hashed) for k, v in value.items()}
    return value


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("--from", dest="old", default="2026E1")
    ap.add_argument("--to", dest="new", default="2026E2")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    root = Path(args.root)
    prov = yaml.safe_load((root / "config" / "provenance.yaml").read_text(encoding="utf-8"))
    if prov.get("edition_id") != args.new:
        raise SystemExit(f"refusing: {root}/config/provenance.yaml has edition_id={prov.get('edition_id')!r}, expected the NEW edition {args.new!r}")

    files = sorted((root / "work" / "editorial").rglob("*.json")) + sorted((root / "templates").glob("TPL-*.json"))
    total = {"files": 0, "changed_files": 0, "rewritten": 0}
    hashed_report: list[dict] = []
    for f in files:
        doc = json.loads(f.read_text(encoding="utf-8"))
        stats, hashed = {"rewritten": 0}, []
        out = walk(doc, args.old, args.new, stats, hashed)
        total["files"] += 1
        if hashed:
            hashed_report.append({"file": str(f.relative_to(root)), "ids": sorted(set(hashed))})
        if stats["rewritten"]:
            total["changed_files"] += 1
            total["rewritten"] += stats["rewritten"]
            if not args.dry_run:
                f.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps({**total, "dry_run": args.dry_run, "hashed_ids_left_for_refresh": hashed_report}, ensure_ascii=False, indent=1))
    return 0


if __name__ == "__main__":
    sys.exit(main())
