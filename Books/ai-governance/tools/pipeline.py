#!/usr/bin/env python3
"""Resumable production pipeline runner (MasterPrompt §57–§59).

  python tools/pipeline.py run  [--translate-workers 8] [--review-workers 6] [--escalation-workers 2] [--limit N] [--doc KEY]
  python tools/pipeline.py status
  python tools/pipeline.py reset-inflight      # after a crash: TRANSLATING→PENDING, REVIEWING→TRANSLATED, RECONCILING→ESCALATED

Only GLM/Gemini (translation) and Claude via Antigravity (review/escalation) are called. Fable is never invoked here.
State lives in state/project.db; every approval is checkpointed immediately (work/checkpoints/, translation_memory/fa/).
"""
from __future__ import annotations
import argparse, json, sys, threading, time, traceback
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, translate, review  # noqa: E402

MAX_RETRANSLATE = 2
LOG = ROOT / "work" / "logs" / "pipeline.log"
_lock = threading.Lock()
REVIEW_PAUSED = threading.Event()   # set when the Claude reviewer quota is exhausted → chunks wait in REVIEW_QUEUED
NO_REVIEW = False                    # --no-review: translation-only run

def log(msg: str):
    line = f"{db.now()} {msg}"
    with _lock:
        print(line, flush=True)
        with open(LOG, "a", encoding="utf-8") as f:
            f.write(line + "\n")

def reset_inflight(doc: str | None = None):
    con = db.connect()
    w, args = (" AND doc_key=?", (doc,)) if doc else ("", ())
    n1 = con.execute("UPDATE chunks SET status='PENDING' WHERE status='TRANSLATING'" + w, args).rowcount
    # chunks claimed by in-session review agents (kv claim:<id>) are left alone
    n2 = con.execute("UPDATE chunks SET status='TRANSLATED' WHERE status='REVIEWING'" + w + " AND chunk_id NOT IN (SELECT substr(key,7) FROM kv WHERE key LIKE 'claim:%')", args).rowcount
    n3 = con.execute("UPDATE chunks SET status='ESCALATED' WHERE status='RECONCILING'" + w + " AND chunk_id NOT IN (SELECT substr(key,7) FROM kv WHERE key LIKE 'claim:%')", args).rowcount
    con.execute("UPDATE jobs SET status='ABANDONED' WHERE status='RUNNING'")
    log(f"reset in-flight: {n1} translating→pending, {n2} reviewing→translated, {n3} reconciling→escalated")

def status(print_it=True) -> dict:
    con = db.connect()
    rows = con.execute("SELECT status, COUNT(*) n, SUM(word_count) w FROM chunks GROUP BY status").fetchall()
    st = {r["status"]: {"chunks": r["n"], "words": r["w"]} for r in rows}
    total = sum(v["chunks"] for v in st.values())
    esc = con.execute("SELECT COUNT(*) FROM approved WHERE escalated=1").fetchone()[0]
    usage = con.execute("SELECT provider, model, COUNT(*) n, SUM(input_tokens) i, SUM(output_tokens) o FROM model_usage GROUP BY provider, model").fetchall()
    out = {"total_chunks": total, "by_status": st, "escalated_approved": esc,
           "model_usage": [{"provider": u["provider"], "model": u["model"], "calls": u["n"], "in": u["i"], "out": u["o"]} for u in usage]}
    if print_it:
        print(json.dumps(out, ensure_ascii=False, indent=1))
    return out

