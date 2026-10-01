#!/usr/bin/env python3
"""Non-Claude REVIEW_MAX runner (GLM / Gemini) — OWNER OVERRIDE ONLY — plus an offline benchmark against Claude verdicts.

  bench  : replay Claude-reviewed chunks through alternative reviewers; writes qa/review_model_benchmark.{json,md}. Touches no production table.
  run    : review REVIEW_QUEUED chunks with one alternative model. Refused unless config/model-policy.yaml OWNER_OVERRIDE_REVIEW.enabled is true.
"""
from __future__ import annotations
import argparse, concurrent.futures as cf, difflib, json, re, sys, threading, time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, policy, prompts, providers, review  # noqa: E402
from translate import load_chunk  # noqa: E402

MODELS = {"glm-5.3": ("zai-coding-plan", "glm-5.3"), "glm-5.3-flash": ("zai-coding-plan", "glm-5.3-flash"),
          "gemini-3.1-pro-preview": ("google", "gemini-3.1-pro-preview"), "gemini-3.8-flash": ("google", "gemini-3.8-flash")}
CJK = re.compile(r"[一-鿿぀-ヿ가-힯]")
SYSTEM = ("You are a strict bilingual English→Persian quality reviewer for NIST publications. "
          "Output ONLY one JSON object that matches this JSON Schema — no prose, no Markdown fences:\n" + json.dumps(prompts.REVIEW_SCHEMA, ensure_ascii=False))
BENCH_DIR = ROOT / "work" / "benchmarks" / "review_alt"

def parse_json(text: str) -> dict | None:
    t = text.strip()
    for cand in (t, re.sub(r"^```(?:json)?\s*|\s*```$", "", t, flags=re.S)):
        try:
            d = json.loads(cand)
            if isinstance(d, dict): return d
        except Exception: pass
    i, j = t.find("{"), t.rfind("}")
    if 0 <= i < j:
        try:
            d = json.loads(t[i:j + 1])
            if isinstance(d, dict): return d
        except Exception: pass
    return None

def _system(schema: dict) -> str:
    return ("You are a strict bilingual English→Persian quality reviewer for NIST publications. "
            "Output ONLY one JSON object that matches this JSON Schema — no prose, no Markdown fences:\n" + json.dumps(schema, ensure_ascii=False))

def call_alt(name: str, prompt: str, purpose: str, job_id=None, schema: dict | None = None) -> dict:
    prov, model = MODELS[name]; schema = schema or prompts.REVIEW_SCHEMA; system = _system(schema)
    if prov == "zai-coding-plan":
        r = providers.glm_chat(model, system, prompt, purpose=purpose, max_tokens=16000, timeout=900, json_mode=True, job_id=job_id, temperature=0.1)
    else:
        try:
            r = providers.gemini_generate(model, system, prompt, purpose=purpose, max_tokens=32000, json_schema=schema, job_id=job_id, temperature=0.1)
        except providers.ProviderError as e:
            if "schema" in str(e).lower() or "400" in str(e):
                r = providers.gemini_generate(model, system, prompt, purpose=purpose, max_tokens=32000, job_id=job_id, temperature=0.1)
            else:
                raise
    r["json"] = parse_json(r["text"])
    return r

