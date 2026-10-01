#!/usr/bin/env python3
"""Phase 6 — translation-model benchmark (MasterPrompt §12).
Selects representative chunks (AI RMF, adversarial ML, GenAI, explainability, bias, legal, glossary, complex table),
translates each with every candidate, scores each output with Claude Sonnet 4.6 REVIEW_MAX, writes qa/model_benchmark.{json,md},
and sets PRIMARY/SECONDARY/FAST_FALLBACK in config/translation.yaml."""
from __future__ import annotations
import json, re, sys, time
from pathlib import Path
import yaml

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers, prompts  # noqa: E402

CANDIDATES = [("zai-coding-plan", "glm-5.3"), ("zai-coding-plan", "glm-5.3-flash"), ("zai-coding-plan", "glm-5.2"), ("zai-coding-plan", "glm-5-turbo"),
              ("google", "gemini-3.8-flash"), ("google", "gemini-3.1-flash-lite")]
TARGETS = [("NIST-AI-100-1", "ai_rmf", None), ("NIST-AI-100-2E2025", "adversarial_ml", "security_taxonomy"), ("NIST-AI-600-1", "generative_ai", None),
           ("NIST-IR-8312", "explainability", None), ("NIST-SP-1270", "bias", None), ("NIST-PAPER-3-LEGAL", "legal_ai", "legal"),
           ("NIST-AI-100-3", "glossary", "definitions"), ("NIST-AI-RMF-PLAYBOOK", "complex_table", "complex_table")]
OUT_JSON = ROOT / "qa" / "model_benchmark.json"; OUT_MD = ROOT / "qa" / "model_benchmark.md"

SCORE_SCHEMA = {"type": "object", "properties": {"scores": {"type": "object", "properties": {
    "fidelity": {"type": "integer"}, "completeness": {"type": "integer"}, "terminology": {"type": "integer"}, "fluency": {"type": "integer"}, "formatting": {"type": "integer"}},
    "required": ["fidelity", "completeness", "terminology", "fluency", "formatting"]}, "critical_defects": {"type": "array", "items": {"type": "string"}},
    "comment": {"type": "string"}}, "required": ["scores", "critical_defects"]}

def pick_chunks() -> list[dict]:
    out = []
    for doc, label, flag in TARGETS:
        cdir = ROOT / "work" / "chunks" / doc
        files = sorted(cdir.glob("CH*.json"))
        chosen = None
        cands = [json.load(open(f, encoding="utf-8")) for f in files]
        pool = [c for c in cands if 350 <= c["word_count"] <= 900 and (flag is None or flag in c["flags"])]
        if not pool:
            pool = [c for c in cands if 250 <= c["word_count"] <= 1200] or cands
        if pool:
            chosen = sorted(pool, key=lambda c: abs(c["word_count"] - 600))[0]
            chosen["bench_label"] = label; out.append(chosen)
    return out

def main(limit_models=None, translate_only=False, threads=1):
    chunks = pick_chunks()
    res = json.load(open(OUT_JSON, encoding="utf-8")) if OUT_JSON.exists() else {"runs": {}}
    lock = __import__("threading").Lock()
    tasks = [(c, prov, model) for c in chunks for prov, model in (CANDIDATES[:limit_models] if limit_models else CANDIDATES)]

    def run_one(c, prov, model):
        key = f"{c['chunk_id']}|{prov}/{model}"
        with lock:
            rec = res["runs"].get(key) or {}
            if translate_only and rec.get("ok") and rec.get("fa_text"):
                return
            if rec.get("quota_skip"):
                return
        system, user = prompts.build_translate_prompt(c)
        if rec.get("ok") and rec.get("fa_text"):
            fa, lat, ok, err = rec["fa_text"], rec.get("latency_s", 0), True, None
        else:
            t0 = time.time()
            try:
                r = providers.translate(prov, model, system, user, purpose="translation", max_tokens=8000)
                fa, lat, ok, err = r["text"], r["latency_s"], True, None
            except Exception as e:
                fa, lat, ok, err = "", time.time() - t0, False, str(e)[:200]
        st = prompts.structural_check(c["en_text"], fa) if fa else {"issues": [{"type": "no_output"}], "hard_fail": True}
        rec = {"chunk_id": c["chunk_id"], "label": c["bench_label"], "provider": prov, "model": model, "latency_s": round(lat, 1), "ok": ok, "error": err,
               "words_en": c["word_count"], "words_fa": len(fa.split()), "structural_issues": len(st["issues"]), "structural_hard_fail": st["hard_fail"], "fa_text": fa}
        if fa and not translate_only and not rec.get("scores"):
            prompt = ("Score this Persian translation of an English NIST passage. Compare strictly against the English. Scores 0–100: fidelity, completeness, terminology "
                      "(formal, consistent, glossary-aligned Persian technical terms), fluency (edited-book Persian, نیم‌فاصله, punctuation), formatting (Markdown structure preserved). "
                      "List critical defects (omission, addition, changed number/modality/negation, wrong identifier). Reply ONLY with JSON matching the schema.\n\n"
                      f"===== ENGLISH =====\n{c['en_text']}\n===== PERSIAN =====\n{fa}\n===== END =====")
            try:
                rr = providers.claude_agy("claude-sonnet-4-6", prompt, purpose="review", schema=SCORE_SCHEMA)
                rec["scores"] = (rr["json"] or {}).get("scores"); rec["critical_defects"] = (rr["json"] or {}).get("critical_defects"); rec["comment"] = (rr["json"] or {}).get("comment")
            except Exception as e:
                rec["scores"] = None; rec["score_error"] = str(e)[:200]
        if translate_only:
            rec["score_status"] = "pending_claude" if rec.get("ok") else "translate_failed"
        with lock:
            res["runs"][key] = rec
            OUT_JSON.write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding="utf-8")
        print(f"{c['bench_label']:16s} {prov}/{model:22s} lat={rec['latency_s']:5.0f}s ok={ok} struct_issues={rec['structural_issues']}" + ("" if translate_only else f" scores={rec.get('scores')}"), flush=True)

    if threads > 1:
        from concurrent.futures import ThreadPoolExecutor
        with ThreadPoolExecutor(max_workers=threads) as ex:
            list(ex.map(lambda t: run_one(*t), tasks))
    else:
        for t in tasks:
            run_one(*t)
    if not translate_only:
        summarize(res)
    else:
        n_ok = sum(1 for r in res["runs"].values() if r.get("ok"))
        print(f"translate-only complete: {n_ok}/{len(tasks)} translations ok; scoring pending Claude (rerun without --translate-only)")

