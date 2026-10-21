#!/usr/bin/env python3
"""Explicit edition-to-edition mapping (default 2026E1 -> 2026E2) for citations and content IDs.

Content IDs and structural IDs embed the edition (KDJ-AI-<EDITION>-Pxx-Cxx-Sxx[-HASH8]), so every ID changes between
editions even when the text does not. This tool records, for every section of the base edition, where it lives in the
new edition and whether its published text changed, so old citations stay resolvable.

It reuses master.canonical_text for text comparison; the hash normalization algorithm is NOT re-implemented here.

    python3 tools/e1_e2_map.py --base-root /path/to/E1/root          # E2 root = this book root
Outputs (in the E2 root): provenance/e1-e2-map.json, provenance/e1-e2-map.csv
Exit code 1 if any base section has no counterpart (nothing may silently disappear).
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import master  # noqa: E402  (canonical_text only; importing does not build anything)

POS = re.compile(r"^KDJ-AI-(?P<ed>\d{4}E\d+)-(?P<part>P\d{2})-(?P<ch>C\d{2})-(?P<sec>S\d{2})$")


def units(book_path: Path) -> tuple[str, dict[str, dict]]:
    book = json.loads(book_path.read_text(encoding="utf-8"))
    out: dict[str, dict] = {}
    for part in book["parts"]:
        for chapter in part["chapters"]:
            for unit in chapter["sections"]:
                m = POS.match(unit["structural_id"])
                if not m:
                    raise SystemExit(f"unexpected structural id: {unit['structural_id']}")
                out[f"{m['part']}-{m['ch']}-{m['sec']}"] = unit
    return book["edition_id"], out


def text_digest(unit: dict, edition_token: tuple[str, str] | None = None) -> str:
    """Digest of origin + canonical title + canonical text; optionally undo an edition-token migration first."""
    title, text = unit["title_fa"], unit["fa_text"]
    if edition_token:
        title, text = title.replace(edition_token[0], edition_token[1]), text.replace(edition_token[0], edition_token[1])
    payload = "\n".join([unit.get("origin", ""), master.canonical_text(title), master.canonical_text(text)])
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base-root", required=True, help="root holding the base edition's master/book.json")
    ap.add_argument("--root", default=str(ROOT), help="root of the new edition (default: this book root)")
    ap.add_argument("--corrections", default=None, help="JSON with documented corrections (default: <root>/evidence/e1-corrections.json if present)")
    args = ap.parse_args()
    base_ed, base = units(Path(args.base_root) / "master" / "book.json")
    new_root = Path(args.root)
    new_ed, new = units(new_root / "master" / "book.json")
    if base_ed == new_ed:
        raise SystemExit("base and new edition are identical; nothing to map")

    corr_path = Path(args.corrections) if args.corrections else new_root / "evidence" / "e1-corrections.json"
    corrections: dict[str, list[dict]] = {}
    if corr_path.is_file():
        for c in json.loads(corr_path.read_text(encoding="utf-8")).get("corrections", []):
            corrections.setdefault(c["position"], []).append({"reason": c["reason"], "evidence_ids": c.get("evidence_ids", [])})
    prefix_old, prefix_new = f"KDJ-AI-{base_ed}-", f"KDJ-AI-{new_ed}-"
    rows, missing = [], []
    for pos, b in base.items():
        n = new.get(pos)
        if n is None:
            missing.append(b["structural_id"])
            rows.append({"base_structural_id": b["structural_id"], "base_content_id": b["content_id"], "structural_id": None, "content_id": None, "status": "missing"})
            continue
        row = {"base_structural_id": b["structural_id"], "base_content_id": b["content_id"], "structural_id": n["structural_id"], "content_id": n["content_id"],
               "origin": n["origin"], "canonical_url": n["canonical_url"]}
        if text_digest(b) == text_digest(n):
            row["status"] = "unchanged"
        elif text_digest(b) == text_digest(n, (prefix_new, prefix_old)):
            row["status"] = "unchanged_edition_token_only"      # only inline citation IDs were re-pointed to the new edition
        elif pos in corrections:
            row["status"] = "corrected_against_supplied_standard"
            row["corrections"] = corrections[pos]
        else:
            row["status"] = "modified"
        rows.append(row)
    added = [{"structural_id": n["structural_id"], "content_id": n["content_id"], "origin": n["origin"], "canonical_url": n["canonical_url"], "title_fa": n["title_fa"]}
             for pos, n in new.items() if pos not in base]
    count = lambda s: sum(r["status"] == s for r in rows)  # noqa: E731
    summary = {"base_sections": len(base), "new_sections": len(new), "unchanged": count("unchanged"), "unchanged_edition_token_only": count("unchanged_edition_token_only"),
               "corrected_against_supplied_standard": count("corrected_against_supplied_standard"), "modified": count("modified"), "missing": len(missing), "added": len(added)}
    doc = {"base_edition_id": base_ed, "edition_id": new_ed,
           "note": ("Every ID changes between editions because the edition is part of the ID. 'unchanged' means the published title, origin and canonical "
                    "text are identical; the hash differs only because the structural ID differs. 'unchanged_edition_token_only' = identical except inline "
                    "citation IDs re-pointed to the new edition. 'corrected_against_supplied_standard' = compiler-authored claim corrected against the supplied standard "
                    "(reason and evidence IDs recorded). Position (part/chapter/section) is stable."),
           "summary": summary, "mapping": rows, "added": added}
    out = new_root / "provenance"
    out.mkdir(parents=True, exist_ok=True)
    (out / "e1-e2-map.json").write_text(json.dumps(doc, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    with open(out / "e1-e2-map.csv", "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=["base_structural_id", "base_content_id", "structural_id", "content_id", "status", "origin", "canonical_url"])
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k) for k in w.fieldnames})
    print(json.dumps(summary, ensure_ascii=False))
    if missing:
        print("MISSING base sections:", ", ".join(missing[:10]))
    return 1 if missing else 0


if __name__ == "__main__":
    sys.exit(main())