# ----------------------------------------------------------------------------- bench
def _bench_one(it: dict) -> dict:
    cid, m, chunk, tr, a, cr, prompt = (it[k] for k in ("cid", "model", "chunk", "tr", "a", "cr", "prompt"))
    doc, ch = cid.split("::")
    try:
        r = call_alt(m, prompt, "benchmark")
    except Exception as e:
        return {"chunk_id": cid, "model": m, "error": str(e)[:300]}
    rv = r["json"] or {}
    edited = (rv.get("edited_fa") or "").strip()
    fa = edited or tr["fa_text"]
    st = prompts.structural_check(chunk["en_text"], fa)
    s = rv.get("scores") or {}; cs = {k: cr.get(k) for k in ("fidelity", "completeness", "terminology", "fluency", "structure")}
    deltas = [abs((s.get(k) or 0) - (cs.get(k) or 0)) for k in cs if s.get(k) is not None and cs.get(k) is not None]
    rec = {"chunk_id": cid, "model": m, "parsed": bool(rv.get("verdict")), "verdict": rv.get("verdict"), "claude_verdict": cr.get("verdict"),
           "verdict_match": rv.get("verdict") == cr.get("verdict"), "scores": s, "claude_scores": cs,
           "mean_abs_score_delta": round(sum(deltas) / len(deltas), 1) if deltas else None,
           "n_issues": len(rv.get("issues") or []), "n_critical": len(rv.get("critical_defects") or []),
           "claude_n_issues": len((json.loads(cr.get("issues_json") or "{}") or {}).get("issues") or []),
           "has_edit": bool(edited), "edit_hard_fail": bool(edited and st["hard_fail"]), "edit_soft_issues": [i["type"] for i in st["issues"]] if edited else [],
           "cjk": bool(CJK.search(fa)), "len_ratio_vs_orig": round(len(fa) / max(1, len(tr["fa_text"])), 3),
           "sim_alt_vs_claude_final": round(difflib.SequenceMatcher(None, fa, a["fa_text"]).ratio(), 4),
           "sim_orig_vs_claude_final": round(difflib.SequenceMatcher(None, tr["fa_text"], a["fa_text"]).ratio(), 4),
           "latency_s": round(r["latency_s"], 1), "usage": r["usage"]}
    out = BENCH_DIR / m; out.mkdir(parents=True, exist_ok=True)
    (out / f"{doc}__{ch}.json").write_text(json.dumps({"rec": rec, "review": rv, "raw_head": r["text"][:400]}, ensure_ascii=False, indent=1), encoding="utf-8")
    return rec

