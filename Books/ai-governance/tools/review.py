#!/usr/bin/env python3
"""Claude review workers (MasterPrompt §27–§29): Sonnet 4.6 REVIEW_MAX, Opus 4.6 REVIEW_ULTRA escalation, approval + TM."""
from __future__ import annotations
import json, re, sys
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers, prompts  # noqa: E402
from translate import load_chunk  # noqa: E402

RCFG = yaml.safe_load(open(ROOT / "config" / "reviewer.yaml", encoding="utf-8"))
SONNET = RCFG["primary_reviewer"]["model"]; OPUS = RCFG["escalation_reviewer"]["model"]
MINS = {k: v["min"] for k, v in RCFG["scores"].items()}

def current_translation(chunk_id: str, con) -> dict:
    r = con.execute("SELECT t.* FROM translations t JOIN chunks c ON c.chunk_id=t.chunk_id AND c.current_version=t.version WHERE t.chunk_id=?", (chunk_id,)).fetchone()
    if not r:
        raise RuntimeError(f"no current translation for {chunk_id}")
    return dict(r)

def save_review(chunk_id: str, version: int, mode: str, model: str, rv: dict, con, latency=None, usage=None):
    doc, ch = chunk_id.split("::")
    out = ROOT / "work" / ("escalations" if mode == "REVIEW_ULTRA" else "reviews") / doc; out.mkdir(parents=True, exist_ok=True)
    rec = {"chunk_id": chunk_id, "version": version, "mode": mode, "model": model, "prompt_version": prompts.REVIEW_PROMPT_VERSION, "review": rv, "latency_s": latency, "usage": usage, "created_at": db.now()}
    (out / f"{ch}.v{version}.{mode}.json").write_text(json.dumps(rec, ensure_ascii=False, indent=1), encoding="utf-8")
    s = rv.get("scores") or {}
    con.execute("INSERT OR REPLACE INTO reviews(chunk_id,version,reviewer_model,mode,verdict,fidelity,completeness,terminology,fluency,structure,issues_json,edited_fa,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                (chunk_id, version, model, mode, rv.get("verdict"), s.get("fidelity"), s.get("completeness"), s.get("terminology"), s.get("fluency"), s.get("structure"),
                 json.dumps({"issues": rv.get("issues"), "critical": rv.get("critical_defects"), "terms": rv.get("terminology_notes")}, ensure_ascii=False),
                 rv.get("edited_fa") or rv.get("final_fa") or "", rv.get("escalation_reason") or rv.get("resolution") or "", rec["created_at"]))
    return rec

def approve(chunk_id: str, version: int, fa: str, translator_model: str, reviewer_model: str, escalated: bool, con, glossary_version="fa-v1"):
    chunk = load_chunk(chunk_id)
    h = db.sha256_text(fa)
    con.execute("INSERT OR REPLACE INTO approved(chunk_id,version,fa_text,sha256,translator_model,reviewer_model,escalated,approved_at) VALUES(?,?,?,?,?,?,?,?)",
                (chunk_id, version, fa, h, translator_model, reviewer_model, int(escalated), db.now()))
    con.execute("INSERT OR REPLACE INTO tm(source_hash,en_norm,fa_text,translator_model,reviewer_model,review_effort,glossary_version,source_refs,approved_at) VALUES(?,?,?,?,?,?,?,?,?)",
                (chunk["sha256"], chunk["en_text"], fa, translator_model, reviewer_model, "REVIEW_ULTRA" if escalated else "REVIEW_MAX", glossary_version, json.dumps(chunk["block_ids"]), db.now()))
    con.execute("UPDATE chunks SET status='APPROVED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    doc, ch = chunk_id.split("::")
    out = ROOT / "work" / "checkpoints" / doc; out.mkdir(parents=True, exist_ok=True)
    (out / f"{ch}.approved.json").write_text(json.dumps({"chunk_id": chunk_id, "version": version, "sha256": h, "fa_text": fa, "translator_model": translator_model,
                                                          "reviewer_model": reviewer_model, "escalated": escalated, "approved_at": db.now()}, ensure_ascii=False, indent=1), encoding="utf-8")
    with open(ROOT / "translation_memory" / "fa" / "tm_fa.jsonl", "a", encoding="utf-8") as f:
        f.write(json.dumps({"source_hash": chunk["sha256"], "chunk_id": chunk_id, "fa_sha256": h, "translator_model": translator_model, "reviewer_model": reviewer_model,
                            "escalated": escalated, "glossary_version": glossary_version, "approved_at": db.now()}, ensure_ascii=False) + "\n")
    db.event("chunk_approved", {"chunk_id": chunk_id, "version": version, "escalated": escalated}, con)

def scores_ok(s: dict) -> bool:
    return all((s or {}).get(k, 0) >= v for k, v in MINS.items())

def _retranslate_now(chunk_id: str, fb: str, con) -> dict:
    import translate as _tr
    try:
        rec = _tr.translate_chunk(chunk_id, feedback=fb, con=con)
        con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "RETRANSLATED", "version": rec["version"], "model": rec["model"], "hard_fail": rec["structural"]["hard_fail"]}
    except Exception as e:
        con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "RETRANSLATE_FAILED", "error": str(e)[:200]}

