#!/usr/bin/env python3
"""Post-audit normalization (deterministic):
 - controlled publication_type vocabulary (Claude/GLM free text → canonical), series_identifier fill, DOI normalization (bare + url)
 - front_matter flags in blocks re-derived from verified body_start_page (avoids re-running extraction)
 - documents table refreshed
Run after tools/audit_sources.py and tools/extract2.py."""
from __future__ import annotations
import json, re, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

SRC = ROOT / "manifests" / "sources.json"
CANON = {
    "NIST-AI-RMF-PLAYBOOK": ("Playbook", "NIST AI RMF Playbook"),
    "NIST-AI-STD-FED-PLAN-2019": ("Federal Plan", "NIST Federal Engagement Plan (2019)"),
    "NIST-AI-100-1": ("Framework", "NIST AI 100-1"),
    "NIST-AI-100-2E2025": ("Trustworthy and Responsible AI Report (Taxonomy)", "NIST AI 100-2e2025"),
    "NIST-AI-100-3": ("Glossary", "NIST AI 100-3"),
    "NIST-AI-100-4-IPD": ("Initial Public Draft", "NIST AI 100-4 ipd"),
    "NIST-AI-600-1": ("Profile", "NIST AI 600-1"),
    "NIST-AI-700-1": ("Pilot Study", "NIST AI 700-1"),
    "NIST-AI-700-2": ("Pilot Study / Evaluation Report", "NIST AI 700-2"),
    "NIST-AI-800-1-IPD": ("Initial Public Draft", "NIST AI 800-1 ipd"),
    "NIST-IR-8312": ("NISTIR", "NIST IR 8312"),
    "NIST-IR-8367": ("NISTIR", "NIST IR 8367"),
    "NIST-SP-1270": ("Special Publication", "NIST SP 1270"),
    "NIST-SP-800-218A": ("Special Publication (SSDF Community Profile)", "NIST SP 800-218A"),
    "NIST-PAPER-1-FOUNDATIONAL": ("Educational Material (AAAS/NIST Materials for Judges)", "AAAS-NIST Paper 1"),
    "NIST-PAPER-2-TRUSTWORTHINESS": ("Educational Material (AAAS/NIST Materials for Judges)", "AAAS-NIST Paper 2"),
    "NIST-PAPER-3-LEGAL": ("Educational Material (AAAS/NIST Materials for Judges)", "AAAS-NIST Paper 3"),
    "NIST-PAPER-4-BIAS": ("Educational Material (AAAS/NIST Materials for Judges)", "AAAS-NIST Paper 4"),
}

def norm_doi(s: str) -> tuple[str, str]:
    s = (s or "").strip().rstrip(".")
    m = re.search(r"10\.\d{4,9}/[^\s\"<>]+", s)
    if not m:
        return "", ""
    d = m.group(0)
    return d, f"https://doi.org/{d}"

def main():
    src = json.load(open(SRC, encoding="utf-8"))
    con = db.connect()
    for d in src["documents"]:
        k = d["doc_key"]
        canon_type, series = CANON[k]
        d["publication_type_raw"] = d.get("publication_type", "")
        d["publication_type"] = canon_type
        if not d.get("series_identifier") or len(d["series_identifier"]) > 40:
            d["series_identifier"] = series
        doi, url = norm_doi(d.get("doi", ""))
        d["doi"], d["doi_url"] = doi, url
        d["body_start_page"] = int(d.get("body_start_page") or 1)
        # re-derive front_matter flags in blocks
        p = ROOT / "sources" / "extracted" / f"{k}.blocks.jsonl"
        if p.exists():
            blocks = [json.loads(l) for l in open(p, encoding="utf-8")]
            changed = 0
            for b in blocks:
                fm = b["page_start"] < d["body_start_page"]
                if bool(b.get("front_matter")) != fm:
                    b["front_matter"] = fm; changed += 1
                    if fm:
                        b["section_path"], b["section_title"] = "FM", "Front Matter"
            if changed:
                with open(p, "w", encoding="utf-8") as f:
                    for b in blocks:
                        f.write(json.dumps(b, ensure_ascii=False) + "\n")
                for b in blocks:
                    con.execute("UPDATE blocks SET section_number=?, section_path=?, meta_json=json_set(COALESCE(meta_json,'{}'),'$.front_matter',json(?)) WHERE block_id=?",
                                (b.get("section_path"), b.get("section_title"), "true" if b.get("front_matter") else "false", b["block_id"]))
            print(f"{k:30s} type={canon_type[:34]:34s} doi={doi or '—':30s} body_start={d['body_start_page']:3d} fm_flags_changed={changed}")
        con.execute("UPDATE documents SET series_id=?, title=?, subtitle=?, authors=?, organization=?, pub_date=?, doi=?, pub_type=?, pub_status=?, updated_at=? WHERE doc_key=?",
                    (d["series_identifier"], d.get("title"), d.get("subtitle"), json.dumps(d.get("authors") or [], ensure_ascii=False), d.get("organization"), d.get("publication_date"), doi, canon_type, d.get("publication_status"), db.now(), k))
    SRC.write_text(json.dumps(src, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("sources_normalized", {"count": len(src["documents"])}, con)
    print("sources normalized")

if __name__ == "__main__":
    main()