def bench(chunk_ids: list[str], models: list[str], workers: int):
    con = db.connect()
    items = []
    for cid in chunk_ids:
        a = con.execute("SELECT * FROM approved WHERE chunk_id=?", (cid,)).fetchone()
        if not a: print("skip (not approved):", cid); continue
        tr = con.execute("SELECT * FROM translations WHERE chunk_id=? AND version=?", (cid, a["version"])).fetchone()
        cr = con.execute("SELECT * FROM reviews WHERE chunk_id=? AND version=? AND mode='REVIEW_MAX'", (cid, a["version"])).fetchone()
        chunk = load_chunk(cid)
        prompt = prompts.build_review_prompt(chunk, tr["fa_text"], json.loads(tr["structural_check"] or "{}"), json.loads(tr["term_lint"] or "{}"))
        for m in models:
            items.append({"cid": cid, "model": m, "chunk": chunk, "tr": dict(tr), "a": dict(a), "cr": dict(cr or {}), "prompt": prompt})
    print(f"bench: {len(chunk_ids)} chunks × {len(models)} models = {len(items)} calls, workers={workers}", flush=True)
    recs = []
    with cf.ThreadPoolExecutor(max_workers=workers) as ex:
        for rec in ex.map(_bench_one, items):
            recs.append(rec); print(json.dumps({k: rec.get(k) for k in ("chunk_id", "model", "verdict", "claude_verdict", "mean_abs_score_delta", "has_edit", "edit_hard_fail", "cjk", "sim_alt_vs_claude_final", "sim_orig_vs_claude_final", "latency_s", "error")}, ensure_ascii=False), flush=True)
    (ROOT / "qa" / "review_model_benchmark.json").write_text(json.dumps({"generated_at": db.now(), "chunks": chunk_ids, "models": models, "results": recs}, ensure_ascii=False, indent=1), encoding="utf-8")
    # summary
    lines = ["# Review-model benchmark (alternative reviewers vs Claude Sonnet 4.6 REVIEW_MAX)", "",
             f"Generated {db.now()} · {len(chunk_ids)} chunks already approved after a Claude review · same REVIEW_MAX prompt/schema.", "",
             "Reference = the Claude verdict and the Claude-approved Persian text for the same translation version. "
             "`sim→Claude final` is the difflib similarity of the alternative reviewer's output text (edited_fa, or the original when no edit) to the Claude-approved text; "
             "`baseline` is the similarity of the *unedited* translation to that text (higher than baseline = the alt reviewer moved the text toward Claude's fixes).", "",
             "| model | calls | errors | parsed | verdict = Claude | mean abs score Δ | edits | edit hard-fail | CJK | sim→Claude final | baseline | mean latency s | mean out tokens |",
             "|---|---|---|---|---|---|---|---|---|---|---|---|---|"]
    for m in models:
        rs = [r for r in recs if r["model"] == m]; ok = [r for r in rs if not r.get("error")]
        def mean(xs): xs = [x for x in xs if x is not None]; return round(sum(xs) / len(xs), 3) if xs else None
        lines.append(f"| {m} | {len(rs)} | {len(rs)-len(ok)} | {sum(r['parsed'] for r in ok)} | {sum(r['verdict_match'] for r in ok)}/{len(ok)} | {mean([r['mean_abs_score_delta'] for r in ok])} | "
                     f"{sum(r['has_edit'] for r in ok)} | {sum(r['edit_hard_fail'] for r in ok)} | {sum(r['cjk'] for r in ok)} | {mean([r['sim_alt_vs_claude_final'] for r in ok])} | "
                     f"{mean([r['sim_orig_vs_claude_final'] for r in ok])} | {mean([r['latency_s'] for r in ok])} | {mean([(r['usage'] or {}).get('output_tokens') for r in ok])} |")
    lines += ["", "## Per chunk", "", "| chunk | model | verdict | Claude | scores | Claude scores | issues | critical | edit | hard-fail | sim→Claude | baseline |", "|---|---|---|---|---|---|---|---|---|---|---|---|"]
    for r in recs:
        if r.get("error"): lines.append(f"| {r['chunk_id']} | {r['model']} | ERROR {r['error'][:80]} | | | | | | | | | |"); continue
        sc = "/".join(str(r["scores"].get(k, "-")) for k in ("fidelity", "completeness", "terminology", "fluency", "structure"))
        cs = "/".join(str(r["claude_scores"].get(k, "-")) for k in ("fidelity", "completeness", "terminology", "fluency", "structure"))
        lines.append(f"| {r['chunk_id']} | {r['model']} | {r['verdict']} | {r['claude_verdict']} | {sc} | {cs} | {r['n_issues']} | {r['n_critical']} | {'yes' if r['has_edit'] else 'no'} | {'YES' if r['edit_hard_fail'] else 'no'} | {r['sim_alt_vs_claude_final']} | {r['sim_orig_vs_claude_final']} |")
    lines += ["", "Raw outputs: work/benchmarks/review_alt/<model>/<doc>__<chunk>.json (full JSON verdict incl. edited_fa).", ""]
    (ROOT / "qa" / "review_model_benchmark.md").write_text("\n".join(lines), encoding="utf-8")
    print("wrote qa/review_model_benchmark.md")

# ----------------------------------------------------------------------------- run (owner override)
_lock = threading.Lock()
def _claim(con, docs=None) -> str | None:
    with _lock:
        con.execute("BEGIN IMMEDIATE")
        q = "SELECT chunk_id FROM chunks WHERE status='REVIEW_QUEUED'" + (" AND doc_key IN (%s)" % ",".join("?" * len(docs)) if docs else "") + " ORDER BY priority, order_no LIMIT 1"
        r = con.execute(q, tuple(docs or ())).fetchone()
        if not r:
            con.execute("COMMIT"); return None
        cid = r["chunk_id"]
        con.execute("UPDATE chunks SET status='REVIEWING', updated_at=? WHERE chunk_id=?", (db.now(), cid))
        con.execute("INSERT OR REPLACE INTO kv(key,value,updated_at) VALUES(?,?,?)", (f"claim:{cid}", json.dumps({"worker": "review_alt", "ts": db.now()}), db.now()))
        con.execute("COMMIT")
        return cid

