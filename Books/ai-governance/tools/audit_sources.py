#!/usr/bin/env python3
"""Phase 1–2: bibliographic + structural audit of the 18 sources.
Extraction model: GLM-5.3 (Z.ai). Verification: Claude Sonnet 4.6 Thinking via Antigravity (REVIEW_MAX).
No Fable tokens are used. Results → manifests/sources.json (+ qa/source_audit.json). Resumable per document."""
from __future__ import annotations
import json, re, sys, time
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers  # noqa: E402

SRC = ROOT / "manifests" / "sources.json"
QA = ROOT / "qa" / "source_audit.json"

SCHEMA = {
  "type": "object",
  "properties": {
    "series_identifier": {"type": "string"}, "title": {"type": "string"}, "subtitle": {"type": "string"},
    "authors": {"type": "array", "items": {"type": "string"}}, "organization": {"type": "string"},
    "publication_date_printed": {"type": "string"}, "publication_date_iso": {"type": "string"},
    "doi": {"type": "string"}, "publication_type": {"type": "string"}, "publication_status": {"type": "string"},
    "status_evidence": {"type": "string"}, "front_matter_end_page": {"type": "integer"}, "body_start_page": {"type": "integer"},
    "toc_present": {"type": "boolean"},
    "top_level_sections": {"type": "array", "items": {"type": "object", "properties": {"number": {"type": "string"}, "title": {"type": "string"}, "printed_page": {"type": "string"}}, "required": ["title"]}},
    "has_glossary": {"type": "boolean"}, "has_references": {"type": "boolean"}, "has_line_numbers": {"type": "boolean"},
    "special_structures": {"type": "array", "items": {"type": "string"}}, "notes": {"type": "string"}
  },
  "required": ["title", "publication_type", "publication_status", "status_evidence", "body_start_page", "top_level_sections", "doi", "authors", "publication_date_printed"]
}

TYPES = ("Framework | Playbook | Special Publication | NISTIR (NIST Interagency/Internal Report) | Profile | Pilot Study | "
         "Evaluation Report | Initial Public Draft | Draft for Public Comment | Educational Material | Federal Plan | Glossary | "
         "Trustworthy and Responsible AI Report | Research/Educational Paper | Other")

def page_text(base: str, p: int) -> str:
    f = ROOT / "sources" / "pages" / base / f"{p:04d}.txt"
    return f.read_text(errors="ignore") if f.exists() else ""

def build_context(d: dict) -> str:
    base = d["filename"][:-4]; n = d["pages"]
    parts = []
    for p in range(1, min(n, 8) + 1):
        parts.append(f"===== PDF PAGE {p} =====\n" + page_text(base, p)[:6000])
    # find TOC pages beyond 8
    for p in range(9, min(n, 14) + 1):
        t = page_text(base, p)
        if re.search(r"table of contents|^\s*contents\s*$|\.{5,}\s*\d+", t, re.I | re.M):
            parts.append(f"===== PDF PAGE {p} (TOC?) =====\n" + t[:5000])
    parts.append(f"===== PDF PAGE {n} (LAST) =====\n" + page_text(base, n)[:3000])
    return "\n\n".join(parts)

def extract_with_glm(d: dict) -> dict:
    ctx = build_context(d)
    system = ("You are a meticulous bibliographic cataloguer for NIST publications. Extract ONLY what is printed in the supplied "
              "pages. Never infer a DOI, author, or date that is not printed. Output strictly valid JSON matching the schema.")
    user = f"""Catalogue this PDF. filename={d['filename']} total_pdf_pages={d['pages']}

Schema (JSON): {json.dumps(SCHEMA)}

Guidance:
- publication_type must be one of: {TYPES}. A Playbook is not a standard. ".ipd" = Initial Public Draft. NIST IR = NISTIR. "NIST AI 100-x/600-x/700-x/800-x" belong to the NIST Trustworthy and Responsible AI report series (state the sub-type: Framework, Profile, Pilot Study, Evaluation Report, Glossary, Initial Public Draft, etc.).
- publication_status: e.g. "Final", "Version 1.0", "Initial Public Draft", "Draft for public comment", as printed; quote evidence (<= 15 words).
- body_start_page / front_matter_end_page are PDF page indices (1-based), not printed labels.
- top_level_sections: from the table of contents (top-level numbered sections and appendices) with printed page labels.
- has_line_numbers: true if margin line numbers (1,2,3…) appear on draft pages.
- special_structures: recurring structured elements (e.g. GOVERN/MAP/MEASURE/MANAGE subcategory tables, Suggested Actions cards, attack taxonomy tables, SSDF practice tables PW.x, numbered glossary entries).

PAGES:
{ctx}"""
    r = providers.glm_chat("glm-5.3", system, user, purpose="translation", temperature=0.0, max_tokens=4000, json_mode=True)
    txt = r["text"]
    s, e = txt.find("{"), txt.rfind("}")
    return json.loads(txt[s:e + 1]), r

