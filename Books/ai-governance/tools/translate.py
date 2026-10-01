#!/usr/bin/env python3
"""First-pass translation worker (MasterPrompt §26) + TM reuse + automated structural check + terminology lint.
translate_chunk(chunk_id, model_pref=None, feedback=None) -> dict(version, fa_text, structural, lint, model)"""
from __future__ import annotations
import json, sys, time
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers, prompts, policy  # noqa: E402

CFG = yaml.safe_load(open(ROOT / "config" / "translation.yaml", encoding="utf-8"))

def model_chain() -> list[tuple[str, str]]:
    chain = []
    for key in ("PRIMARY_TRANSLATOR", "SECONDARY_TRANSLATOR", "FAST_FALLBACK_TRANSLATOR"):
        v = CFG.get(key)
        if v and "/" in v and "PENDING" not in v:
            p, m = v.split("/", 1); chain.append((p, m))
    return chain or [("zai-coding-plan", "glm-5.3"), ("google", "gemini-3.8-flash"), ("zai-coding-plan", "glm-5-turbo")]

def load_chunk(chunk_id: str) -> dict:
    doc, ch = chunk_id.split("::")
    return json.load(open(ROOT / "work" / "chunks" / doc / f"{ch}.json", encoding="utf-8"))

def tm_lookup(chunk: dict, con) -> dict | None:
    r = con.execute("SELECT * FROM tm WHERE source_hash=?", (chunk["sha256"],)).fetchone()
    return dict(r) if r else None

def save_translation(chunk: dict, version: int, fa: str, model: str, provider: str, structural: dict, lint: dict, con, usage=None, latency=None):
    doc, ch = chunk["chunk_id"].split("::")
    out = ROOT / "work" / "translations" / doc; out.mkdir(parents=True, exist_ok=True)
    rec = {"chunk_id": chunk["chunk_id"], "version": version, "model": model, "provider": provider, "prompt_version": prompts.TRANSLATE_PROMPT_VERSION,
           "fa_text": fa, "sha256": db.sha256_text(fa), "structural": structural, "lint": lint, "usage": usage, "latency_s": latency, "created_at": db.now()}
    (out / f"{ch}.v{version}.json").write_text(json.dumps(rec, ensure_ascii=False, indent=1), encoding="utf-8")
    con.execute("INSERT OR REPLACE INTO translations(chunk_id,version,model,provider,fa_text,sha256,structural_check,term_lint,created_at) VALUES(?,?,?,?,?,?,?,?,?)",
                (chunk["chunk_id"], version, model, provider, fa, rec["sha256"], json.dumps(structural, ensure_ascii=False), json.dumps(lint, ensure_ascii=False), rec["created_at"]))
    con.execute("UPDATE chunks SET current_version=?, status=?, updated_at=? WHERE chunk_id=?", (version, "TRANSLATED", db.now(), chunk["chunk_id"]))
    return rec

def translate_chunk(chunk_id: str, model_pref: tuple[str, str] | None = None, feedback: str | None = None, con=None) -> dict:
    con = con or db.connect()
    chunk = load_chunk(chunk_id)
    row = con.execute("SELECT current_version FROM chunks WHERE chunk_id=?", (chunk_id,)).fetchone()
    version = (row["current_version"] if row else 0) + 1
    # Translation Memory exact match (only for first attempt)
    if version == 1 and not feedback:
        tm = tm_lookup(chunk, con)
        if tm:
            structural = prompts.structural_check(chunk["en_text"], tm["fa_text"]); lint = prompts.terminology_lint(chunk["en_text"], tm["fa_text"])
            rec = save_translation(chunk, version, tm["fa_text"], "TM:" + (tm["translator_model"] or ""), "tm", structural, lint, con)
            db.event("tm_reuse", {"chunk_id": chunk_id}, con)
            return {**rec, "tm_reuse": True}
    system, user = prompts.build_translate_prompt(chunk, feedback)
    mt = CFG["generation"]["max_output_tokens"]
    if len(chunk["en_text"]) > 18000:  # very large chunks need a larger output budget or the model truncates
        mt = min(24000, mt + len(chunk["en_text"]) // 2)
    chain = [model_pref] if model_pref else model_chain()
    last_err = None
    for provider, model in chain:
        job = db.new_job("translate", [chunk_id], chunk["sha256"], model, provider, "n/a", prompts.TRANSLATE_PROMPT_VERSION, con)
        con.execute("UPDATE chunks SET status='TRANSLATING', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
        db.job_status(job, "RUNNING", con)
        try:
            r = providers.translate(provider, model, system, user, purpose="translation", max_tokens=mt, job_id=job)
            fa = r["text"].strip()
            # strip accidental code fences / preambles
            if fa.startswith("```"):
                fa = fa.strip("`").split("\n", 1)[1] if "\n" in fa else fa
                fa = fa.rsplit("```", 1)[0].strip()
            structural = prompts.structural_check(chunk["en_text"], fa)
            lint = prompts.terminology_lint(chunk["en_text"], fa)
            rec = save_translation(chunk, version, fa, model, provider, structural, lint, con, r.get("usage"), r.get("latency_s"))
            db.job_status(job, "COMPLETED", con, output_hash=rec["sha256"], usage_json=r.get("usage"))
            return rec
        except policy.PolicyViolation:
            raise
        except Exception as e:
            last_err = str(e)[:300]
            db.job_status(job, "FAILED", con, error=last_err)
            con.execute("UPDATE jobs SET retry_count=retry_count+1 WHERE job_id=?", (job,))
            time.sleep(2)
    con.execute("UPDATE chunks SET status='FAILED', updated_at=? WHERE chunk_id=?", (db.now(), chunk_id))
    raise RuntimeError(f"translation failed for {chunk_id}: {last_err}")

if __name__ == "__main__":
    cid = sys.argv[1]
    r = translate_chunk(cid)
    print(json.dumps({k: r[k] for k in ("chunk_id", "version", "model", "latency_s", "structural", "lint")}, ensure_ascii=False, indent=1))
    print(r["fa_text"][:1500])