def _run_worker(name: str, label: str, docs, stop_at: float, counter: dict):
    con = db.connect()
    while time.time() < stop_at:
        cid = _claim(con, docs)
        if not cid: return
        try:
            chunk = load_chunk(cid); tr = review.current_translation(cid, con)
            n_prev = con.execute("SELECT COUNT(*) FROM reviews WHERE chunk_id=?", (cid,)).fetchone()[0]
            prompt = prompts.build_review_prompt(chunk, tr["fa_text"], json.loads(tr.get("structural_check") or "{}"), json.loads(tr.get("term_lint") or "{}"))
            job = db.new_job("review_alt", [cid], tr["sha256"], MODELS[name][1], MODELS[name][0], "REVIEW_MAX_ALT", prompts.REVIEW_PROMPT_VERSION, con)
            r = call_alt(name, prompt, "review_alt", job_id=job)
            rv = r["json"] or {}
            if not rv.get("verdict") or not isinstance(rv.get("scores"), dict):
                db.job_status(job, "FAILED", con, error="unparsed"); raise RuntimeError("unparsed review output")
            res = review.apply_review(cid, tr, rv, label, con, n_prev)
            db.job_status(job, "COMPLETED", con, usage_json=r["usage"])
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            db.event("alt_review_submitted", {"chunk_id": cid, "model": name, "result": res.get("action"), "scores": rv.get("scores")}, con)
            with _lock: counter[res.get("action", "?")] = counter.get(res.get("action", "?"), 0) + 1
            print(json.dumps({"chunk": cid, "action": res.get("action"), "scores": rv.get("scores"), "latency_s": round(r["latency_s"], 1)}, ensure_ascii=False), flush=True)
        except Exception as e:
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=? AND status='REVIEWING'", (db.now(), cid))
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            with _lock: counter["error"] = counter.get("error", 0) + 1
            print(json.dumps({"chunk": cid, "error": str(e)[:200]}), flush=True)
            time.sleep(5)

# ----------------------------------------------------------------------------- prereview (spec-compatible: Claude still reviews everything)
def _prereview_worker(name: str, docs, stop_at: float, counter: dict):
    import translate as _tr
    con = db.connect(); prov, model = MODELS[name]
    while time.time() < stop_at:
        cid = _claim(con, docs)
        if not cid: return
        try:
            chunk = load_chunk(cid); tr = review.current_translation(cid, con)
            prompt = prompts.build_review_prompt(chunk, tr["fa_text"], json.loads(tr.get("structural_check") or "{}"), json.loads(tr.get("term_lint") or "{}"))
            job = db.new_job("prereview", [cid], tr["sha256"], model, prov, "PREREVIEW", prompts.REVIEW_PROMPT_VERSION, con)
            r = call_alt(name, prompt, "prereview", job_id=job)
            rv = r["json"] or {}
            if not rv.get("verdict"):
                db.job_status(job, "FAILED", con, error="unparsed"); raise RuntimeError("unparsed prereview output")
            doc, ch = cid.split("::"); out = ROOT / "work" / "prereviews" / doc; out.mkdir(parents=True, exist_ok=True)
            (out / f"{ch}.v{tr['version']}.json").write_text(json.dumps({"chunk_id": cid, "version": tr["version"], "model": model, "provider": prov, "review": rv,
                                                                        "usage": r["usage"], "latency_s": r["latency_s"], "created_at": db.now()}, ensure_ascii=False, indent=1), encoding="utf-8")
            action = "KEPT"; edited = (rv.get("edited_fa") or "").strip()
            if rv.get("verdict") in ("PASS", "PASS_WITH_EDIT") and edited and edited != tr["fa_text"].strip():
                st = prompts.structural_check(chunk["en_text"], edited)
                if not st["hard_fail"] and not CJK.search(edited):
                    lint = prompts.terminology_lint(chunk["en_text"], edited)
                    _tr.save_translation(chunk, tr["version"] + 1, edited, f"{tr['model']}+{model}-prereview", prov, st, lint, con, usage=r["usage"], latency=r["latency_s"])
                    action = f"EDITED_v{tr['version'] + 1}"
                else:
                    action = "EDIT_REJECTED"
            elif rv.get("verdict") == "RETRANSLATE":
                fb = (rv.get("retranslate_instructions") or "") + "\nIssues: " + json.dumps(rv.get("issues"), ensure_ascii=False)[:2000]
                res = review._retranslate_now(cid, fb, con); action = f"{res.get('action')}_v{res.get('version')}"
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), cid))
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            db.job_status(job, "COMPLETED", con, usage_json=r["usage"])
            db.event("prereview_applied", {"chunk_id": cid, "model": model, "verdict": rv.get("verdict"), "action": action, "scores": rv.get("scores")}, con)
            with _lock: counter[action.split("_")[0]] = counter.get(action.split("_")[0], 0) + 1
            print(json.dumps({"chunk": cid, "verdict": rv.get("verdict"), "action": action, "scores": rv.get("scores"), "latency_s": round(r["latency_s"], 1)}, ensure_ascii=False), flush=True)
        except Exception as e:
            con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=? AND status='REVIEWING'", (db.now(), cid))
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            with _lock: counter["error"] = counter.get("error", 0) + 1
            print(json.dumps({"chunk": cid, "error": str(e)[:200]}), flush=True); time.sleep(5)

