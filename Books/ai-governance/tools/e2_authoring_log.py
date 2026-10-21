#!/usr/bin/env python3
"""Authoring-and-review log of a later edition (provenance/e2-authoring-log.json, shipped as release artifact 31).

Records WHO produced and checked what, from the data the project actually holds: the chapter files (generator, QA stamp), the
glossary (review verdicts), the model-call ledger (state/project.db) and the correction records. It claims nothing that is not in
those records: no human review, no publisher/standards-body review, no certification.

    python3 tools/e2_authoring_log.py --root <root> [--since 2026-10-17]
"""
from __future__ import annotations

import argparse
import collections
import json
import re
import sqlite3
import sys
from pathlib import Path

import yaml


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("--since", default="2026-10-17", help="ledger rows from this date on belong to this edition's work")
    args = ap.parse_args()
    root = Path(args.root)
    prov = yaml.safe_load((root / "config" / "provenance.yaml").read_text(encoding="utf-8"))
    pub = yaml.safe_load((root / "config" / "publishing.yaml").read_text(encoding="utf-8"))

    files = []
    for f in sorted((root / "work" / "editorial").rglob("*.json")):
        d = json.loads(f.read_text(encoding="utf-8"))
        qa = d.get("qa") or {}
        files.append({"file": str(f.relative_to(root / "work" / "editorial")), "kind": d.get("kind"), "origin": d.get("origin"), "generator": d.get("generator"),
                      "generated_at": d.get("generated_at"), "sections": len(d.get("sections", [])),
                      "qa": {k: qa.get(k) for k in ("pass", "status", "reviewer", "review_mode", "runs", "content_bound") if k in qa}})
    new_files = [x for x in files if x["kind"] == "standards_integration"]

    gl = json.loads((root / "translation_memory" / "glossary.json").read_text(encoding="utf-8"))["terms"]
    gl_stat = collections.Counter(f"{(e.get('e2') or {}).get('change', 'unchanged')}/{e['status']}" for e in gl.values())
    gl_rev = collections.Counter()
    for e in gl.values():
        r = (e.get("e2") or {}).get("review") or {}
        for who in ("sonnet", "opus"):
            if who in r:
                gl_rev[f"{who}:{r[who].get('model')}:{r[who].get('verdict')}"] += 1

    con = sqlite3.connect(f"file:{root / 'state' / 'project.db'}?mode=ro", uri=True)
    ledger = [{"purpose": p, "provider": pr, "model": m, "status": st, "calls": n, "input_tokens": it, "output_tokens": ot}
              for p, pr, m, st, n, it, ot in con.execute("SELECT purpose, provider, model, status, COUNT(*), SUM(input_tokens), SUM(output_tokens) FROM model_usage WHERE ts >= ? GROUP BY 1,2,3,4 ORDER BY 1,2,3,4", (args.since,))]
    gpt = [r for r in ledger if re.search(r"gpt|o[134]-|openai", f"{r['provider']} {r['model']}", re.I)]

    ev_md = (root / "evidence" / "evidence-corrections.md")
    ev_lines = [l[2:].strip() for l in ev_md.read_text(encoding="utf-8").splitlines() if l.startswith("- ")] if ev_md.is_file() else []
    e1c = json.loads((root / "evidence" / "e1-corrections.json").read_text(encoding="utf-8"))
    runs = {p.name: sorted(x.name for x in p.glob("*.json")) for p in (root / "evidence" / "chapter-reviews").glob("*") if p.is_dir()}
    runs_g = {p.name: sorted(x.name for x in p.glob("*.json")) for p in (root / "evidence" / "glossary-reviews").glob("*") if p.is_dir()}

    out = {
        "edition_id": prov["edition_id"], "base_edition_id": prov.get("base_edition_id"),
        "statement": "This log lists what the project's own records show. It is not an attestation by any standards body or by a human reviewer.",
        "orchestrator": pub.get("orchestrator"),
        "writers": {"note": "chapters were drafted by delegated Claude workers from verified evidence packets; the generator field of each chapter file names the worker model as the harness reported it",
                    "generators": sorted({x["generator"] for x in files if x["generator"]})},
        "reviewers": {"policy": "claude_cli route only: claude-sonnet-4-6 (REVIEW_MAX), claude-opus-4-6 (REVIEW_ULTRA) for escalation; GPT text models are denied by config/model-policy.yaml",
                      "human_review": "none recorded", "publisher_or_standards_body_review": "none; INSO, ISO, IEC, IEEE and NIST did not review or approve this book"},
        "new_chapters": new_files,
        "all_editorial_files": len(files),
        "glossary": {"entries": len(gl), "by_change_and_status": dict(sorted(gl_stat.items())), "review_verdicts": dict(sorted(gl_rev.items())), "review_runs": runs_g},
        "chapter_review_runs": runs,
        "model_calls_since": args.since,
        "model_ledger": ledger,
        "ledger_note": "rows with status 'error' on provider 'antigravity' are attempts on a route that rejects the Claude model; the same call then ran on claude_cli. rows with http_401 are providers that were tried and refused credentials; none produced content.",
        "gpt_text_usage_in_this_edition": gpt,
        "evidence_corrections": ev_lines,
        "e1_editorial_corrections": [{k: c.get(k) for k in ("position", "section_title", "kind", "reason", "evidence_ids")} for c in e1c.get("corrections", [])],
        "signing": "development key only; the release is not signed by a publisher key (PUBLISHER_SIGNING_KEY_REQUIRED)",
    }
    dest = root / "provenance" / "e2-authoring-log.json"
    dest.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps({"written": str(dest.relative_to(root)), "new_chapters": len(new_files), "ledger_rows": len(ledger), "gpt_rows": len(gpt)}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
