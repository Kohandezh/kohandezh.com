#!/usr/bin/env python3
"""Prompt builders, glossary subsetting, structural check and terminology lint (MasterPrompt §23–§29).
Prompt versions are recorded on every job: TRANSLATE_PROMPT_VERSION / REVIEW_PROMPT_VERSION."""
from __future__ import annotations
import json, re
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
TRANSLATE_PROMPT_VERSION = "translate-v1"
REVIEW_PROMPT_VERSION = "review-v1"
STYLE = yaml.safe_load(open(ROOT / "config" / "style.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
GLOS_PATH = ROOT / "translation_memory" / "glossary.json"
_GLOS = None

FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
KNOWN_ACR = {"AI", "ML", "LLM", "LLMs", "RAG", "NLP", "RMF", "SSDF", "ARIA", "NIST", "API", "APIs", "GenAI", "IPD", "DOI", "ISO", "IEC", "IEEE", "OECD", "EU", "US", "USG",
             "GAI", "GOVERN", "MAP", "MEASURE", "MANAGE", "PII", "TEVV", "XAI", "GPT", "GPU", "CPU", "SP", "IR", "OMB", "DoD", "DHS", "FTC", "GDPR", "CVE", "SOC", "SIEM", "IAM"}

def glossary() -> dict:
    global _GLOS
    if _GLOS is None:
        _GLOS = json.load(open(GLOS_PATH, encoding="utf-8"))["terms"] if GLOS_PATH.exists() else {}
    return _GLOS

def glossary_subset(en_text: str, cap: int = 60) -> list[dict]:
    low = en_text.lower()
    hits = []
    for key, t in glossary().items():
        if not t.get("preferred_fa"):
            continue
        if re.search(r"(?<![a-z0-9])" + re.escape(key) + r"(?![a-z0-9])", low):
            hits.append(t)
    hits.sort(key=lambda t: (t.get("status") != "approved", -len(t["english"])))
    return hits[:cap]

def style_summary() -> str:
    o = STYLE["orthography"]; m = STYLE["lexical"]["modality_map"]
    return (f"- Register: {STYLE['register']}.\n- نیم‌فاصله: {o['zwnj']}\n- Punctuation: {o['punctuation']}\n- Digits: {o['digits']}\n"
            f"- Modality map (never strengthen/weaken): " + "; ".join(f"{k}→{v}" for k, v in m.items()) + "\n"
            f"- Keep Latin: {', '.join(STYLE['lexical']['acronyms_kept_latin'])}; document identifiers (NIST AI 100-1, SP 800-218A), standards (ISO/IEC 23894), URLs, DOIs, code.\n"
            f"- First occurrence pattern in a chunk: {STYLE['lexical']['first_occurrence_pattern']} (once per term per chunk).")

TRANSLATE_SYSTEM = """You are a professional technical translator producing the Persian (fa-IR) edition of NIST artificial-intelligence publications for a professionally edited reference book (کهن‌دژ / kohandezh.com).

ABSOLUTE RULES
1. Translate EVERYTHING in the chunk. Never summarize, condense, omit, reorder, or add. Every sentence, list item, table cell, footnote, number, date, unit, percentage, citation, section number, URL, DOI, and identifier must survive.
2. Preserve Markdown structure exactly: headings (#…), list markers (- ), pipe tables (same number of rows and columns, header separator row kept), captions (*…*), footnotes ([^n]: …), [FIGURE …] markers, $$ equations $$ unchanged.
3. Preserve modality exactly (must / shall / should / may / can / could / required / recommended / optional / likely / possible). Never strengthen or weaken a NIST statement.
4. Use the supplied glossary verbatim for listed terms. Keep acronyms in Latin letters. On the first occurrence of a glossary term in this chunk write «اصطلاح فارسی» (English Term); afterwards use the Persian term alone.
5. Persian standard: formal modern Iranian Persian of an edited technical book; correct نیم‌فاصله (می‌شود، سیستم‌ها، داده‌ها); Persian punctuation (، ؛ ؟) and «گیومه فارسی»; Persian digits (۰–۹) in running prose but ASCII digits inside identifiers, URLs, DOIs, section numbers of the source, code, and numeric table data. No colloquial forms, no English word order, no unnecessary transliteration.
6. Keep in Latin script: names of people, organizations (may add a Persian gloss once), document identifiers, standard numbers, product/model names, URLs, code.
7. Text inside [FIGURE …] markers stays unchanged; translate only the caption text after it. Never translate or alter URLs.
8. Output ONLY the Persian translation in the same Markdown structure — no preamble, no notes, no English copy, no explanations."""

def build_translate_prompt(chunk: dict, feedback: str | None = None) -> tuple[str, str]:
    d = SRC.get(chunk["doc_key"], {})
    gl = glossary_subset(chunk["en_text"])
    gl_lines = "\n".join(f"- {t['english']}" + (f" ({t['acronym']})" if t.get("acronym") else "") + f" → {t['preferred_fa']}" for t in gl) or "- (no glossary entries matched)"
    user = f"""SOURCE DOCUMENT: {d.get('series_identifier') or chunk['doc_key']} — {d.get('title','')} ({d.get('publication_type','')}, {d.get('publication_date','')})
SECTION PATH: {' › '.join(chunk.get('heading_path') or [chunk.get('heading','')])}
PAGES: {chunk.get('pages')}

STYLE POLICY
{style_summary()}

GLOSSARY (use verbatim)
{gl_lines}

CONTEXT BEFORE (English, reference only — do NOT translate):
{chunk.get('context_before','')}

===== CHUNK TO TRANSLATE (Markdown) =====
{chunk['en_text']}
===== END OF CHUNK =====

CONTEXT AFTER (English, reference only — do NOT translate):
{chunk.get('context_after','')}
"""
    if feedback:
        user += f"\nREVIEWER FEEDBACK FROM THE PREVIOUS ATTEMPT (must be fixed):\n{feedback}\n"
    user += "\nReturn only the complete Persian translation of the chunk."
    return TRANSLATE_SYSTEM, user

# --------------------------------------------------------------------------- deterministic checks
def _norm_fa(fa: str) -> str:
    return fa.translate(FA_DIGITS).replace("٫", ".").replace("٬", ",").replace("،", ",").replace("‌", "‌")

def _table_data_lines(s: str) -> list[str]:
    return [l for l in s.splitlines() if l.lstrip().startswith("|") and not set(l.replace("|", "").strip()) <= set("-: ")]

def _is_cont_fragment(l: str) -> bool:
    """PDF-extraction artifact: a wrapped continuation of the previous row — first cell empty or starts lowercase mid-sentence."""
    cells = [c.strip() for c in l.strip().strip("|").split("|")]
    first = cells[0] if cells else ""
    return first == "" or (bool(first) and first[0].islower())

def structural_check(en: str, fa: str) -> dict:
    fa_n = _norm_fa(fa)
    def count(pat, s, flags=re.M): return len(re.findall(pat, s, flags))
    res = {"ok": True, "hard_fail": False, "issues": []}
    LIST_EN, LIST_FA = r"^\s*(?:-|\*|\d{1,3}[\.\)])\s", r"^\s*(?:-|•|–|—|\*|▪|○|●|■|\d{1,3}[\.\)]|[۰-۹]{1,3}[\.\)])\s"
    def table_rows(s):  # data rows only (separator rows excluded)
        return sum(1 for l in s.splitlines() if l.lstrip().startswith("|") and not set(l.replace("|", "").strip()) <= set("-: "))
    def table_row_ids(lines):  # leading identifiers (rows whose first cell is a Latin/digit ID) must survive translation verbatim
        ids = []
        for l in lines:
            if _is_cont_fragment(l) or l.count("|") < 2: continue
            t = l.split("|")[1].strip()
            if t and (re.search(r"\d", t) or t.isupper()): ids.append(t)
        return ids
    pairs = [("headings", r"^#{1,6} "), ("list_items", (LIST_EN, LIST_FA)), ("table_rows", None), ("footnotes", r"^\[\^[^\]]+\]:"), ("figures", r"\[FIGURE "), ("equations", r"\$\$")]
    for name, pat in pairs:
        if name == "table_rows":
            ce, cf = table_rows(en), table_rows(fa_n)
            if ce != cf:
                enl, fal = _table_data_lines(en), _table_data_lines(fa_n)
                frag = sum(1 for l in enl if _is_cont_fragment(l))
                merged = 0 < ce - cf <= frag  # translator merged wrapped fragment lines (extraction artifact)
                if merged and any(t not in fa_n for t in table_row_ids(enl)):
                    merged = False  # a dropped row's leading identifier is genuinely missing
                sev = "soft" if merged else "hard"
                res["issues"].append({"type": "table_rows_count", "severity": sev, "en": ce, "fa": cf, "merged_fragments": bool(merged)})
                continue
        elif isinstance(pat, tuple):
            ce, cf = count(pat[0], en), count(pat[1], fa_n)
        else:
            ce, cf = count(pat, en), count(pat, fa_n)
        if ce != cf:
            sev = "hard" if name in ("table_rows", "headings", "figures", "footnotes") or abs(ce - cf) > max(1, 0.15 * ce) else "soft"
            res["issues"].append({"type": f"{name}_count", "severity": sev, "en": ce, "fa": cf})
    # numbers
    en_nums = re.findall(r"(?<![\w.])\d+(?:[.,]\d+)*(?![\w])", en)
    fa_c = re.sub(r",\s+", ",", fa_n)  # tolerate spaced commas inside comma-joined number lists
    missing = [x for x in set(en_nums) if x not in fa_n and x not in fa_c]
    if missing:
        frac = len(missing) / max(1, len(set(en_nums)))
        res["issues"].append({"type": "numbers_missing", "severity": "hard" if frac > 0.05 or len(missing) > 3 else "soft", "missing": sorted(missing)[:20]})
    # urls / dois
    for u in set(re.findall(r"https?://\S+|10\.\d{4,9}/\S+", en)):
        u2 = u.rstrip(".,);`")
        if u2 not in fa_n:
            # source extraction sometimes splits URLs at spaces; accept when the URL prefix survives
            sev = "soft" if u2[:28] in fa_n else "hard"
            res["issues"].append({"type": "url_missing", "severity": sev, "value": u[:120]})
    # acronyms / identifiers
    acr = {a for a in re.findall(r"\b[A-Z][A-Z0-9\-]{1,9}\b", en) if a in KNOWN_ACR or re.match(r"^(AI|SP|IR)$", a) or "-" in a}
    miss_acr = [a for a in acr if a not in fa_n]
    if miss_acr:
        res["issues"].append({"type": "acronyms_missing", "severity": "soft", "missing": sorted(miss_acr)[:15]})
    for ident in set(re.findall(r"\b(?:NIST\s+)?(?:AI|SP|IR)\s?\d{3,4}(?:-\d+[A-Za-z]?)?(?:e\d{4})?\b", en)):
        if ident not in fa_n and ident.replace("NIST ", "") not in fa_n:
            res["issues"].append({"type": "identifier_missing", "severity": "hard", "value": ident})
    # length ratio
    we, wf = len(en.split()), len(fa.split())
    ratio = wf / max(1, we)
    res["length_ratio"] = round(ratio, 2)
    if ratio < 0.7:
        res["issues"].append({"type": "too_short", "severity": "hard", "ratio": ratio})
    elif ratio > 2.2:
        res["issues"].append({"type": "too_long", "severity": "soft", "ratio": ratio})
    # leftover English prose (whole English sentences) — allow identifiers/acronyms/URLs
    # Reference titles, quoted titles and URL/DOI lines legitimately stay in English → exempt those lines.
    eng_sent = []
    for line in fa.splitlines():
        if re.search(r"\b(19|20)\d{2}\b|https?://|doi|arXiv|^\s*(-\s*)?\[\^?\d+\]|^\s*\[\^|«[^»]*[A-Za-z]{4,}[^»]*»|“[^”]*[A-Za-z]{4,}[^”]*”", line):
            continue
        eng_sent += re.findall(r"(?<![/\w])[A-Z][a-z]+(?:\s+[a-z]+){7,}", line)
    if len(eng_sent) > 1:
        res["issues"].append({"type": "untranslated_english", "severity": "hard", "samples": [s[:80] for s in eng_sent[:3]]})
    # CJK / other-script contamination (GLM slip-through) — hard fail
    cjk = re.findall(r"[\u3040-\u30ff\u3400-\u4dbf\u4e00-\u9fff\uf900-\ufaff\uac00-\ud7af]+", fa)
    if cjk:
        res["issues"].append({"type": "script_contamination", "severity": "hard", "samples": [s[:20] for s in cjk[:5]]})
    if any(i["severity"] == "hard" for i in res["issues"]):
        res["ok"] = False; res["hard_fail"] = True
    elif res["issues"]:
        res["ok"] = False
    return res

def terminology_lint(en: str, fa: str) -> dict:
    fa_n = _norm_fa(fa)
    missing, present = [], []
    for t in glossary_subset(en, cap=200):
        if t.get("status") == "english_only":
            continue
        cands = [t["preferred_fa"]] + list(t.get("alternatives_fa") or [])
        if any(c and c in fa_n for c in cands):
            present.append(t["english"])
        else:
            missing.append({"en": t["english"], "expected_fa": t["preferred_fa"]})
        for r in t.get("rejected_fa") or []:
            if r and r in fa_n:
                missing.append({"en": t["english"], "rejected_fa_used": r, "expected_fa": t["preferred_fa"]})
    return {"ok": not missing, "missing": missing[:30], "present": len(present)}

# --------------------------------------------------------------------------- review prompt
REVIEW_SCHEMA = {
    "type": "object",
    "properties": {
        "verdict": {"type": "string", "enum": ["PASS", "PASS_WITH_EDIT", "RETRANSLATE", "ESCALATE"]},
        "scores": {"type": "object", "properties": {"fidelity": {"type": "integer"}, "completeness": {"type": "integer"}, "terminology": {"type": "integer"},
                                                    "fluency": {"type": "integer"}, "structure": {"type": "integer"}}, "required": ["fidelity", "completeness", "terminology", "fluency", "structure"]},
        "critical_defects": {"type": "array", "items": {"type": "string"}},
        "issues": {"type": "array", "items": {"type": "object", "properties": {"type": {"type": "string"}, "severity": {"type": "string"}, "en_excerpt": {"type": "string"}, "fa_excerpt": {"type": "string"}, "fix": {"type": "string"}}, "required": ["type", "severity"]}},
        "edited_fa": {"type": "string", "description": "Full corrected Persian chunk (complete, same Markdown structure) when verdict is PASS_WITH_EDIT; empty otherwise"},
        "retranslate_instructions": {"type": "string"},
        "escalation_reason": {"type": "string"},
        "terminology_notes": {"type": "array", "items": {"type": "object", "properties": {"en": {"type": "string"}, "fa_used": {"type": "string"}, "fa_should_be": {"type": "string"}}, "required": ["en"]}},
    },
    "required": ["verdict", "scores", "critical_defects", "issues", "edited_fa"],
}

REVIEW_INSTRUCTIONS = """You are the PRIMARY QUALITY AUTHORITY (REVIEW_MAX) for the Persian (fa-IR) edition of NIST AI publications. Compare the Persian translation against the ORIGINAL English, sentence by sentence.

CHECK: missing content · extra content · semantic errors · wrong terminology (glossary) · changed numbers/dates/units · missing negation · changed modality (must/shall/should/may/can/could/required/recommended/optional) · legal errors · cybersecurity errors · incorrect document identifiers/acronyms · Persian fluency and formal register · structure preservation (headings, lists, tables, footnotes, figure markers) · نیم‌فاصله and Persian punctuation · consistency with the glossary.

SCORES (0–100): fidelity, completeness, terminology, fluency, structure. Minimums: fidelity ≥ 96, completeness ≥ 99, terminology ≥ 96, fluency ≥ 92, structure ≥ 99. Any critical defect (omission, addition, changed number/date/unit, missing negation, changed modality, wrong identifier, legal or security error) overrides scores.

VERDICT
- PASS: meets every minimum, no critical defect.
- PASS_WITH_EDIT: fixable issues → put the COMPLETE corrected Persian chunk in edited_fa (full text, identical Markdown structure; not a diff, not excerpts). Prefer this over RETRANSLATE whenever you can repair the text yourself.
- RETRANSLATE: omissions/errors too extensive to repair → give precise retranslate_instructions.
- ESCALATE: genuine ambiguity you cannot resolve with confidence (legal ambiguity, security taxonomy dispute, normative-language ambiguity, mathematical ambiguity, high-impact definition, critical table) → escalation_reason must quote the exact passage and the competing readings.

NEVER add content absent from the source, never alter the source's meaning, never "improve" NIST's claims, never convert editorial preference into a defect. Return ONLY JSON matching the schema."""

def build_review_prompt(chunk: dict, fa_text: str, structural: dict | None = None, lint: dict | None = None) -> str:
    d = SRC.get(chunk["doc_key"], {})
    gl = glossary_subset(chunk["en_text"])
    gl_lines = "\n".join(f"- {t['english']} → {t['preferred_fa']}" + (f"  (rejected: {', '.join(t['rejected_fa'])})" if t.get("rejected_fa") else "") for t in gl) or "- (none)"
    auto = ""
    if structural and structural.get("issues"):
        auto += "AUTOMATED STRUCTURAL CHECK FLAGS: " + json.dumps(structural["issues"], ensure_ascii=False)[:1200] + "\n"
    if lint and lint.get("missing"):
        auto += "TERMINOLOGY LINT FLAGS: " + json.dumps(lint["missing"], ensure_ascii=False)[:1200] + "\n"
    return f"""{REVIEW_INSTRUCTIONS}

SOURCE: {d.get('series_identifier') or chunk['doc_key']} — {d.get('title','')} · section: {' › '.join(chunk.get('heading_path') or [chunk.get('heading','')])} · pages {chunk.get('pages')}
FLAGS: {chunk.get('flags')}
{auto}
GLOSSARY (must be used)
{gl_lines}

STYLE POLICY
{style_summary()}

===== ORIGINAL ENGLISH =====
{chunk['en_text']}
===== PERSIAN TRANSLATION UNDER REVIEW =====
{fa_text}
===== END =====
"""

ESCALATION_SCHEMA = {
    "type": "object",
    "properties": {
        "resolution": {"type": "string"}, "final_fa": {"type": "string", "description": "Complete final Persian chunk, same Markdown structure"},
        "scores": {"type": "object", "properties": {"fidelity": {"type": "integer"}, "completeness": {"type": "integer"}, "terminology": {"type": "integer"}, "fluency": {"type": "integer"}, "structure": {"type": "integer"}}},
        "glossary_decisions": {"type": "array", "items": {"type": "object", "properties": {"en": {"type": "string"}, "fa": {"type": "string"}, "rationale": {"type": "string"}}, "required": ["en", "fa"]}},
        "unresolved": {"type": "string", "description": "non-empty only if a human decision is truly required"},
    },
    "required": ["resolution", "final_fa"],
}

def build_escalation_prompt(chunk: dict, fa_text: str, sonnet_review: dict) -> str:
    base = build_review_prompt(chunk, fa_text)
    return ("You are the ESCALATION AUTHORITY (REVIEW_ULTRA). The primary reviewer could not resolve this chunk. Resolve the ambiguity definitively, "
            "grounded strictly in the English source and the glossary; produce the complete final Persian chunk in final_fa (same Markdown structure). "
            "If a glossary term must change, record it in glossary_decisions. Set 'unresolved' only if a human publisher decision is genuinely required.\n\n"
            f"PRIMARY REVIEWER FINDINGS:\n{json.dumps({k: sonnet_review.get(k) for k in ('verdict','scores','critical_defects','issues','escalation_reason','terminology_notes')}, ensure_ascii=False, indent=1)}\n\n"
            + base.split("\n", 1)[1])
