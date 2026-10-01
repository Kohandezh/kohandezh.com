#!/usr/bin/env python3
"""Corpus-wide terminology pipeline (MasterPrompt §18, Phases 4–5).

  mine    : deterministic candidate extraction from all blocks (glossaries, definitions, acronyms, italicised terms, seeds)
  propose : GLM-5.3 proposes Persian equivalents + concept IDs + domain, in batches   → translation_memory/glossary_candidates.json
  review  : Claude Sonnet 4.6 (REVIEW_MAX via Antigravity) reviews high-impact terms   → translation_memory/glossary.json (+ csv, approved/rejected)
No Fable tokens are used.
"""
from __future__ import annotations
import argparse, csv, json, re, sys, time
from collections import Counter, defaultdict
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers  # noqa: E402

SRC = json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]
TAX = ROOT / "taxonomy"; TM = ROOT / "translation_memory"
CAND = (TAX / "terminology_filtered.json") if (TAX / "terminology_filtered.json").exists() else (TAX / "terminology_candidates.json")
PROP = TM / "glossary_candidates.json"
GLOS = TM / "glossary.json"

SEED = ["Artificial Intelligence", "Machine Learning", "Generative AI", "Foundation Model", "Dual-Use Foundation Model", "Risk", "Residual Risk",
        "Risk Appetite", "Risk Tolerance", "Bias", "Fairness", "Harm", "Explainability", "Interpretability", "Transparency", "Accountability", "Privacy",
        "Validity", "Reliability", "Robustness", "Resilience", "Adversarial Machine Learning", "Evasion", "Poisoning", "Model Extraction", "Model Inversion",
        "Synthetic Content", "Provenance", "Watermarking", "Human Oversight", "Govern", "Map", "Measure", "Manage", "SSDF", "ARIA", "AI RMF", "Trustworthy AI",
        "AI Actor", "AI System", "Risk Management", "Safety", "Security", "Impact Assessment", "Data Poisoning", "Prompt Injection", "Jailbreak", "Red Teaming",
        "Large Language Model", "Hallucination", "Confabulation", "Model Card", "Deepfake", "Digital Content Transparency", "Misuse Risk", "Threat Model",
        "Backdoor", "Membership Inference", "Differential Privacy", "Secure Software Development", "Supply Chain", "Third-Party", "Deployment", "Monitoring",
        "Incident", "Governance", "Compliance", "Audit", "Stakeholder", "Socio-technical", "Systemic Bias", "Statistical Bias", "Human Bias", "Meaningful Explanation",
        "Explanation Accuracy", "Knowledge Limits", "Judicial Analytics", "Legal Research", "Standard", "Conformity Assessment", "Interoperability"]

DEF_PATTERNS = [
    re.compile(r"\b([A-Z][A-Za-z\-/ ]{2,60}?)\s+(?:is|are)\s+defined\s+(?:as|in)\b"),
    re.compile(r"\b([A-Z][A-Za-z\-/ ]{2,60}?)\s+refers?\s+to\b"),
    re.compile(r"\b([A-Z][A-Za-z\-/ ]{2,60}?)\s+means\b"),
]
ACRO = re.compile(r"\b((?:[A-Z][A-Za-z\-]+\s?){1,7}?)\s*\(([A-Z]{2,8}s?)\)")
ITAL = re.compile(r"(?<![\w/])_([A-Za-z][^_\n]{2,60}?)_(?![\w/])")
STOP = {"The", "This", "These", "Those", "It", "They", "There", "However", "For", "In", "As", "An", "A", "Such", "Some", "Many", "Each", "Both", "All", "Other"}

def norm(t: str) -> str:
    t = re.sub(r"\s+", " ", t).strip(" .,:;-–—")
    return t