def prereview(name: str, workers: int, limit_minutes: float, docs=None):
    prov, model = MODELS[name]; policy.assert_allowed("prereview", prov, model)
    print(f"PREREVIEW model={name} workers={workers} (Claude REVIEW_MAX remains mandatory for every chunk)", flush=True)
    counter: dict = {}; stop_at = time.time() + limit_minutes * 60
    with cf.ThreadPoolExecutor(max_workers=workers) as ex:
        list(ex.map(lambda i: _prereview_worker(name, docs, stop_at, counter), range(workers)))
    print("DONE", json.dumps(counter))

# ----------------------------------------------------------------------------- escalation (owner override: GLM as REVIEW_ULTRA)
def _escalate_worker(name: str, label: str, stop_at: float, counter: dict):
    con = db.connect(); prov, model = MODELS[name]
    while time.time() < stop_at:
        with _lock:
            con.execute("BEGIN IMMEDIATE")
            r0 = con.execute("SELECT chunk_id FROM chunks WHERE status='ESCALATED' ORDER BY priority, order_no LIMIT 1").fetchone()
            if not r0:
                con.execute("COMMIT"); return
            cid = r0["chunk_id"]
            con.execute("UPDATE chunks SET status='RECONCILING', updated_at=? WHERE chunk_id=?", (db.now(), cid))
            con.execute("INSERT OR REPLACE INTO kv(key,value,updated_at) VALUES(?,?,?)", (f"claim:{cid}", json.dumps({"worker": "review_alt-escalation", "ts": db.now()}), db.now()))
            con.execute("COMMIT")
        try:
            chunk = load_chunk(cid); tr = review.current_translation(cid, con)
            last = con.execute("SELECT * FROM reviews WHERE chunk_id=? AND mode='REVIEW_MAX' ORDER BY created_at DESC LIMIT 1", (cid,)).fetchone()
            prev = {"verdict": last["verdict"], "scores": {k: last[k] for k in ("fidelity", "completeness", "terminology", "fluency", "structure")},
                    "escalation_reason": last["notes"], **json.loads(last["issues_json"] or "{}")} if last else {"verdict": "ESCALATE", "escalation_reason": "retranslate limit reached"}
            prompt = prompts.build_escalation_prompt(chunk, tr["fa_text"], prev)
            job = db.new_job("escalation_alt", [cid], tr["sha256"], model, prov, "REVIEW_ULTRA_ALT", prompts.REVIEW_PROMPT_VERSION, con)
            r = call_alt(name, prompt, "review_alt", job_id=job, schema=prompts.ESCALATION_SCHEMA)
            rv = r["json"] or {}
            if not (rv.get("final_fa") or "").strip():
                db.job_status(job, "FAILED", con, error="no final_fa"); raise RuntimeError("escalation output without final_fa")
            res = review.apply_escalation(cid, tr, rv, label, con)
            db.job_status(job, "COMPLETED", con, usage_json=r["usage"])
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            db.event("alt_escalation_submitted", {"chunk_id": cid, "model": name, "result": res.get("action")}, con)
            with _lock: counter[res.get("action", "?")] = counter.get(res.get("action", "?"), 0) + 1
            print(json.dumps({"chunk": cid, "action": res.get("action"), "latency_s": round(r["latency_s"], 1)}, ensure_ascii=False), flush=True)
        except Exception as e:
            con.execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=? AND status='RECONCILING'", (db.now(), cid))
            con.execute("DELETE FROM kv WHERE key=?", (f"claim:{cid}",)); con.commit()
            with _lock: counter["error"] = counter.get("error", 0) + 1
            print(json.dumps({"chunk": cid, "error": str(e)[:200]}), flush=True); time.sleep(5)

