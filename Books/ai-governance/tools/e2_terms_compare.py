#!/usr/bin/env python3
"""Compare verified INSO terms (evidence/out/<ID>/terms.json) with the base-edition glossary.

DATA ONLY. This tool never classifies a conflict as resolved and never edits the glossary. It pairs each verified
standard term with the base glossary entry that has the same English headword (after light normalisation), and reports
how the Persian forms relate:

  same_preferred        official Persian term equals the base preferred term
  official_is_alternate official term is currently only a base alternative / rejected form
  differs               official term differs from the base preferred term (needs a human/semantic decision)
  no_base_entry         the base glossary has no entry with that English headword
  no_english_printed    the standard prints no English headword, so no automatic pairing is possible

Output: <evidence>/terminology-comparison.json  (default: <root>/evidence/terminology-comparison.json)
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def norm_fa(s: str) -> str:
    s = unicodedata.normalize("NFC", s or "").replace("ي", "ی").replace("ك", "ک")
    return re.sub(r"[\s‌‎‏()\[\]«»]+", "", s).casefold()


def norm_en(s: str) -> str:
    s = (s or "").lower().strip()
    s = re.sub(r"\(.*?\)", " ", s)
    s = re.sub(r"[^a-z0-9 ]+", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    # light singularisation so that "stakeholders"/"stakeholder" pair up; never applied to very short words
    if len(s) > 4 and s.endswith("ies"):
        s = s[:-3] + "y"
    elif len(s) > 4 and s.endswith("s") and not s.endswith("ss"):
        s = s[:-1]
    return s


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(ROOT))
    ap.add_argument("--base-glossary", default=None, help="defaults to <root>/translation_memory/glossary.json")
    ap.add_argument("--out", default=None)
    args = ap.parse_args()
    root = Path(args.root)
    gpath = Path(args.base_glossary) if args.base_glossary else root / "translation_memory" / "glossary.json"
    glossary = json.loads(gpath.read_text(encoding="utf-8"))["terms"]
    by_en: dict[str, list[tuple[str, dict]]] = {}
    for key, entry in glossary.items():
        by_en.setdefault(norm_en(entry.get("english", "")), []).append((key, entry))

    report, counts = [], {}
    for tfile in sorted((root / "evidence" / "out").glob("*/terms.json")):
        sid = tfile.parent.name
        data = json.loads(tfile.read_text(encoding="utf-8"))
        for t in data.get("terms", []):
            en = t.get("term_en")
            fa = t.get("term_fa")
            row = {"source_id": sid, "clause": t.get("clause"), "term_fa": fa, "term_en": en, "pdf_page": t.get("pdf_page"), "printed_page": t.get("printed_page"),
                   "definition_fa": t.get("definition_fa"), "entry_type": t.get("entry_type", "term")}
            if not en:
                row["relation"] = "no_english_printed"
            else:
                hits = by_en.get(norm_en(en), [])
                if not hits:
                    row["relation"] = "no_base_entry"
                else:
                    key, base = hits[0]
                    pref, alts, rej = norm_fa(base.get("preferred_fa", "")), [norm_fa(a) for a in base.get("alternatives_fa", [])], [norm_fa(a) for a in base.get("rejected_fa", [])]
                    got = norm_fa(fa or "")
                    row.update({"base_key": key, "base_preferred_fa": base.get("preferred_fa"), "base_alternatives_fa": base.get("alternatives_fa", []), "base_rejected_fa": base.get("rejected_fa", []),
                                "base_definition_fa": base.get("definition_fa", "")})
                    if got == pref:
                        row["relation"] = "same_preferred"
                    elif got in rej:
                        row["relation"] = "official_is_rejected_in_base"
                    elif got in alts:
                        row["relation"] = "official_is_alternate"
                    else:
                        row["relation"] = "differs"
            counts[row["relation"]] = counts.get(row["relation"], 0) + 1
            report.append(row)
    out = Path(args.out) if args.out else root / "evidence" / "terminology-comparison.json"
    out.write_text(json.dumps({"base_glossary": str(gpath), "counts": counts, "total": len(report), "rows": report}, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps({"total": len(report), **counts}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())