def process_chunk(chunk_id: str, feedback: str | None, attempt: int, review_pool: ThreadPoolExecutor, esc_pool: ThreadPoolExecutor, futures: list):
    con = db.connect()
    try:
        rec = translate.translate_chunk(chunk_id, feedback=feedback, con=con)
        st = rec["structural"]
        if st["hard_fail"] and attempt < MAX_RETRANSLATE:
            fb = "Automated structural check failed: " + json.dumps(st["issues"], ensure_ascii=False)[:1200]
            log(f"[T] {chunk_id} v{rec['version']} {rec['model']} structural HARD FAIL → retranslate (attempt {attempt+1}): {[i['type'] for i in st['issues']]}")
            futures.append(review_pool.submit(process_chunk, chunk_id, fb, attempt + 1, review_pool, esc_pool, futures))  # reuse pool slot
            return
        log(f"[T] {chunk_id} v{rec['version']} {rec['model']} {rec.get('latency_s') or 0:.0f}s ratio={st.get('length_ratio')} issues={len(st['issues'])} lint_missing={len(rec['lint'].get('missing', []))}")
        con.execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        if NO_REVIEW or REVIEW_PAUSED.is_set():
            return
        futures.append(review_pool.submit(review_stage, chunk_id, attempt, review_pool, esc_pool, futures))
    except Exception as e:
        log(f"[T] {chunk_id} FAILED: {str(e)[:200]}")
        con.execute("UPDATE chunks SET status='FAILED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))

def review_stage(chunk_id: str, attempt: int, review_pool, esc_pool, futures):
    if NO_REVIEW or REVIEW_PAUSED.is_set():
        db.connect().execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        return
    try:
        r = review.review_chunk(chunk_id)
        a = r["action"]
        if a in ("PASS", "EDITED"):
            log(f"[R] {chunk_id} {a} scores={r.get('scores')}")
        elif a == "ESCALATE":
            log(f"[R] {chunk_id} ESCALATE → Opus")
            futures.append(esc_pool.submit(escalation_stage, chunk_id, attempt, review_pool, esc_pool, futures))
        elif a == "RETRANSLATE":
            if attempt < MAX_RETRANSLATE:
                log(f"[R] {chunk_id} RETRANSLATE (attempt {attempt+1}) scores={r.get('scores')}")
                futures.append(review_pool.submit(process_chunk, chunk_id, r["feedback"], attempt + 1, review_pool, esc_pool, futures))
            else:
                log(f"[R] {chunk_id} RETRANSLATE limit reached → escalate to Opus")
                db.connect().execute("UPDATE chunks SET status='ESCALATED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
                futures.append(esc_pool.submit(escalation_stage, chunk_id, attempt, review_pool, esc_pool, futures))
    except Exception as e:
        import providers
        if isinstance(e, providers.QuotaExhausted):
            if not REVIEW_PAUSED.is_set():
                REVIEW_PAUSED.set()
                log(f"[R] REVIEWER QUOTA EXHAUSTED — pausing all reviews; chunks stay REVIEW_QUEUED. Resume later with: pipeline.py run --review-workers 6  ({str(e)[:160]})")
            db.connect().execute("UPDATE chunks SET status='REVIEW_QUEUED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
            return
        log(f"[R] {chunk_id} review error: {str(e)[:200]}")

def escalation_stage(chunk_id: str, attempt: int, review_pool, esc_pool, futures):
    if REVIEW_PAUSED.is_set():
        return
    try:
        r = review.escalate_chunk(chunk_id)
        log(f"[E] {chunk_id} {r['action']} {str(r.get('resolution') or r.get('unresolved') or '')[:120]}")
        if r["action"] == "RETRANSLATE" and attempt < MAX_RETRANSLATE + 1:
            futures.append(review_pool.submit(process_chunk, chunk_id, r["feedback"], attempt + 1, review_pool, esc_pool, futures))
    except Exception as e:
        log(f"[E] {chunk_id} escalation error: {str(e)[:200]}")

def run(tw: int, rw: int, ew: int, limit: int | None, doc: str | None):
    reset_inflight(doc)
    con = db.connect()
    q = "SELECT chunk_id, status FROM chunks WHERE status IN ('PENDING','TRANSLATED','REVIEW_QUEUED','ESCALATED','FAILED')"
    args = []
    if doc:
        q += " AND doc_key=?"; args.append(doc)
    q += " ORDER BY priority, doc_key, order_no"
    rows = con.execute(q, args).fetchall()
    if limit:
        rows = rows[:limit]
    log(f"pipeline start: {len(rows)} chunks to process (translate={tw}, review={rw}, escalation={ew})")
    futures: list = []
    with ThreadPoolExecutor(max_workers=tw, thread_name_prefix="T") as tpool, ThreadPoolExecutor(max_workers=rw, thread_name_prefix="R") as rpool, ThreadPoolExecutor(max_workers=ew, thread_name_prefix="E") as epool:
        for r in rows:
            cid, st = r["chunk_id"], r["status"]
            if st in ("PENDING", "FAILED"):
                futures.append(tpool.submit(process_chunk, cid, None, 0, rpool, epool, futures))
            elif st in ("TRANSLATED", "REVIEW_QUEUED"):
                futures.append(rpool.submit(review_stage, cid, 0, rpool, epool, futures))
            elif st == "ESCALATED":
                futures.append(epool.submit(escalation_stage, cid, 0, rpool, epool, futures))
        last = time.time()
        while True:
            pending = [f for f in futures if not f.done()]
            if not pending:
                break
            if time.time() - last > 60:
                s = status(print_it=False)["by_status"]
                log("progress: " + ", ".join(f"{k}={v['chunks']}" for k, v in sorted(s.items())))
                last = time.time()
            time.sleep(5)
    s = status(print_it=False)
    log("pipeline end: " + json.dumps(s["by_status"], ensure_ascii=False))

if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("cmd", choices=["run", "status", "reset-inflight"])
    ap.add_argument("--translate-workers", type=int, default=8); ap.add_argument("--review-workers", type=int, default=6); ap.add_argument("--escalation-workers", type=int, default=2)
    ap.add_argument("--limit", type=int); ap.add_argument("--doc"); ap.add_argument("--no-review", action="store_true", help="translation-only (reviewer unavailable)")
    a = ap.parse_args()
    if a.cmd == "run":
        NO_REVIEW = a.no_review
        run(a.translate_workers, a.review_workers, a.escalation_workers, a.limit, a.doc)
    elif a.cmd == "status":
        status()
    else:
        reset_inflight()