def _restore_urls(en_text: str, base_fa: str, edited_fa: str) -> str:
    """Model edits sometimes drop long URLs / citation tails. Splice back any URL present in the base
    (reviewed) translation but missing from the edit: replace the whole matching edited line with the
    full base line (not just a prefix splice, which duplicated the edit's own tail — see audit 2026-09-19)."""
    urls = [u.rstrip(".,);`") for u in set(re.findall(r"https?://\S+|10\.\d{4,9}/\S+", en_text))]
    lines = edited_fa.splitlines()
    for u in urls:
        if not u or u in "\n".join(lines) or u not in base_fa:
            continue
        bl = next((l for l in base_fa.splitlines() if u in l), None)
        if not bl:
            continue
        words = bl.split()
        for k in range(len(words), 9, -1):
            pref = " ".join(words[:k])
            if len(pref) < 30:
                continue
            idx = next((i for i, el in enumerate(lines) if pref in el), None)
            if idx is not None:
                lines[idx] = bl
                break
    return "\n".join(lines)

def apply_review(chunk_id: str, tr: dict, rv: dict, label: str, con, n_prev: int = 0) -> dict:
    """Apply a REVIEW_MAX verdict produced by any reviewer (Antigravity, API, or in-session subagent). Synchronous retranslation."""
    chunk = load_chunk(chunk_id)
    save_review(chunk_id, tr["version"], "REVIEW_MAX", label, rv, con)
    verdict = rv.get("verdict"); s = rv.get("scores") or {}; critical = bool(rv.get("critical_defects"))
    if verdict == "PASS" and scores_ok(s) and not critical:
        approve(chunk_id, tr["version"], tr["fa_text"], tr["model"], label, False, con)
        return {"action": "PASS", "scores": s}
    fb_extra = ""
    if verdict in ("PASS", "PASS_WITH_EDIT") and (rv.get("edited_fa") or "").strip():
        fa = _restore_urls(chunk["en_text"], tr["fa_text"], rv["edited_fa"].strip())
        st = prompts.structural_check(chunk["en_text"], fa)
        if not st["hard_fail"]:
            approve(chunk_id, tr["version"], fa, tr["model"], label, False, con)
            return {"action": "EDITED", "scores": s, "soft_issues": [i["type"] for i in st["issues"]]}
        fb_extra = "Reviewer's edited text failed the structural check: " + json.dumps(st["issues"], ensure_ascii=False)[:800] + "\n"
    if verdict == "ESCALATE":
        con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "ESCALATE", "reason": (rv.get("escalation_reason") or "")[:200]}
    if n_prev >= 3:
        con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "ESCALATE", "reason": "retranslate limit reached"}
    fb = fb_extra + (rv.get("retranslate_instructions") or "") + "\nIssues: " + json.dumps(rv.get("issues"), ensure_ascii=False)[:2000]
    if rv.get("critical_defects"):
        fb += "\nCritical defects: " + json.dumps(rv["critical_defects"], ensure_ascii=False)[:1000]
    return _retranslate_now(chunk_id, fb, con)