def verify_with_claude(d: dict, rec: dict) -> dict:
    base = d["filename"][:-4]
    pages = "\n\n".join(f"===== PDF PAGE {p} =====\n{page_text(base, p)[:4500]}" for p in range(1, min(d['pages'], 4) + 1))
    schema = {"type": "object", "properties": {
        "verdict": {"type": "string", "enum": ["CONFIRMED", "CORRECTED"]},
        "corrections": {"type": "array", "items": {"type": "object", "properties": {"field": {"type": "string"}, "was": {"type": "string"}, "now": {"type": "string"}, "evidence": {"type": "string"}}, "required": ["field", "now", "evidence"]}},
        "residual_uncertainty": {"type": "array", "items": {"type": "string"}}}, "required": ["verdict", "corrections"]}
    fields = {k: rec.get(k) for k in ("series_identifier", "title", "subtitle", "authors", "organization", "publication_date_printed", "publication_date_iso", "doi", "publication_type", "publication_status", "body_start_page")}
    prompt = f"""REVIEW_MAX task: adversarially verify a bibliographic record against the printed source pages. Assume the record may be wrong. Check EVERY field below against the text. A DOI must appear verbatim in the pages or be "" . Publication type must be precise (Playbook ≠ standard; NISTIR; Initial Public Draft; Profile; Pilot Study...). Reply ONLY with JSON matching this schema: {json.dumps(schema)}

RECORD UNDER TEST (filename {d['filename']}):
{json.dumps(fields, ensure_ascii=False, indent=1)}

SOURCE PAGES 1-4:
{pages}"""
    r = providers.claude_agy("claude-sonnet-4-6", prompt, purpose="review", schema=schema)
    return r["json"] or {"verdict": "UNPARSED", "corrections": [], "raw": r["text"][:500]}

def main():
    src = json.load(open(SRC, encoding="utf-8"))
    qa = json.load(open(QA, encoding="utf-8")) if QA.exists() else {}
    con = db.connect()
    for d in src["documents"]:
        k = d["doc_key"]
        if d.get("audit") == "VERIFIED":
            continue
        t0 = time.time()
        if k not in qa or "glm" not in qa[k]:
            rec, r = extract_with_glm(d)
            qa[k] = {"glm": rec, "glm_usage": r["usage"], "glm_latency": r["latency_s"]}
            QA.write_text(json.dumps(qa, ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"[{k}] GLM extracted in {r['latency_s']:.0f}s: {rec.get('title','')[:70]} | {rec.get('publication_type')} | {rec.get('publication_status')}")
        rec = qa[k]["glm"]
        if "claude" not in qa[k]:
            v = verify_with_claude(d, rec)
            qa[k]["claude"] = v
            QA.write_text(json.dumps(qa, ensure_ascii=False, indent=1), encoding="utf-8")
            print(f"[{k}] Claude Sonnet 4.6 verdict={v.get('verdict')} corrections={len(v.get('corrections', []))}")
        v = qa[k]["claude"]
        final = dict(rec)
        for c in v.get("corrections", []):
            f = c.get("field")
            if f in final and c.get("now") is not None:
                val = c["now"]
                if f == "authors":
                    val = [a.strip() for a in re.split(r";|,(?![^()]*\))", val) if a.strip()] if isinstance(val, str) else val
                if f == "body_start_page":
                    try: val = int(val)
                    except Exception: pass
                final[f] = val
        d.update({
            "series_identifier": final.get("series_identifier", ""), "title": final.get("title", ""), "subtitle": final.get("subtitle", ""),
            "authors": final.get("authors", []), "organization": final.get("organization", ""),
            "publication_date": final.get("publication_date_printed", ""), "publication_date_iso": final.get("publication_date_iso", ""),
            "doi": final.get("doi", ""), "publication_type": final.get("publication_type", ""), "publication_status": final.get("publication_status", ""),
            "status_evidence": final.get("status_evidence", ""), "front_matter_end_page": final.get("front_matter_end_page", 0),
            "body_start_page": final.get("body_start_page", 1), "toc": final.get("top_level_sections", []),
            "has_glossary": final.get("has_glossary", False), "has_references": final.get("has_references", False),
            "has_line_numbers": final.get("has_line_numbers", False), "special_structures": final.get("special_structures", []),
            "text_native": True, "audit": "VERIFIED", "audit_models": {"extract": "zai-coding-plan/glm-5.3", "verify": "antigravity/claude-sonnet-4-6"},
            "audit_verdict": v.get("verdict"), "audit_corrections": v.get("corrections", []), "audit_uncertainty": v.get("residual_uncertainty", []),
        })
        con.execute("UPDATE documents SET series_id=?, title=?, subtitle=?, authors=?, organization=?, pub_date=?, doi=?, pub_type=?, pub_status=?, text_native=1, status='AUDITED', meta_json=?, updated_at=? WHERE doc_key=?",
                    (d["series_identifier"], d["title"], d["subtitle"], json.dumps(d["authors"], ensure_ascii=False), d["organization"], d["publication_date"], d["doi"], d["publication_type"], d["publication_status"],
                     json.dumps({kk: d[kk] for kk in ("toc", "special_structures", "has_line_numbers", "body_start_page", "front_matter_end_page")}, ensure_ascii=False), db.now(), k))
        src["documents"] = [d if x["doc_key"] == k else x for x in src["documents"]]
        SRC.write_text(json.dumps(src, ensure_ascii=False, indent=1), encoding="utf-8")
        db.event("source_audited", {"doc_key": k, "verdict": v.get("verdict"), "seconds": round(time.time() - t0)}, con)
    print("AUDIT_COMPLETE", sum(1 for d in src["documents"] if d.get("audit") == "VERIFIED"), "/ 18")

if __name__ == "__main__":
    main()
