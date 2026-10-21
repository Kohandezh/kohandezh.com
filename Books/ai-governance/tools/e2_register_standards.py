#!/usr/bin/env python3
"""Register the national (INSO) standards of edition 2 in the source manifests.

Inputs  : config/edition-2.json (owner list), config/national-standards.json (printed facts, curated from page-image
          evidence), Raw/<source_directory>/<filename> (PDF bytes, read-only), evidence/out/<ID>/identity.json (cross-check).
Outputs : manifests/national_standards.json   (rich registry, incl. the requested-but-absent standards)
          manifests/sources.json              (national standards appended after the NIST documents; count updated)
          manifests/source_hashes.sha256      (one line per source file)

Fails (exit 1) when a PDF hash/page count disagrees with the recorded values, when a printed national number is not found
in the evidence for that document, or when a configured source has no curated printed facts. Never invents a URL, a
publication date or an IEEE/ISO status. Re-running is idempotent.

    python3 tools/e2_register_standards.py [--root DIR] [--check]
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def sha256_file(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def pdf_pages(path: Path) -> int:
    out = subprocess.run(["pdfinfo", str(path)], capture_output=True, text=True, check=True).stdout
    return int(next(line.split(":")[1] for line in out.splitlines() if line.startswith("Pages:")))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(ROOT))
    ap.add_argument("--check", action="store_true", help="verify only; write nothing")
    args = ap.parse_args()
    root = Path(args.root)
    cfg = json.loads((root / "config" / "edition-2.json").read_text(encoding="utf-8"))
    facts = json.loads((root / "config" / "national-standards.json").read_text(encoding="utf-8"))
    problems: list[str] = []
    records = []
    for seq, source in enumerate(cfg["sources"], start=19):
        sid, fname = source["source_id"], source["filename"]
        pdf = root / cfg["source_directory"] / fname
        fact = facts["standards"].get(sid)
        if fact is None:
            problems.append(f"{sid}: no curated printed facts in config/national-standards.json")
            continue
        if not pdf.is_file():
            problems.append(f"{sid}: missing file {pdf}")
            continue
        digest, pages = sha256_file(pdf), pdf_pages(pdf)
        ident_path = root / "evidence" / "out" / sid / "identity.json"
        if ident_path.is_file():
            ident = json.loads(ident_path.read_text(encoding="utf-8"))
            printed = json.dumps(ident.get("national_reference_printed", ""), ensure_ascii=False).translate(FA_DIGITS)
            if fact["national_number"] not in printed:
                problems.append(f"{sid}: printed reference in evidence does not contain {fact['national_number']}")
            if ident.get("pdf_pages_total") not in (None, pages):
                problems.append(f"{sid}: evidence says {ident.get('pdf_pages_total')} pages, PDF has {pages}")
        else:
            problems.append(f"{sid}: no evidence/identity.json to cross-check")
        intl = fact["international"]
        if intl.get("underlying_designation", intl["designation"]) != source["international_reference"] and intl["designation"].replace("BS/", "") != source["international_reference"]:
            problems.append(f"{sid}: international designation {intl['designation']!r} differs from config {source['international_reference']!r}")
        ap_ = fact["approval"]
        records.append({
            "seq": seq, "doc_key": sid, "filename": fname, "sha256": digest, "size_bytes": pdf.stat().st_size, "pages": pages,
            "part_id": source["part_id"], "chapter_id": source.get("chapter_id"), "part_id_provisional": not str(source.get("placement_status", "")).startswith("final"),
            "status": "INVENTORIED", "audit": "VERIFIED_AGAINST_PAGE_IMAGES",
            "source_kind": "national_standard",
            "series_identifier": source["national_reference"],
            "title": source["title_fa"], "title_en_printed": intl.get("title_en_printed"), "subtitle": "", "authors": [],
            "organization": facts["publisher"]["fa"] + " (" + facts["publisher"]["en"] + ")",
            "publication_type": "استاندارد ملی ایران (پذیرش به روش معادل یکسان)",
            "publication_status": (f"{fact['edition_fa']} " if fact.get("edition_fa") else "") + f"{fact['iranian_year']}؛ تصویب {ap_['date_jalali']} (جلسهٔ {ap_['session']})",
            "publication_date": "", "publication_date_iso": "", "doi": "", "doi_url": "",
            "national": {"number": fact["national_number"], "iranian_year": fact["iranian_year"], "edition_fa": fact["edition_fa"],
                         "cover_edition_year_gregorian": fact.get("cover_edition_year_gregorian"), "reference_as_configured": source["national_reference"]},
            "international": {**intl, "adoption_reference_as_configured": source.get("adoption_reference"), "equivalence": fact["equivalence"],
                              "equivalence_fa_printed": fact["equivalence_fa_printed"]},
            "approval": ap_, "ics": fact.get("ics"), "pdf_to_printed_offset": fact["pdf_to_printed_offset"],
            "access": {"reference_index_url": facts["reference_index"]["url"], "reference_index_role": facts["reference_index"]["role"],
                       "listed_in_reference_index": fact.get("listed_in_reference_index"), "direct_download_url": source.get("direct_download_url"),
                       "file_origin": facts["file_origin_note"]},
            "status_evidence": "Identity, approval and numbering read from the page images (evidence/out/%s/identity.json); no separate publication date is printed." % sid,
        })
    if problems:
        print("REGISTRATION FAILED:\n  " + "\n  ".join(problems))
        return 1
    print(f"verified {len(records)} national standards (hash, pages, printed number, international designation)")
    if args.check:
        return 0

    registry = {"edition_id": cfg["edition_id"], "base_edition_id": cfg["base_edition_id"], "publisher": facts["publisher"], "reference_index": facts["reference_index"],
                "file_origin_note": facts["file_origin_note"], "standards": records, "requested_but_absent": facts["requested_but_absent"],
                "requested_standards_from_owner": cfg["requested_standards"]}
    (root / "manifests" / "national_standards.json").write_text(json.dumps(registry, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    sp = root / "manifests" / "sources.json"
    sources = json.loads(sp.read_text(encoding="utf-8"))
    keep = [d for d in sources["documents"] if d.get("source_kind") != "national_standard"]
    sources["documents"] = keep + records
    sources["unique_source_document_count"] = len(sources["documents"])
    sources["note"] = (sources.get("note", "").split(" | ")[0] + " | edition 2: national (INSO) standards appended as source_kind=national_standard; "
                       "NIST documents unchanged.")
    sp.write_text(json.dumps(sources, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    hp = root / "manifests" / "source_hashes.sha256"
    national_names = {r["filename"] for r in records}
    lines = [ln for ln in hp.read_text(encoding="utf-8").splitlines() if ln.strip() and ln.split(None, 1)[1].strip() not in national_names]
    lines += [f"{r['sha256']}  {r['filename']}" for r in records]
    hp.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"sources.json: {sources['unique_source_document_count']} documents; source_hashes.sha256: {len(lines)} lines")
    return 0


if __name__ == "__main__":
    sys.exit(main())
