#!/usr/bin/env python3
"""Edition-2 release gates (run after tools/qa_final.py). Deterministic; exit code 0 only when every gate passes.

    python3 tools/qa_e2.py --root <E2 root> --base-root <E1 root> [--snapshot <E1 snapshot dir>]

Gates: edition consistency across every output · national-standard registry integrity (hash/pages/URLs/coverage) · base edition untouched (hash list
from the recoverable snapshot) · E1→E2 map completeness · new chapters complete AND content-bound reviewed · glossary contract and review integrity ·
RAG/KG consistency (no failed extractions, only new-edition IDs) · source/attribution safety scan · truthful signing status · edition label in HTML/PDF/DOCX.
A failed gate is a real finding; nothing here edits any file.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
import zipfile
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))

NEW_CHAPTERS = {"P01": ["C54", "C55", "C56"], "P02": ["C57", "C58", "C59", "C60"], "P03": ["C61"], "P04": ["C62"], "P06": ["C63"], "P07": ["C64", "C65"]}
OLD_ID = re.compile(r"KDJ-AI-2026E1-P\d{2}-C\d{2}-S\d{2}")
BLOCKLIST = ["ترجمهٔ رسمی NIST است", "تأییدشدهٔ NIST", "تأیید‌شده توسط NIST", "مصوب سازمان ملی استاندارد ایران برای این کتاب", "گواهی‌نامهٔ رسمی INSO", "مورد تأیید ISO"]


def sha256(p: Path) -> str:
    return hashlib.sha256(p.read_bytes()).hexdigest()


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=str(ROOT))
    ap.add_argument("--base-root", required=True)
    ap.add_argument("--snapshot", default="/Users/emperor/Documents/AI/kohandezh-archive/ai-governance-e1-snapshot-20261017")
    args = ap.parse_args()
    root, base, snap = Path(args.root), Path(args.base_root), Path(args.snapshot)
    gates: dict[str, tuple[bool, str]] = {}

    def gate(name: str, ok: bool, detail: str = "") -> None:
        gates[name] = (bool(ok), detail)
        print(f"[{'PASS' if ok else 'FAIL'}] {name} {detail}")

    prov = yaml.safe_load((root / "config" / "provenance.yaml").read_text(encoding="utf-8"))
    pub = yaml.safe_load((root / "config" / "publishing.yaml").read_text(encoding="utf-8"))
    edition, label = prov["edition_id"], pub.get("edition_label_fa")
    book = json.loads((root / "master" / "book.json").read_text(encoding="utf-8"))
    units = [s for p in book["parts"] for c in p["chapters"] for s in c["sections"]]

    # ---- 1. edition consistency
    seen = {"provenance.yaml": edition, "publishing.yaml": pub["edition_id"], "master/book.json": book["edition_id"],
            "provenance/content_ids.json": json.loads((root / "provenance/content_ids.json").read_text(encoding="utf-8"))["edition_id"],
            "provenance/citation-registry.json": json.loads((root / "provenance/citation-registry.json").read_text(encoding="utf-8"))["edition_id"],
            "html/search/index.json": json.loads((root / "html/search/index.json").read_text(encoding="utf-8"))["edition"],
            "knowledge/graph.json": json.loads((root / "knowledge/graph.json").read_text(encoding="utf-8"))["edition"]}
    rm = root / "release" / "23_release_manifest.json"
    if rm.exists():
        seen["release manifest"] = json.loads(rm.read_text(encoding="utf-8"))["edition_id"]
    gate("edition id identical in every data artifact", len(set(seen.values())) == 1 and edition == "2026E2", json.dumps(seen, ensure_ascii=False))
    stale = sum(len(OLD_ID.findall(p.read_text(encoding="utf-8", errors="ignore"))) for p in [root / "master/book.json", root / "knowledge/chunks.jsonl", root / "html/search/index.json"])
    html_stale = sum(len(OLD_ID.findall(p.read_text(encoding="utf-8", errors="ignore"))) for p in (root / "html" / "fa").rglob("*.html"))
    gate("no base-edition section IDs left in book/RAG/search/HTML", stale == 0 and html_stale == 0, f"book+rag+search={stale} html={html_stale}")

    # ---- 2. national-standard registry
    reg = json.loads((root / "manifests" / "national_standards.json").read_text(encoding="utf-8"))
    cfg = json.loads((root / "config" / "edition-2.json").read_text(encoding="utf-8"))
    bad, urls = [], []
    for s in reg["standards"]:
        f = root / cfg["source_directory"] / s["filename"]
        pages = int(next(l.split(":")[1] for l in subprocess.run(["pdfinfo", str(f)], capture_output=True, text=True).stdout.splitlines() if l.startswith("Pages:")))
        if sha256(f) != s["sha256"] or pages != s["pages"]:
            bad.append(s["doc_key"])
        if s["access"].get("direct_download_url"):
            urls.append(s["doc_key"])
    gate("9 national standards registered with verified hash and page count", len(reg["standards"]) == 9 and not bad, f"bad={bad}")
    gate("no invented direct download URLs", not urls, f"urls={urls}")
    gate("six requested-but-absent standards recorded as absent", len(reg["requested_but_absent"]) == 6 and all(not r["in_local_set"] for r in reg["requested_but_absent"]), "")
    used = {d for u in units for d in u["source_documents"]}
    missing_cov = [s["doc_key"] for s in reg["standards"] if s["doc_key"] not in used]
    gate("every national standard is cited by at least one section", not missing_cov, f"missing={missing_cov}")
    bib = {e["doc_key"] for e in json.loads((root / "master" / "bibliography.json").read_text(encoding="utf-8"))["entries"]}
    gate("bibliography lists all 27 sources", len(bib) == 27 and all(s["doc_key"] in bib for s in reg["standards"]), f"{len(bib)} entries")

    # ---- 3. base edition untouched
    drift = []
    lines = (snap / "e1-protected-files.sha256").read_text(encoding="utf-8").splitlines()[1:]
    for line in lines:
        h, rel = line.split("  ", 1)
        p = base / rel
        if not p.is_file() or sha256(p) != h:
            drift.append(rel)
    gate("base edition (E1) protected files unchanged since the snapshot", not drift and len(lines) > 50, f"files={len(lines)} drift={drift[:5]}")

    # ---- 4. E1→E2 map
    mp = json.loads((root / "provenance" / "e1-e2-map.json").read_text(encoding="utf-8"))
    corr = json.loads((root / "evidence" / "e1-corrections.json").read_text(encoding="utf-8"))["corrections"] if (root / "evidence/e1-corrections.json").exists() else []
    s = mp["summary"]
    gate("E1→E2 map: nothing missing, nothing silently modified", s["missing"] == 0 and s["modified"] == 0 and s["corrected_against_supplied_standard"] == len({c["position"] for c in corr}),
         json.dumps(s))
    gate("E1→E2 map accounts for every section", s["unchanged"] + s["unchanged_edition_token_only"] + s["corrected_against_supplied_standard"] == s["base_sections"] and s["base_sections"] + s["added"] == len(units),
         f"{s['base_sections']} + {s['added']} = {len(units)} units")

    # ---- 5. new chapters complete and reviewed (verdicts bound to the current text)
    import e2_review_chapter as RC
    index, e1, _ = RC.load_context(root)
    problems = []
    for part, chs in NEW_CHAPTERS.items():
        for ch in chs:
            f = root / "work" / "editorial" / part / f"{ch}.json"
            d = json.loads(f.read_text(encoding="utf-8"))
            if not d.get("sections"):
                problems.append(f"{ch}: no sections")
                continue
            qa = d.get("qa") or {}
            if qa.get("pass") is not True or not qa.get("reviewer") or not qa.get("content_bound"):
                problems.append(f"{ch}: qa not stamped")
                continue
            current = {i: RC.section_sha(i, s_, index, e1) for i, s_ in enumerate(d["sections"])}
            valid = RC.collect(root / "evidence" / "chapter-reviews", f"{part}-{ch}", current)
            bad_i = [i for i in range(len(d["sections"])) if ("sonnet", i) not in valid or valid[("sonnet", i)]["verdict"] != "PASS"]
            if bad_i:
                problems.append(f"{ch}: sections {bad_i} lack a PASS verdict for the current text")
    gate("12 new chapters present, validated, policy-reviewed with verdicts bound to the current text", not problems, "; ".join(problems[:6]))

    # ---- 6. glossary
    g = json.loads((root / "translation_memory" / "glossary.json").read_text(encoding="utf-8"))["terms"]
    contract = [k for k, t in g.items() if not re.match(r"^[A-Za-z][A-Za-z0-9_ ]{0,63}$", t["concept_id"]) or t["status"] not in ("approved", "proposed", "english_only")
                or not t["english"].strip() or not t["preferred_fa"].strip()]
    cids = [t["concept_id"] for t in g.values()]
    gate("glossary satisfies the plugin contract with unique concept IDs", not contract and len(cids) == len(set(cids)), f"{len(g)} entries; contract={contract[:3]}")
    lax = [k for k, t in g.items() if t.get("e2", {}).get("change") in ("changed", "new") and t["status"] == "approved"
           and not (t["e2"].get("review", {}).get("sonnet", {}).get("verdict") == "AGREE" and t.get("reviewer"))]
    inherited = [k for k, t in g.items() if t.get("e2", {}).get("change") == "changed" and t["status"] == "approved" and t["e2"].get("previous_status") == "approved" and not t["e2"].get("review")]
    gate("no changed/new glossary entry is 'approved' without a recorded review (no inherited approval)", not lax and not inherited, f"lax={lax[:3]} inherited={inherited[:3]}")
    counts = {}
    for t in g.values():
        counts[t["status"]] = counts.get(t["status"], 0) + 1
    print("   glossary status counts:", counts)

    # ---- 7/9. RAG and KG
    rp = root / "knowledge" / "chunks.jsonl"
    chunk_ids = {json.loads(l)["content_id"] for l in rp.open(encoding="utf-8")}
    gate("RAG corpus covers every section of the new edition", {u["content_id"] for u in units} <= chunk_ids, f"{len(chunk_ids)} chunks")
    krp, kgp = root / "knowledge" / "kg_extraction_report.json", root / "knowledge" / "graph.json"
    if krp.is_file() and kgp.is_file():
        kr = json.loads(krp.read_text(encoding="utf-8"))
        kg = json.loads(kgp.read_text(encoding="utf-8"))
        uid = {u["content_id"] for u in units}
        foreign = [e["content_id"] for e in kg["edges"] if e["content_id"] not in uid][:3]
        pubs = [n for n in kg["nodes"] if n["type"] == "Publication"]
        reg = {d["doc_key"] for d in json.loads((root / "manifests" / "sources.json").read_text(encoding="utf-8"))["documents"]}
        missing_pubs = sorted(reg - {n.get("doc_key") for n in pubs})
        gate("knowledge graph: no failed extractions, only new-edition content IDs, a publication node for every registered source", not kr["failed_content_ids"] and not foreign and not missing_pubs,
             f"failed={len(kr['failed_content_ids'])} foreign={foreign} missing_registered={missing_pubs} publications={len(pubs)} entities={len(kg['nodes'])} relations={len(kg['edges'])}")
    else:
        gate("knowledge graph: no failed extractions, only new-edition content IDs, a publication node for every registered source", False, "knowledge/graph.json or kg_extraction_report.json is missing (the KG step has not run)")

    # ---- 10. attribution safety
    hits = []
    for u in units:
        for phrase in BLOCKLIST:
            if phrase in u["fa_text"]:
                hits.append((u["structural_id"], phrase))
    gate("no false-attribution phrases in the book text", not hits, str(hits[:3]))

    # ---- 12/13. signing and edition label
    vp = root / "provenance" / "verification.json"
    v = json.loads(vp.read_text(encoding="utf-8")) if vp.exists() else {}
    gate("signing status reported truthfully (development key is not a publisher signature)", "PUBLISHER_SIGNING_KEY_REQUIRED" in v.get("signing_status", "") or v.get("signature_kind") == "PUBLISHER", v.get("signing_status", ""))
    idx_html = (root / "html" / "fa" / "ai-book" / "index.html")
    label_ok = bool(label) and idx_html.is_file() and label in idx_html.read_text(encoding="utf-8")
    try:
        pdf_text = subprocess.run(["pdftotext", "-f", "1", "-l", "4", str(root / "pdf" / "fa" / "book-fa.pdf"), "-"], capture_output=True, text=True).stdout
        pdf_ok = label in pdf_text
    except Exception:  # noqa: BLE001
        pdf_ok = False
    try:
        from docx import Document
        doc = Document(root / "docx" / "fa" / "book-fa.docx")
        docx_ok = any(label in p.text for p in doc.paragraphs[:40])
    except Exception:  # noqa: BLE001
        docx_ok = False
    gate("visible «ویرایش دوم» label in HTML, PDF and DOCX", label_ok and pdf_ok and docx_ok, f"html={label_ok} pdf={pdf_ok} docx={docx_ok}")

    ok = all(g_[0] for g_ in gates.values())
    (root / "qa").mkdir(exist_ok=True)
    (root / "qa" / "e2_gate.json").write_text(json.dumps({n: {"pass": o, "detail": d} for n, (o, d) in gates.items()}, ensure_ascii=False, indent=1), encoding="utf-8")
    print("EDITION_2_GATES =", "PASS" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
