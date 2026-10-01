#!/usr/bin/env python3
"""Review queue CLI for in-session Claude subagents (owner-authorized 2026-09-17: Sonnet = primary reviewer, Opus = escalation).

  claim  [--mode primary|escalation] [--worker NAME]   → atomically claims one chunk, prints the full review prompt + JSON schema
  submit CHUNK_ID VERDICT_JSON_FILE [--mode …] [--worker NAME] → applies the verdict exactly like the pipeline (approve / edit / retranslate / escalate)
  status                                              → queue counts

Retranslation (RETRANSLATE verdict) is executed synchronously with the GLM/Gemini chain and the chunk goes back to the queue.
After 3 RETRANSLATE rounds a chunk escalates automatically. Every approval is checkpointed and enters the Translation Memory."""
from __future__ import annotations
import argparse, json, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, prompts, review, translate  # noqa: E402

SONNET_LABEL = "claude-code-subagent/sonnet"
OPUS_LABEL = "claude-code-subagent/opus"

def _requeue_stale(con, minutes=45):
    """Claims abandoned by a dead agent go back to the queue."""
    import time as _t
    for r in con.execute("SELECT chunk_id, status, updated_at FROM chunks WHERE status IN ('REVIEWING','RECONCILING')").fetchall():
        try:
            ts = _t.mktime(_t.strptime(r["updated_at"][:19], "%Y-%m-%dT%H:%M:%S"))
        except Exception:
            continue
        if _t.time() - ts > minutes * 60 and db.kv_get(f"claim:{r['chunk_id']}"):
            con.execute("UPDATE chunks SET status=?, updated_at=? WHERE chunk_id=?", ("REVIEW_QUEUED" if r["status"] == "REVIEWING" else "ESCALATED", db.now(), r["chunk_id"]))

def claim(mode: str, worker: str):
    con = db.connect()
    _requeue_stale(con)
    con.execute("BEGIN IMMEDIATE")
    if mode == "primary":
        row = con.execute("SELECT chunk_id FROM chunks WHERE status='REVIEW_QUEUED' ORDER BY priority, doc_key, order_no LIMIT 1").fetchone()
        nxt = "REVIEWING"
    else:
        row = con.execute("SELECT chunk_id FROM chunks WHERE status='ESCALATED' ORDER BY priority, doc_key, order_no LIMIT 1").fetchone()
        nxt = "RECONCILING"
    if not row:
        con.execute("COMMIT"); print(json.dumps({"claimed": None, "message": "queue empty"})); return
    cid = row["chunk_id"]
    con.execute("UPDATE chunks SET status=?, updated_at=? WHERE chunk_id=?", (nxt, db.now(), cid))
    db.kv_set(f"claim:{cid}", {"worker": worker, "mode": mode, "at": db.now()}, con)
    con.execute("COMMIT")
    chunk = translate.load_chunk(cid)
    tr = review.current_translation(cid, con)
    structural = json.loads(tr.get("structural_check") or "{}"); lint = json.loads(tr.get("term_lint") or "{}")
    if mode == "primary":
        prompt = prompts.build_review_prompt(chunk, tr["fa_text"], structural, lint); schema = prompts.REVIEW_SCHEMA
    else:
        last = con.execute("SELECT * FROM reviews WHERE chunk_id=? AND version=? AND mode='REVIEW_MAX'", (cid, tr["version"])).fetchone()
        sonnet_rv = {}
        if last:
            sonnet_rv = {"verdict": last["verdict"], "scores": {k: last[k] for k in ("fidelity", "completeness", "terminology", "fluency", "structure")}, "escalation_reason": last["notes"], **json.loads(last["issues_json"] or "{}")}
        prompt = prompts.build_escalation_prompt(chunk, tr["fa_text"], sonnet_rv); schema = prompts.ESCALATION_SCHEMA
    n_prev = con.execute("SELECT COUNT(*) FROM reviews WHERE chunk_id=? AND mode='REVIEW_MAX'", (cid,)).fetchone()[0]
    safe = cid.replace("::", "__")
    pdir = ROOT / "work" / "review_prompts"; pdir.mkdir(exist_ok=True)
    ppath = pdir / f"{safe}.v{tr['version']}.{mode}.md"
    ppath.write_text("<!-- JSON SCHEMA FOR YOUR VERDICT -->\n" + json.dumps(schema, ensure_ascii=False) + "\n\n<!-- PROMPT -->\n" + prompt, encoding="utf-8")
    print(json.dumps({"claimed": cid, "version": tr["version"], "mode": mode, "previous_reviews": n_prev, "doc_key": chunk["doc_key"], "words_en": chunk["word_count"], "flags": chunk["flags"],
                      "prompt_file": str(ppath), "verdict_file_suggestion": str(ROOT / "work" / "review_prompts" / f"{safe}.v{tr['version']}.verdict.json")}, ensure_ascii=False))

def submit(cid: str, path: str, mode: str, worker: str):
    con = db.connect()
    rv = json.load(open(path, encoding="utf-8"))
    tr = review.current_translation(cid, con)
    label = SONNET_LABEL if mode == "primary" else OPUS_LABEL
    if mode == "primary":
        if rv.get("verdict") not in ("PASS", "PASS_WITH_EDIT", "RETRANSLATE", "ESCALATE"):
            raise SystemExit("verdict must be PASS | PASS_WITH_EDIT | RETRANSLATE | ESCALATE")
        rv.setdefault("scores", {}); rv.setdefault("critical_defects", []); rv.setdefault("issues", []); rv.setdefault("edited_fa", "")
        n_prev = con.execute("SELECT COUNT(*) FROM reviews WHERE chunk_id=? AND mode='REVIEW_MAX'", (cid,)).fetchone()[0]
        result = review.apply_review(cid, tr, rv, label, con, n_prev)
    else:
        result = review.apply_escalation(cid, tr, rv, label, con)
    db.event("subagent_review_submitted", {"chunk_id": cid, "mode": mode, "worker": worker, "result": result.get("action")}, con)
    print(json.dumps(result, ensure_ascii=False)[:600])

def status():
    con = db.connect()
    print(json.dumps({r["status"]: r["n"] for r in con.execute("SELECT status, COUNT(*) n FROM chunks GROUP BY status")}))

if __name__ == "__main__":
    ap = argparse.ArgumentParser(); ap.add_argument("cmd", choices=["claim", "submit", "status"]); ap.add_argument("args", nargs="*")
    ap.add_argument("--mode", default="primary", choices=["primary", "escalation"]); ap.add_argument("--worker", default="agent")
    a = ap.parse_args()
    if a.cmd == "claim": claim(a.mode, a.worker)
    elif a.cmd == "submit": submit(a.args[0], a.args[1], a.mode, a.worker)
    else: status()