def mine():
    cands = defaultdict(lambda: {"en": "", "freq": 0, "docs": set(), "definitions": [], "acronym": "", "sources": set(), "kind": set()})
    def add(term, doc, kind, definition=None, acronym=None, block_id=None):
        t = norm(term)
        if not t or len(t) < 3 or t.split()[0] in STOP or len(t.split()) > 7 or re.search(r"\d{3,}", t): return
        key = t.lower()
        c = cands[key]; c["en"] = c["en"] or t; c["freq"] += 1; c["docs"].add(doc); c["kind"].add(kind)
        if acronym: c["acronym"] = acronym
        if definition and len(c["definitions"]) < 3: c["definitions"].append({"text": definition[:600], "block_id": block_id, "doc": doc})
    for d in SRC:
        k = d["doc_key"]; p = ROOT / "sources" / "extracted" / f"{k}.blocks.jsonl"
        if not p.exists(): continue
        for line in open(p, encoding="utf-8"):
            b = json.loads(line); text = b["text"]
            if b["block_type"] == "glossary_entry":
                m = re.match(r"^([A-Za-z][A-Za-z\-/ ()]{2,70}?)\s*[:–—-]\s+(.{20,})$", text)
                if m: add(m.group(1), k, "glossary", m.group(2), block_id=b["block_id"])
            for pat in DEF_PATTERNS:
                for m in pat.finditer(text):
                    s = m.start(); sent = text[s:s + 500].split(". ")[0]
                    add(m.group(1), k, "definition", sent, block_id=b["block_id"])
            for m in ACRO.finditer(text):
                add(m.group(1), k, "acronym", acronym=m.group(2))
        md = ROOT / "sources" / "extracted" / f"{k}.md"
        if md.exists():
            for m in ITAL.finditer(md.read_text(encoding="utf-8")):
                add(m.group(1), k, "italic")
    for s in SEED:
        add(s, "SEED", "seed")
    out = []
    for key, c in cands.items():
        score = c["freq"] + 5 * len(c["docs"]) + (20 if "seed" in c["kind"] else 0) + (10 if "glossary" in c["kind"] else 0) + (6 if "definition" in c["kind"] else 0) + (3 if c["acronym"] else 0)
        if score < 6 and "seed" not in c["kind"]: continue
        out.append({"en": c["en"], "key": key, "freq": c["freq"], "docs": sorted(c["docs"] - {"SEED"}), "kinds": sorted(c["kind"]), "acronym": c["acronym"],
                    "definitions": c["definitions"], "score": score})
    out.sort(key=lambda x: -x["score"])
    TAX.mkdir(exist_ok=True)
    CAND.write_text(json.dumps({"generated": db.now(), "count": len(out), "terms": out}, ensure_ascii=False, indent=1), encoding="utf-8")
    print("candidates:", len(out), "| top:", [t["en"] for t in out[:25]])