def escalate(name: str, workers: int, limit_minutes: float):
    prov, model = MODELS[name]; policy.assert_allowed("review_alt", prov, model)
    o = policy.POLICY["OWNER_OVERRIDE_REVIEW"]; label = f"{model} (OWNER_OVERRIDE review_alt/REVIEW_ULTRA, decided {o.get('decided_at') or '?'})"
    print(f"review_alt ESCALATE model={name} workers={workers}", flush=True)
    counter: dict = {}; stop_at = time.time() + limit_minutes * 60
    with cf.ThreadPoolExecutor(max_workers=workers) as ex:
        list(ex.map(lambda i: _escalate_worker(name, label, stop_at, counter), range(workers)))
    print("DONE", json.dumps(counter))

def run(name: str, workers: int, limit_minutes: float, docs=None):
    prov, model = MODELS[name]
    policy.assert_allowed("review_alt", prov, model)   # raises PolicyViolation unless OWNER_OVERRIDE_REVIEW.enabled
    o = policy.POLICY["OWNER_OVERRIDE_REVIEW"]
    label = f"{model} (OWNER_OVERRIDE review_alt, decided {o.get('decided_at') or '?'})"
    print(f"review_alt RUN model={name} workers={workers} label={label!r}", flush=True)
    counter: dict = {}; stop_at = time.time() + limit_minutes * 60
    with cf.ThreadPoolExecutor(max_workers=workers) as ex:
        list(ex.map(lambda i: _run_worker(name, label, docs, stop_at, counter), range(workers)))
    print("DONE", json.dumps(counter))

if __name__ == "__main__":
    ap = argparse.ArgumentParser(); sub = ap.add_subparsers(dest="cmd", required=True)
    b = sub.add_parser("bench"); b.add_argument("--ids", nargs="+", required=True); b.add_argument("--models", nargs="+", default=["glm-5.3", "gemini-3.1-pro-preview", "gemini-3.8-flash"]); b.add_argument("--workers", type=int, default=6)
    r = sub.add_parser("run"); r.add_argument("--model", required=True, choices=list(MODELS)); r.add_argument("--workers", type=int, default=4); r.add_argument("--minutes", type=float, default=600); r.add_argument("--docs", nargs="*")
    pr = sub.add_parser("prereview"); pr.add_argument("--model", default="glm-5.3", choices=list(MODELS)); pr.add_argument("--workers", type=int, default=4); pr.add_argument("--minutes", type=float, default=600); pr.add_argument("--docs", nargs="*")
    es = sub.add_parser("escalate"); es.add_argument("--model", default="glm-5.3", choices=list(MODELS)); es.add_argument("--workers", type=int, default=2); es.add_argument("--minutes", type=float, default=120)
    a = ap.parse_args()
    if a.cmd == "bench": bench(a.ids, a.models, a.workers)
    elif a.cmd == "escalate": escalate(a.model, a.workers, a.minutes)
    elif a.cmd == "prereview": prereview(a.model, a.workers, a.minutes, a.docs or None)
    else: run(a.model, a.workers, a.minutes, a.docs or None)
