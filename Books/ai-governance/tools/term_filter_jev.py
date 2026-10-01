#!/usr/bin/env python3
"""Phase 4 Jev filter (TypeSafe System One) for mined terminology candidates.

Reads taxonomy/terminology_candidates.json, asks Jev (jev-latest) two batched
judgments per term (is_domain_term: Noul, glossary_priority: Score), merges
acronym/initials clusters deterministically, writes:

  taxonomy/terminology_filtered.json   — clean candidates + Jev fields
  qa/phase4_jev_usage.json             — request/token usage log

Usage: python tools/term_filter_jev.py [--batch 30] [--threads 6] [--limit N]
"""
from __future__ import annotations
import argparse, json, os, re, sys, time, urllib.request, urllib.error
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402

CAND = ROOT / "taxonomy" / "terminology_candidates.json"
OUT = ROOT / "taxonomy" / "terminology_filtered.json"
USAGE = ROOT / "qa" / "phase4_jev_usage.json"
API = "https://api.typesafe.ai/v1/systemone"
MODEL = "jev-latest"

PRI_LEVELS = [
    "not_a_term: boilerplate, name, venue, citation or generic word — exclude from glossary",
    "peripheral: real concept but marginal for this book — include only if space allows",
    "useful: appears in specific sections, belongs in the extended glossary",
    "core: central AI governance/security/ML/risk/legal concept, must be in the glossary and kept consistent book-wide",
]


