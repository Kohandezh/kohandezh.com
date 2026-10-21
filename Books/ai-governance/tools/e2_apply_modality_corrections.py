#!/usr/bin/env python3
"""Apply verified statement corrections (modality-check results) to evidence/out/<source>/statements.json and log them.

    python3 tools/e2_apply_modality_corrections.py --root <root> RESULT.json [RESULT2.json ...]

Only items with verdict 'wrong' and a corrected_statement_fa are changed; the statement keeps its position (so every item ID is stable)
and its modality label; each change is appended to evidence/evidence-corrections.md with the printed expression found on the page image.
"""
from __future__ import annotations
import argparse, json, re, sys
from pathlib import Path

SHORT = {"15816-3": "INSO-15816-3-1403", "23681": "INSO-23681-1403", "23682": "INSO-23682-1403", "23683": "INSO-23683-1403", "23684": "INSO-23684-1403",
         "23685": "INSO-23685-1403", "23728": "INSO-23728-1403", "23733": "INSO-23733-1403", "42001": "INSO-42001-1404"}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("results", nargs="+")
    ap.add_argument("--skip", default="", help="comma list of item IDs already corrected by hand")
    args = ap.parse_args()
    root = Path(args.root)
    skip = {x for x in args.skip.split(",") if x}
    changed, log = 0, []
    docs: dict[str, dict] = {}
    for rf in args.results:
        for r in json.loads(Path(rf).read_text(encoding="utf-8")):
            if r["verdict"] != "wrong" or not r.get("corrected_statement_fa") or r["item_id"] in skip:
                continue
            m = re.match(r"S([\w-]+?)-(\d{3})$", r["item_id"])
            sid = SHORT[m.group(1)]
            path = root / "evidence" / "out" / sid / "statements.json"
            doc = docs.setdefault(sid, json.loads(path.read_text(encoding="utf-8")))
            st = doc["statements"][int(m.group(2)) - 1]
            if st["statement_fa"] == r["corrected_statement_fa"]:
                continue
            st["statement_fa"] = r["corrected_statement_fa"]
            changed += 1
            log.append(f"- 2026-10-18 {sid} statements.json {r['item_id']} (clause {st.get('clause')}, pdf {st.get('pdf_page')} / printed {st.get('printed_page')}): modality wording corrected against the page image — printed «{r.get('printed_expression')}»; {r.get('note')} Position and modality label unchanged.")
    for sid, doc in docs.items():
        (root / "evidence" / "out" / sid / "statements.json").write_text(json.dumps(doc, ensure_ascii=False, indent=1), encoding="utf-8")
    if log:
        with (root / "evidence" / "evidence-corrections.md").open("a", encoding="utf-8") as f:
            f.write("\n".join(log) + "\n")
    print(json.dumps({"corrected": changed}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