def summarize(res):
    agg = {}
    for r in res["runs"].values():
        k = f"{r['provider']}/{r['model']}"
        a = agg.setdefault(k, {"n": 0, "fail": 0, "lat": [], "fid": [], "comp": [], "term": [], "flu": [], "fmt": [], "crit": 0, "hard": 0})
        a["n"] += 1
        if not r["ok"] or not r.get("scores"):
            a["fail"] += 1; continue
        s = r["scores"]; a["lat"].append(r["latency_s"]); a["fid"].append(s["fidelity"]); a["comp"].append(s["completeness"]); a["term"].append(s["terminology"]); a["flu"].append(s["fluency"]); a["fmt"].append(s["formatting"])
        a["crit"] += len(r.get("critical_defects") or []); a["hard"] += int(r.get("structural_hard_fail", False))
    rows = []
    for k, a in agg.items():
        m = lambda l: round(sum(l) / len(l), 1) if l else 0
        composite = 0.35 * m(a["fid"]) + 0.25 * m(a["comp"]) + 0.2 * m(a["term"]) + 0.15 * m(a["flu"]) + 0.05 * m(a["fmt"]) - 3 * a["crit"] - 5 * a["hard"] - 10 * a["fail"]
        rows.append({"model": k, "runs": a["n"], "failures": a["fail"], "latency_avg": m(a["lat"]), "fidelity": m(a["fid"]), "completeness": m(a["comp"]), "terminology": m(a["term"]),
                     "fluency": m(a["flu"]), "formatting": m(a["fmt"]), "critical_defects": a["crit"], "structural_hard_fails": a["hard"], "composite": round(composite, 1)})
    rows.sort(key=lambda r: -r["composite"])
    res["summary"] = rows
    if rows:
        ok_rows = [r for r in rows if r["failures"] == 0 or r["runs"] - r["failures"] >= 5]
        primary = ok_rows[0]["model"] if ok_rows else rows[0]["model"]
        others = [r for r in ok_rows if r["model"] != primary]
        # prefer a different provider as secondary for resilience
        secondary = next((r["model"] for r in others if r["model"].split("/")[0] != primary.split("/")[0]), others[0]["model"] if others else primary)
        fast = min(others or rows, key=lambda r: r["latency_avg"])["model"]
        res["selection"] = {"PRIMARY_TRANSLATOR": primary, "SECONDARY_TRANSLATOR": secondary, "FAST_FALLBACK_TRANSLATOR": fast, "selected_at": db.now(), "reviewer": "antigravity/claude-sonnet-4-6 (REVIEW_MAX)"}
        cfg_path = ROOT / "config" / "translation.yaml"
        txt = cfg_path.read_text(encoding="utf-8")
        for k, v in res["selection"].items():
            if k.endswith("TRANSLATOR"):
                txt = re.sub(rf'^{k}:.*$', f'{k}:       "{v}"', txt, flags=re.M)
        cfg_path.write_text(txt, encoding="utf-8")
    OUT_JSON.write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding="utf-8")
    md = ["# Translation model benchmark", "", f"Generated {db.now()} — scored by Claude Sonnet 4.6 Thinking (REVIEW_MAX) via Antigravity; passages from 8 representative chunks.", "",
          "| model | runs | fail | latency s | fidelity | completeness | terminology | fluency | formatting | critical | hard-fail | composite |", "|---|---|---|---|---|---|---|---|---|---|---|---|"]
    for r in rows:
        md.append(f"| {r['model']} | {r['runs']} | {r['failures']} | {r['latency_avg']} | {r['fidelity']} | {r['completeness']} | {r['terminology']} | {r['fluency']} | {r['formatting']} | {r['critical_defects']} | {r['structural_hard_fails']} | {r['composite']} |")
    if res.get("selection"):
        md += ["", "## Selection", ""] + [f"- **{k}**: `{v}`" for k, v in res["selection"].items()]
    OUT_MD.write_text("\n".join(md) + "\n", encoding="utf-8")
    print("\n".join(md))

if __name__ == "__main__":
    import argparse
    ap = argparse.ArgumentParser()
    ap.add_argument("--translate-only", action="store_true", help="run translations only; Claude scoring later")
    ap.add_argument("--threads", type=int, default=1)
    ap.add_argument("--limit-models", type=int)
    a = ap.parse_args()
    main(limit_models=a.limit_models, translate_only=a.translate_only, threads=a.threads)
