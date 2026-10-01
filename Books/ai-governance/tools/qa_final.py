#!/usr/bin/env python3
"""Phase 32–33 — release gate (MasterPrompt §85) + final metrics (§89). Deterministic; writes qa/final_report.md and qa/provenance_qa.json etc.
Exit code 0 only if every gate passes → PERSIAN_RELEASE_CANDIDATE = PASS."""
from __future__ import annotations
import json, re, subprocess, sys, zipfile
from pathlib import Path
from html.parser import HTMLParser
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

class LinkParser(HTMLParser):
    def __init__(self): super().__init__(); self.links = []; self.rtl = False; self.lang = None
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "html": self.rtl = a.get("dir") == "rtl"; self.lang = a.get("lang")
        if tag in ("a", "link") and a.get("href"): self.links.append(a["href"])

def main():
    con = db.connect(); gates = {}; notes = []
    def gate(name, ok, detail=""):
        gates[name] = {"pass": bool(ok), "detail": detail}
        print(f"[{'PASS' if ok else 'FAIL'}] {name} {detail}")
    docs = con.execute("SELECT status, COUNT(*) n FROM documents GROUP BY status").fetchall()
    gate("18 unique documents processed", con.execute("SELECT COUNT(*) FROM documents").fetchone()[0] == 18, str({r["status"]: r["n"] for r in docs}))
    ext = json.load(open(ROOT / "manifests" / "extraction.json", encoding="utf-8"))
    low = [k for k, e in ext.items() if e.get("coverage", 0) < 0.9]
    gate("all required text extracted (coverage ≥ 0.9 per doc)", len(ext) == 18 and not low, f"low coverage: {low}")
    st = {r["status"]: r["n"] for r in con.execute("SELECT status, COUNT(*) n FROM chunks GROUP BY status")}
    total = sum(st.values()); approved = st.get("APPROVED", 0)
    gate("every production translation reviewed & approved", total > 0 and approved == total, str(st))
    unresolved = con.execute("SELECT COUNT(*) FROM chunks WHERE status IN ('ESCALATED','RECONCILING')").fetchone()[0]
    gate("all critical escalations resolved", unresolved == 0, f"unresolved={unresolved}")
    # terminology conflicts: approved terms with same concept_id but different fa
    dup = con.execute("SELECT concept_id, COUNT(DISTINCT fa) c FROM terms WHERE status='approved' GROUP BY concept_id HAVING c>1").fetchall()
    gate("no critical terminology conflicts", len(dup) == 0, str([d["concept_id"] for d in dup][:10]))
    book_p = ROOT / "master" / "book.json"
    book = json.load(open(book_p, encoding="utf-8")) if book_p.exists() else {"parts": []}
    units = [S for P in book["parts"] for C in P["chapters"] for S in C["sections"]]
    src_units = [u for u in units if u["origin"] == "source_translation"]
    gate("source mappings complete", all(u["source_documents"] and u["source_blocks"] for u in src_units) and (ROOT / "master" / "source_map.csv").exists(), f"{len(src_units)} source units")
    ids = [u["content_id"] for u in units]
    gate("all Content IDs unique", len(ids) == len(set(ids)) and all(re.match(r"^KDJ-AI-\w+-P\d{2}-C\d{2}-S\d{2}-[0-9A-F]{8}$", i) for i in ids), f"{len(ids)} ids")
    cr = ROOT / "provenance" / "citation-registry.json"
    reg = json.load(open(cr, encoding="utf-8"))["registry"] if cr.exists() else {}
    gate("citation registry valid", cr.exists() and set(reg) == {u["structural_id"] for u in units} and len(reg) == len(units), f"{len(reg)} entries")
    # one approved chunk → exactly one unit (RC1 regression: stale anchors duplicated documents 2–50×) and text volume parity
    import collections as _c
    refs = _c.Counter(c for u in units for c in u.get("chunk_ids", []))
    excluded = set((book.get("coverage") or {}).get("excluded_chunks", {}))
    approved_rows = con.execute("SELECT chunk_id, fa_text FROM approved").fetchall()
    dupes = sorted(c for c, n in refs.items() if n > 1); missing = [r["chunk_id"] for r in approved_rows if r["chunk_id"] not in refs and r["chunk_id"] not in excluded]
    gate("master maps every approved chunk exactly once", bool(units) and not dupes and not missing, f"dupes={dupes[:3]} missing={missing[:3]} excluded={len(excluded)}")
    aw = sum(len(r["fa_text"].split()) for r in approved_rows if r["chunk_id"] not in excluded)
    mw = sum(len(u["fa_text"].split()) for u in src_units); ratio = mw / max(1, aw)
    gate("master text volume matches approved translations (0.9–1.1×)", 0.9 <= ratio <= 1.1, f"master={mw} approved={aw} ratio={ratio:.3f}")
    words_all = sum(len(u["fa_text"].split()) for u in units)
    # DOCX opens
    dp = ROOT / "docx" / "fa" / "book-fa.docx"; ok = False; detail = "missing"
    if dp.exists():
        try:
            from docx import Document
            d = Document(dp); n = len(d.paragraphs); ok = n > 500
            with zipfile.ZipFile(dp) as z: ok = ok and "docProps/custom.xml" in z.namelist()
            detail = f"{n} paragraphs, custom props present={ok}"
        except Exception as e:
            detail = str(e)[:100]
    gate("DOCX opens", ok, detail)
    # PDF renders
    pp = ROOT / "pdf" / "fa" / "book-fa.pdf"; ok = False; detail = "missing"
    if pp.exists():
        info = subprocess.run(["pdfinfo", str(pp)], capture_output=True, text=True).stdout
        pages = int(next((l.split(":")[1] for l in info.splitlines() if l.startswith("Pages:")), "0"))
        txt = subprocess.run(["pdftotext", "-f", "5", "-l", "12", str(pp), "-"], capture_output=True, text=True).stdout
        cap = max(300, words_all // 120)   # RC1 shipped 7,351 pages for 376K words: a bloated render must fail here
        fa_chars = len(re.findall(r"[؀-ۿ]", txt)); ok = 100 < pages <= cap and fa_chars > 2000
        detail = f"pages={pages} (cap {cap}), persian chars in sample={fa_chars}"
    gate("PDF renders (selectable Persian text)", ok, detail)
    # HTML crawl
    hroot = ROOT / "html"; pages = list((hroot / "fa" / "ai-book").rglob("index.html")) if hroot.exists() else []
    broken, rtl_bad = [], []
    for pg in pages:
        lp = LinkParser(); lp.feed(pg.read_text(encoding="utf-8"))
        if not lp.rtl or lp.lang != "fa": rtl_bad.append(str(pg.relative_to(hroot)))
        for href in lp.links:
            if href.startswith("/") and not href.startswith("//") and "ai-book" in href:
                target = hroot / href.lstrip("/").split("#")[0]
                if not (target.exists() or (target / "index.html").exists() or target.with_suffix("").exists()):
                    broken.append(href)
    gate("HTML crawls (internal links resolve)", len(pages) > 20 and not broken, f"{len(pages)} pages, broken={sorted(set(broken))[:8]}")
    gate("RTL passes (lang=fa dir=rtl on every page)", len(pages) > 0 and not rtl_bad, f"bad={rtl_bad[:5]}")
    sp = ROOT / "html" / "search" / "index.json"
    gate("search index works", sp.exists() and len(json.load(open(sp, encoding="utf-8"))["entries"]) == len(units), "")
    rp = ROOT / "knowledge" / "chunks.jsonl"
    gate("RAG corpus generated", rp.exists() and sum(1 for _ in open(rp, encoding="utf-8")) >= len(units), "")
    kp = ROOT / "knowledge" / "graph.json"
    kg = json.load(open(kp, encoding="utf-8")) if kp.exists() else {"nodes": [], "edges": []}
    gate("Knowledge Graph generated", len(kg["nodes"]) > 100 and len(kg["edges"]) > 100 and (ROOT / "knowledge" / "graph.graphml").exists(), f"{len(kg['nodes'])} nodes, {len(kg['edges'])} edges")
    hp = ROOT / "provenance" / "hashes.json"
    gate("all canonical sections hashed", hp.exists() and len(json.load(open(hp, encoding="utf-8"))["units"]) == len(units), "")
    rel = ROOT / "release"
    vr = subprocess.run([sys.executable, str(ROOT / "tools" / "verify_release.py"), str(rel)], capture_output=True, text=True) if (rel / "23_release_manifest.json").exists() else None
    gate("Merkle Tree valid & Release Manifest valid (verify_release.py)", vr is not None and vr.returncode == 0, (vr.stdout.strip().splitlines()[-1] if vr else "no release"))
    vp = ROOT / "provenance" / "verification.json"; v = json.load(open(vp, encoding="utf-8")) if vp.exists() else {}
    gate("signing status explicitly reported", bool(v.get("signing_status")), v.get("signing_status", ""))
    gate("provenance verification works", vr is not None and "VERIFICATION PASS" in (vr.stdout or ""), "")
    gpt = con.execute("SELECT COUNT(*) FROM model_usage WHERE provider='openai' AND purpose!='image'").fetchone()[0]
    gate("GPT_TEXT_USAGE = 0", gpt == 0, f"openai text calls={gpt}")
    all_pass = all(g["pass"] for g in gates.values())
    # metrics §89
    m = {"TOTAL_SOURCES": 18, "TOTAL_SOURCE_PAGES": sum(json.load(open(ROOT / 'manifests' / 'sources.json', encoding='utf-8'))["documents"][i]["pages"] for i in range(18)),
         "TOTAL_SOURCE_BLOCKS": con.execute("SELECT COUNT(*) FROM blocks").fetchone()[0], "TOTAL_TRANSLATION_CHUNKS": total, "TOTAL_APPROVED_CHUNKS": approved,
         "TOTAL_ESCALATED_CHUNKS": con.execute("SELECT COUNT(*) FROM approved WHERE escalated=1").fetchone()[0], "TOTAL_PERSIAN_WORDS": sum(len(u["fa_text"].split()) for u in units),
         "TOTAL_PARTS": len(book["parts"]), "TOTAL_CHAPTERS": sum(1 for P in book["parts"] for C in P["chapters"] if C["sections"]), "TOTAL_SECTIONS": len(units),
         "TOTAL_TABLES": sum(e.get("tables", 0) for e in ext.values()), "TOTAL_FIGURES": sum(e.get("figures", 0) for e in ext.values()),
         "TOTAL_REFERENCES": con.execute("SELECT COUNT(*) FROM blocks WHERE block_type='reference'").fetchone()[0], "TOTAL_GLOSSARY_TERMS": con.execute("SELECT COUNT(*) FROM terms").fetchone()[0],
         "TOTAL_CONTENT_IDS": len(ids), "TOTAL_RAG_CHUNKS": sum(1 for _ in open(rp, encoding="utf-8")) if rp.exists() else 0, "TOTAL_KNOWLEDGE_ENTITIES": len(kg["nodes"]), "TOTAL_KNOWLEDGE_RELATIONS": len(kg["edges"]),
         "translator_model_distribution": {r["translator_model"]: r["n"] for r in con.execute("SELECT translator_model, COUNT(*) n FROM approved GROUP BY translator_model")},
         "claude_sonnet_review_count": con.execute("SELECT COUNT(*) FROM reviews WHERE mode='REVIEW_MAX'").fetchone()[0], "claude_opus_escalation_count": con.execute("SELECT COUNT(*) FROM reviews WHERE mode='REVIEW_ULTRA'").fetchone()[0],
         "failed_jobs": con.execute("SELECT COUNT(*) FROM jobs WHERE status='FAILED'").fetchone()[0], "retried_jobs": con.execute("SELECT COUNT(*) FROM jobs WHERE retry_count>0").fetchone()[0],
         "GPT_TEXT_CALLS": gpt, "PERSIAN_RELEASE_CANDIDATE": "PASS" if all_pass else "FAIL"}
    (ROOT / "qa" / "release_gate.json").write_text(json.dumps({"gates": gates, "metrics": m, "generated": db.now()}, ensure_ascii=False, indent=1), encoding="utf-8")
    lines = ["# Final QA report — Persian Release Candidate", "", f"Generated {db.now()}", "", "## Release gate (MasterPrompt §85)", ""] + [f"- [{'x' if g['pass'] else ' '}] {k} — {g['detail']}" for k, g in gates.items()] + ["", "## Final metrics (§89)", ""] + [f"- **{k}**: {v}" for k, v in m.items()] + ["", f"## PERSIAN_RELEASE_CANDIDATE = **{m['PERSIAN_RELEASE_CANDIDATE']}**", ""]
    (ROOT / "qa" / "final_report.md").write_text("\n".join(lines), encoding="utf-8")
    (ROOT / "qa" / "provenance_qa.json").write_text(json.dumps({k: gates[k] for k in gates if any(w in k for w in ("Merkle", "signing", "provenance", "Content IDs", "hashed", "citation"))}, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("release_gate", {"pass": all_pass, "failed": [k for k, g in gates.items() if not g["pass"]]}, con)
    print("PERSIAN_RELEASE_CANDIDATE =", m["PERSIAN_RELEASE_CANDIDATE"])
    return 0 if all_pass else 1

if __name__ == "__main__":
    sys.exit(main())