def apply_escalation(chunk_id: str, tr: dict, rv: dict, label: str, con) -> dict:
    chunk = load_chunk(chunk_id)
    fa = _restore_urls(chunk["en_text"], tr["fa_text"], (rv.get("final_fa") or "").strip())
    rv.setdefault("verdict", "ESCALATION_UNRESOLVED" if rv.get("unresolved") else "ESCALATION_RESOLVED")
    save_review(chunk_id, tr["version"], "REVIEW_ULTRA", label, rv, con)
    if rv.get("unresolved"):
        db.event("human_decision_required", {"chunk_id": chunk_id, "unresolved": rv["unresolved"]}, con)
        return {"action": "HUMAN", "unresolved": rv["unresolved"][:200]}   # stays RECONCILING (not re-claimable)
    st = prompts.structural_check(chunk["en_text"], fa) if fa else {"hard_fail": True, "issues": [{"type": "empty_final_fa"}]}
    if st["hard_fail"]:
        return _retranslate_now(chunk_id, "Escalation output failed structural check: " + json.dumps(st["issues"], ensure_ascii=False)[:800] + "\nResolution: " + (rv.get("resolution") or "")[:1500], con)
    approve(chunk_id, tr["version"], fa, tr["model"], label, True, con)
    for gd in rv.get("glossary_decisions") or []:
        db.event("glossary_decision_opus", {"chunk_id": chunk_id, **gd}, con)
    return {"action": "RESOLVED", "resolution": (rv.get("resolution") or "")[:200]}