def jev_evaluate(questions: dict, state, api_key: str):
    body = json.dumps({"state": state, "model": MODEL, "questions": questions}).encode()
    req = urllib.request.Request(API, data=body, headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"})
    for attempt in range(6):
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                return json.loads(r.read())
        except urllib.error.HTTPError as e:
            if e.code in (429, 529) and attempt < 5:
                time.sleep(2**attempt + 1)
                continue
            raise
    raise RuntimeError("unreachable")


def term_state(chunk):
    return {"terms": [
        {"en": t["en"], "acronym": t.get("acronym") or "", "kinds": t.get("kinds", []),
         "freq": t.get("freq", 0), "n_docs": len(t.get("docs", [])),
         "definition_snippet": (t.get("definitions") or [{}])[0].get("text", "")[:220]}
        for t in chunk]}


def term_questions(chunk):
    qs = {}
    for i, t in enumerate(chunk):
        qs[f"is_term__{t['key']}"] = {
            "type": "noul",
            "instructions": {
                "question": f"Is `terms[{i}].en` a genuine domain concept term usable in a technical glossary about AI governance, AI security, machine learning, risk management, or law?",
                "context": {"yes": "a real concept, technique, property, role, framework, document type, attack, or metric in this domain (may be multiword; acronym entries count if the acronym is used as a term)",
                            "no": "boilerplate or noise: sentence fragments, cross-reference text (e.g. 'See id'), person names, organization names as publisher, journal/conference/proceedings names (e.g. 'ArXiv', 'Advances in Neural Information Processing Systems'), standalone generic words (e.g. 'Science', 'Technology'), citation fragments, figure/table references"}}}
        qs[f"priority__{t['key']}"] = {
            "type": "score",
            "instructions": f"How essential is `terms[{i}].en` for the glossary of a professionally edited Persian reference book compiled from NIST AI publications?",
            "criteria": PRI_LEVELS}
    return qs


def initials(term: str) -> str:
    words = re.findall(r"[A-Za-z]+", term)
    return "".join(w[0] for w in words).upper() if len(words) >= 2 else ""


def main(batch=30, threads=6, limit=None):
    api_key = None
    for line in (ROOT / ".env").read_text().splitlines():
        if line.startswith("TYPESAFE_API_KEY="):
            api_key = line.split("=", 1)[1].strip()
    if not api_key:
        sys.exit("TYPESAFE_API_KEY missing in .env")
    cand = json.load(open(CAND, encoding="utf-8"))["terms"]
    if limit:
        cand = cand[:limit]
    chunks = [cand[i:i + batch] for i in range(0, len(cand), batch)]
    usage_rows, con, results = [], db.connect(), {}
    t0 = time.time()

    def run_chunk(ci):
        chunk = chunks[ci]
        r = jev_evaluate(term_questions(chunk), term_state(chunk), api_key)
        return ci, chunk, r, time.time() - t0

    done = 0
    with ThreadPoolExecutor(max_workers=threads) as ex:
        futs = [ex.submit(run_chunk, ci) for ci in range(len(chunks))]
        for fut in as_completed(futs):
            ci, chunk, r, _ = fut.result()
            ans = r.get("answers", {})
            u = r.get("usage", {})
            usage_rows.append({"ts": db.now(), "purpose": "terminology", "provider": "typesafe", "model": r.get("model", MODEL),
                               "input_tokens": u.get("input_tokens", 0), "output_tokens": u.get("output_tokens", 0), "latency_s": round(time.time() - t0, 2), "status": "ok"})
            for t in chunk:
                k = t["key"]
                it = ans.get(f"is_term__{k}", {})
                pr = ans.get(f"priority__{k}", {})
                noul = it.get("noul")
                score = pr.get("score")
                results[k] = {**t, "jev_is_term": noul, "jev_priority": score,
                              "jev_priority_label": (pr.get("legend") or {}).get(str(int(score)) if score is not None else "", ""),
                              "jev_confidence": pr.get("confidence")}
            done += 1
            if done % 5 == 0 or done == len(chunks):
                print(f"  {done}/{len(chunks)} batches")

    kept, dropped = [], []
    for t in results.values():
        if "seed" in (t.get("kinds") or []):
            t["seed_whitelisted"] = True
            t["jev_priority"] = max(t.get("jev_priority") or 0, 2.5)
            kept.append(t)
            continue
        keep = (t["jev_is_term"] or 0) >= 0.5 and (t["jev_priority"] or 0) >= 1.0
        (kept if keep else dropped).append(t)
    kept.sort(key=lambda t: (-(t["jev_priority"] or 0), -(t["jev_is_term"] or 0), -t.get("score", 0)))

    # deterministic concept clustering — SUGGESTIONS ONLY, verified at propose/review stage.
    # Trust only clusters where a stored acronym field matches another term's stored acronym field.
    by_acro = {}
    for t in kept:
        a = (t.get("acronym") or "").strip().upper()
        if a and len(a) >= 2:
            by_acro.setdefault(a, []).append(t["key"])
    clusters = {}
    for a, keys in by_acro.items():
        if len({k for k in keys}) > 1:
            rep = max(keys, key=lambda k: (results[k].get("jev_priority") or 0, results[k].get("freq", 0)))
            for k in keys:
                clusters[k] = {"cluster_acronym": a, "canonical_key": rep}

    for t in kept:
        if t["key"] in clusters:
            t["cluster"] = clusters[t["key"]]

    OUT.write_text(json.dumps({"generated": db.now(), "source_candidates": len(cand), "kept": len(kept), "dropped": len(dropped),
                               "terms": kept, "dropped_terms": [{"en": d["en"], "key": d["key"], "jev_is_term": d["jev_is_term"], "jev_priority": d["jev_priority"]} for d in dropped]}, ensure_ascii=False, indent=1), encoding="utf-8")
    for u in usage_rows:
        con.execute("INSERT INTO model_usage(ts,job_id,purpose,provider,model,input_tokens,output_tokens,thinking_tokens,latency_s,status) VALUES(?,?,?,?,?,?,?,?,?,?)",
                    (u["ts"], None, u["purpose"], u["provider"], u["model"], u["input_tokens"], u["output_tokens"], 0, None, u["status"]))
    con.commit()
    USAGE.write_text(json.dumps({"requests": len(usage_rows), "input_tokens": sum(u["input_tokens"] for u in usage_rows),
                                 "output_tokens": sum(u["output_tokens"] for u in usage_rows), "wall_s": round(time.time() - t0, 1), "rows": usage_rows}, ensure_ascii=False, indent=1), encoding="utf-8")
    n_core = sum(1 for t in kept if (t["jev_priority"] or 0) >= 2.5)
    print(f"kept {len(kept)} / dropped {len(dropped)} | core {n_core} | clusters {len(set(c['cluster_acronym'] for c in clusters.values()))} | "
          f"{len(usage_rows)} req, {sum(u['input_tokens'] for u in usage_rows)} in / {sum(u['output_tokens'] for u in usage_rows)} out tok, {time.time()-t0:.0f}s")
    print("top core:", [t["en"] for t in kept[:20] if (t["jev_priority"] or 0) >= 2.5][:15])


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--batch", type=int, default=30)
    ap.add_argument("--threads", type=int, default=6)
    ap.add_argument("--limit", type=int)
    a = ap.parse_args()
    main(batch=a.batch, threads=a.threads, limit=a.limit)