def propose(batch=40, limit=None):
    cand = json.load(open(CAND, encoding="utf-8"))["terms"]
    if limit: cand = cand[:limit]
    prop = json.load(open(PROP, encoding="utf-8")) if PROP.exists() else {"terms": {}}
    todo = [t for t in cand if t["key"] not in prop["terms"]]
    print("to propose:", len(todo))
    system = ("You are a senior Persian technical terminologist for AI governance, cybersecurity, ML and law. Propose formal modern Iranian Persian equivalents "
              "as used in professionally edited technical books. Prefer established Persian terms; avoid unnecessary transliteration; keep acronyms Latin. "
              "Use proper نیم‌فاصله (U+200C) in compounds. Output strictly valid JSON.")
    for i in range(0, len(todo), batch):
        chunk = todo[i:i + batch]
        items = [{"en": t["en"], "acronym": t["acronym"], "definition_en": (t["definitions"][0]["text"][:300] if t["definitions"] else ""), "docs": t["docs"][:4]} for t in chunk]
        user = ("For each English term return: concept_id (UPPER_SNAKE_CASE, language-independent), preferred_fa, alternatives_fa (0-3), domain "
                "(governance|risk|security|ml|genai|bias|explainability|legal|standards|general), keep_english_in_parentheses (bool), note (short, optional).\n"
                "Return JSON: {\"terms\":[{\"en\":..., \"concept_id\":..., \"preferred_fa\":..., \"alternatives_fa\":[...], \"domain\":..., \"keep_english_in_parentheses\":..., \"note\":...}]}\n\n"
                + json.dumps(items, ensure_ascii=False))
        r = providers.glm_chat("glm-5.3", system, user, purpose="translation", temperature=0.1, max_tokens=6000, json_mode=True)
        txt = r["text"]; s, e = txt.find("{"), txt.rfind("}")
        try:
            data = json.loads(txt[s:e + 1])
        except Exception as ex:
            print("parse fail batch", i, ex); continue
        by_en = {norm(x.get("en", "")).lower(): x for x in data.get("terms", [])}
        for t in chunk:
            x = by_en.get(t["key"]) or by_en.get(t["en"].lower())
            if x:
                prop["terms"][t["key"]] = {**t, **{k: x.get(k) for k in ("concept_id", "preferred_fa", "alternatives_fa", "domain", "keep_english_in_parentheses", "note")}, "proposer": "zai-coding-plan/glm-5.3", "status": "proposed"}
        PROP.write_text(json.dumps(prop, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"batch {i//batch+1}: {len(by_en)} proposals in {r['latency_s']:.0f}s")
    print("proposed total:", len(prop["terms"]))

REVIEW_SCHEMA = {"type": "object", "properties": {"reviews": {"type": "array", "items": {"type": "object", "properties": {
    "en": {"type": "string"}, "verdict": {"type": "string", "enum": ["APPROVE", "REPLACE", "REJECT"]}, "approved_fa": {"type": "string"},
    "rejected_fa": {"type": "array", "items": {"type": "string"}}, "definition_fa": {"type": "string"}, "rationale": {"type": "string"}, "high_impact": {"type": "boolean"}},
    "required": ["en", "verdict", "approved_fa"]}}}, "required": ["reviews"]}

def review(batch=25, top=200):
    prop = json.load(open(PROP, encoding="utf-8"))["terms"]
    glos = json.load(open(GLOS, encoding="utf-8")) if GLOS.exists() else {"glossary_version": "fa-v1", "terms": {}}
    ranked = sorted(prop.values(), key=lambda t: -t["score"])
    high = [t for t in ranked if ("seed" in t["kinds"] or t["score"] >= 15)][:top]
    todo = [t for t in high if t["key"] not in glos["terms"]]
    print("high-impact to review:", len(todo))
    for i in range(0, len(todo), batch):
        chunk = todo[i:i + batch]
        items = [{"en": t["en"], "acronym": t["acronym"], "proposed_fa": t.get("preferred_fa"), "alternatives_fa": t.get("alternatives_fa"),
                  "definition_en": (t["definitions"][0]["text"][:350] if t["definitions"] else ""), "domain": t.get("domain"), "docs": t["docs"][:3]} for t in chunk]
        prompt = ("REVIEW_MAX terminology review for a professionally edited Persian (fa-IR) reference book compiled from NIST AI publications. "
                  "For each term decide APPROVE (proposed Persian is the best formal equivalent), REPLACE (give a better approved_fa), or REJECT (term should stay English/acronym; put the English/acronym in approved_fa). "
                  "Rules: formal modern Iranian Persian; consistency across the book; avoid literal calques and colloquialisms; keep acronyms Latin; use نیم‌فاصله correctly; "
                  "distinguish near-synonyms (e.g. Safety vs Security, Validity vs Reliability, Explainability vs Interpretability, Bias vs Fairness, Risk Appetite vs Risk Tolerance, Accuracy vs Precision). "
                  "Provide a one-sentence definition_fa faithful to the English definition when one is given (do not invent NIST definitions). Reply ONLY with JSON matching the schema.\n\nTERMS:\n"
                  + json.dumps(items, ensure_ascii=False, indent=1))
        r = providers.claude_agy("claude-sonnet-4-6", prompt, purpose="terminology", schema=REVIEW_SCHEMA)
        data = r["json"] or {}
        by_en = {norm(x.get("en", "")).lower(): x for x in data.get("reviews", [])}
        for t in chunk:
            x = by_en.get(t["key"]) or by_en.get(t["en"].lower())
            if not x: continue
            glos["terms"][t["key"]] = {
                "concept_id": t.get("concept_id") or re.sub(r"[^A-Z0-9]+", "_", t["en"].upper()).strip("_"), "english": t["en"], "acronym": t["acronym"],
                "preferred_fa": x.get("approved_fa") or t.get("preferred_fa"), "alternatives_fa": [a for a in (t.get("alternatives_fa") or []) if a != x.get("approved_fa")],
                "rejected_fa": (x.get("rejected_fa") or []) + ([t.get("preferred_fa")] if x.get("verdict") == "REPLACE" and t.get("preferred_fa") else []),
                "definition_en": (t["definitions"][0]["text"] if t["definitions"] else ""), "definition_fa": x.get("definition_fa", ""),
                "domain": t.get("domain"), "source_documents": t["docs"], "status": "approved" if x.get("verdict") in ("APPROVE", "REPLACE") else "english_only",
                "reviewer": "antigravity/claude-sonnet-4-6", "review_mode": "REVIEW_MAX", "rationale": x.get("rationale", ""), "high_impact": bool(x.get("high_impact", True)),
                "reviewed_at": db.now()}
        GLOS.write_text(json.dumps(glos, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"review batch {i//batch+1}: {len(by_en)} verdicts in {r['latency_s']:.0f}s")
    # non-high-impact proposals enter glossary as 'proposed' (usable, flagged for lint, reviewed implicitly during chunk review)
    for t in ranked:
        if t["key"] not in glos["terms"] and t.get("preferred_fa"):
            glos["terms"][t["key"]] = {"concept_id": t.get("concept_id"), "english": t["en"], "acronym": t["acronym"], "preferred_fa": t["preferred_fa"],
                                      "alternatives_fa": t.get("alternatives_fa") or [], "rejected_fa": [], "definition_en": (t["definitions"][0]["text"] if t["definitions"] else ""),
                                      "definition_fa": "", "domain": t.get("domain"), "source_documents": t["docs"], "status": "proposed", "reviewer": None, "high_impact": False}
    GLOS.write_text(json.dumps(glos, ensure_ascii=False, indent=1), encoding="utf-8")
    export()

def export():
    glos = json.load(open(GLOS, encoding="utf-8"))
    con = db.connect()
    with open(TM / "glossary.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f); w.writerow(["concept_id", "english", "acronym", "preferred_fa", "alternatives_fa", "domain", "status", "source_documents"])
        for t in glos["terms"].values():
            w.writerow([t.get("concept_id"), t["english"], t.get("acronym"), t.get("preferred_fa"), "; ".join(t.get("alternatives_fa") or []), t.get("domain"), t["status"], "; ".join(t.get("source_documents") or [])])
            con.execute("INSERT OR REPLACE INTO terms(concept_id,en,fa,alternatives_fa,rejected_fa,def_en,def_fa,acronym,domain,source_documents,frequency,status,reviewer,notes,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        (t.get("concept_id"), t["english"], t.get("preferred_fa"), json.dumps(t.get("alternatives_fa") or [], ensure_ascii=False), json.dumps(t.get("rejected_fa") or [], ensure_ascii=False),
                         t.get("definition_en"), t.get("definition_fa"), t.get("acronym"), t.get("domain"), json.dumps(t.get("source_documents") or []), None, t["status"], t.get("reviewer"), t.get("rationale"), db.now()))
    (TM / "approved_terms.json").write_text(json.dumps({k: v for k, v in glos["terms"].items() if v["status"] == "approved"}, ensure_ascii=False, indent=1), encoding="utf-8")
    (TM / "rejected_terms.json").write_text(json.dumps({k: v["rejected_fa"] for k, v in glos["terms"].items() if v.get("rejected_fa")}, ensure_ascii=False, indent=1), encoding="utf-8")
    counts = Counter(t["status"] for t in glos["terms"].values())
    print("glossary exported:", dict(counts))

if __name__ == "__main__":
    ap = argparse.ArgumentParser(); ap.add_argument("cmd", choices=["mine", "propose", "review", "export"]); ap.add_argument("--limit", type=int); ap.add_argument("--top", type=int, default=200)
    a = ap.parse_args()
    {"mine": mine, "propose": lambda: propose(limit=a.limit), "review": lambda: review(top=a.top), "export": export}[a.cmd]()