def review_chunk(chunk_id: str, con=None) -> dict:
    """Run Sonnet REVIEW_MAX on the current translation. Returns {'action': PASS|EDITED|RETRANSLATE|ESCALATE, ...}."""
    con = con or db.connect()
    chunk = load_chunk(chunk_id)
    tr = current_translation(chunk_id, con)
    structural = json.loads(tr.get("structural_check") or "{}"); lint = json.loads(tr.get("term_lint") or "{}")
    prompt = prompts.build_review_prompt(chunk, tr["fa_text"], structural, lint)
    job = db.new_job("review", [chunk_id], tr["sha256"], SONNET, "antigravity", "REVIEW_MAX", prompts.REVIEW_PROMPT_VERSION, con)
    con.execute("UPDATE chunks SET status='REVIEWING', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    try:
        r = providers.claude_agy(SONNET, prompt, purpose="review", schema=prompts.REVIEW_SCHEMA, timeout=RCFG["primary_reviewer"]["timeout_s"], job_id=job)
    except Exception as e:
        db.job_status(job, "FAILED", con, error=str(e)[:300])
        con.execute("UPDATE chunks SET status='TRANSLATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        raise
    rv = r["json"] or {}
    if not rv.get("verdict"):
        db.job_status(job, "FAILED", con, error="unparsed review")
        con.execute("UPDATE chunks SET status='TRANSLATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        raise RuntimeError("unparsed review output")
    save_review(chunk_id, tr["version"], "REVIEW_MAX", SONNET, rv, con, r["latency_s"], r["usage"])
    db.job_status(job, "COMPLETED", con, usage_json=r["usage"])
    verdict = rv["verdict"]; s = rv.get("scores") or {}
    critical = bool(rv.get("critical_defects"))
    if verdict == "PASS" and scores_ok(s) and not critical:
        approve(chunk_id, tr["version"], tr["fa_text"], tr["model"], SONNET, False, con)
        return {"action": "PASS", "scores": s}
    if verdict in ("PASS", "PASS_WITH_EDIT") and rv.get("edited_fa", "").strip():
        fa = rv["edited_fa"].strip()
        st = prompts.structural_check(chunk["en_text"], fa)
        if st["hard_fail"]:
            con.execute("UPDATE chunks SET status='TRANSLATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
            return {"action": "RETRANSLATE", "feedback": "Reviewer edit failed structural check: " + json.dumps(st["issues"], ensure_ascii=False)[:800] + "\nReviewer issues: " + json.dumps(rv.get("issues"), ensure_ascii=False)[:1500]}
        approve(chunk_id, tr["version"], fa, tr["model"], SONNET, False, con)
        return {"action": "EDITED", "scores": s}
    if verdict == "ESCALATE":
        con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "ESCALATE", "review": rv}
    # PASS but critical defects listed without a repair → contradictory; retranslate with the defects as feedback (Opus is escalation-only)
    # RETRANSLATE (or PASS with failing scores and no edit)
    con.execute("UPDATE chunks SET status='TRANSLATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    fb = rv.get("retranslate_instructions") or ""
    fb += "\nIssues: " + json.dumps(rv.get("issues"), ensure_ascii=False)[:2000]
    if rv.get("critical_defects"):
        fb += "\nCritical defects: " + json.dumps(rv["critical_defects"], ensure_ascii=False)[:1000]
    return {"action": "RETRANSLATE", "feedback": fb, "scores": s}

def escalate_chunk(chunk_id: str, con=None) -> dict:
    con = con or db.connect()
    chunk = load_chunk(chunk_id)
    tr = current_translation(chunk_id, con)
    last = con.execute("SELECT * FROM reviews WHERE chunk_id=? AND version=? AND mode='REVIEW_MAX'", (chunk_id, tr["version"])).fetchone()
    sonnet_rv = {}
    if last:
        sonnet_rv = {"verdict": last["verdict"], "scores": {k: last[k] for k in ("fidelity", "completeness", "terminology", "fluency", "structure")},
                     "escalation_reason": last["notes"], **json.loads(last["issues_json"] or "{}")}
    prompt = prompts.build_escalation_prompt(chunk, tr["fa_text"], sonnet_rv)
    job = db.new_job("escalate", [chunk_id], tr["sha256"], OPUS, "antigravity", "REVIEW_ULTRA", prompts.REVIEW_PROMPT_VERSION, con)
    con.execute("UPDATE chunks SET status='RECONCILING', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    try:
        r = providers.claude_agy(OPUS, prompt, purpose="escalation", schema=prompts.ESCALATION_SCHEMA, timeout=RCFG["escalation_reviewer"]["timeout_s"], job_id=job)
    except Exception as e:
        db.job_status(job, "FAILED", con, error=str(e)[:300])
        con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        raise
    rv = r["json"] or {}
    fa = _restore_urls(chunk["en_text"], tr["fa_text"], (rv.get("final_fa") or "").strip())
    rv.setdefault("verdict", "ESCALATION_UNRESOLVED" if rv.get("unresolved") else "ESCALATION_RESOLVED")
    save_review(chunk_id, tr["version"], "REVIEW_ULTRA", OPUS, rv, con, r["latency_s"], r["usage"])
    db.job_status(job, "COMPLETED", con, usage_json=r["usage"])
    if rv.get("unresolved"):
        con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        db.event("human_decision_required", {"chunk_id": chunk_id, "unresolved": rv["unresolved"]}, con)
        return {"action": "HUMAN", "unresolved": rv["unresolved"]}
    st = prompts.structural_check(chunk["en_text"], fa) if fa else {"hard_fail": True, "issues": ["empty final_fa"]}
    if st["hard_fail"]:
        con.execute("UPDATE chunks SET status='TRANSLATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return {"action": "RETRANSLATE", "feedback": "Escalation output failed structural check: " + json.dumps(st["issues"], ensure_ascii=False)[:800] + "\nResolution: " + rv.get("resolution", "")[:1500]}
    approve(chunk_id, tr["version"], fa, tr["model"], OPUS, True, con)
    for gd in rv.get("glossary_decisions") or []:
        db.event("glossary_decision_opus", {"chunk_id": chunk_id, **gd}, con)
    return {"action": "RESOLVED", "resolution": rv.get("resolution")}

if __name__ == "__main__":
    print(json.dumps(review_chunk(sys.argv[1]), ensure_ascii=False, indent=1)[:3000])
