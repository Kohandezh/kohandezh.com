#!/usr/bin/env python3
"""Carry base-edition knowledge-graph extractions over to the new edition for sections whose published text did not change.

The KG cache is keyed by CONTENT ID and content IDs embed the edition, so a new edition would otherwise re-extract (and, with an unavailable
extractor, silently lose) every unit. Entities and relations were extracted from the section text, so a section that is unchanged (or changed only in
its inline edition token) keeps the same extraction; only `content_id` is rewritten, and `carried_from` records provenance. Corrected or new
sections are NOT carried: they are extracted again by tools/kg.py. A cached extraction that recorded an error is never carried.

    python3 tools/e2_kg_carry_cache.py --root <new-edition root>      # needs provenance/e1-e2-map.json and the base cache in work/kg_cache
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    args = ap.parse_args()
    root = Path(args.root)
    cache = root / "work" / "kg_cache"
    mapping = json.loads((root / "provenance" / "e1-e2-map.json").read_text(encoding="utf-8"))["mapping"]
    carried = skipped_existing = no_base = errored = not_carried = 0
    for row in mapping:
        if row["status"] not in ("unchanged", "unchanged_edition_token_only"):
            not_carried += 1
            continue
        src, dst = cache / f"{row['base_content_id']}.json", cache / f"{row['content_id']}.json"
        if dst.exists():
            skipped_existing += 1
            continue
        if not src.exists():
            no_base += 1
            continue
        data = json.loads(src.read_text(encoding="utf-8"))
        if data.get("error"):
            errored += 1
            continue
        data["content_id"], data["carried_from"] = row["content_id"], row["base_content_id"]
        dst.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
        carried += 1
    print(json.dumps({"carried": carried, "already_present": skipped_existing, "base_cache_missing": no_base, "base_error_not_carried": errored, "not_carried_changed_or_corrected": not_carried}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
