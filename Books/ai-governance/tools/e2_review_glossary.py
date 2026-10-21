#!/usr/bin/env python3
"""Policy review of edition-2 glossary entries (changed + new) through the approved Claude route.

    python3 tools/e2_review_glossary.py --root <E2 root> review [--only changed|new|all] [--batch 12] [--workers 3] [--escalate] [--run r3]
    python3 tools/e2_review_glossary.py --root <E2 root> apply
    python3 tools/e2_review_glossary.py --root <E2 root> snapshot --run r2     # bind an earlier, hash-less run to the CURRENT entry contents

`review` sends only entries that do not yet have a valid verdict to REVIEW_MAX (claude-sonnet-4-6 via providers.claude_call → claude_cli)
with a structured schema. A verdict is valid only if the SHA-256 of the entry view it reviewed equals the entry's current view, so a verdict
can never be attached to content that changed after the review. With --escalate the CHANGED entries (semantic/conflict cases) also go to
REVIEW_ULTRA (claude-opus-4-6). `apply` turns valid verdicts into statuses:
    AGREE (and Opus AGREE if it ran) → status 'approved', reviewer/review_mode/reviewed_at recorded
    anything else                    → stays 'proposed'; issues are stored in entry['e2']['review'] for the editor
Reviewer scope (recorded honestly): mapping, aliases, attribution, definition faithfulness to the supplied evidence text, orthography.
Letter-level accuracy of the evidence was verified separately against page images and is NOT re-verified by the reviewer.
Model usage is written to the model ledger by providers.py. This tool never approves anything on its own authority.
Run it from the E2 root's tools/ so the ledger of the right database is used.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import providers  # noqa: E402

SCHEMA = {"type": "object", "properties": {"verdicts": {"type": "array", "items": {"type": "object", "properties": {
    "key": {"type": "string"}, "verdict": {"type": "string", "enum": ["AGREE", "AGREE_WITH_EDIT", "DISAGREE"]},
    "issues": {"type": "array", "items": {"type": "object", "properties": {
        "type": {"type": "string", "enum": ["wrong_term", "alias_conflates_concepts", "definition_not_faithful", "invented_claim", "orthography", "attribution", "scope", "other"]},
        "explanation": {"type": "string"}, "suggested_fix": {"type": "string"}}, "required": ["type", "explanation"]}},
    "confidence": {"type": "number"}}, "required": ["key", "verdict", "issues"]}}}, "required": ["verdicts"]}

INSTRUCTIONS = """You are the terminology reviewer (policy mode {mode}) for edition 2 of a Persian reference book on AI governance.
For each glossary entry below, decide whether the PROPOSED entry is a faithful, careful rendering of what the Iranian national standards (INSO) PRINT.

Rules of the project (check each):
1. The preferred Persian term must equal the term the cited standard prints for that concept at that clause. Other Persian/common forms are only ALIASES and must not conflate a different concept
   (e.g. «interested party» ≠ «stakeholder»; «risk appetite» ≠ «risk tolerance»; «measure (noun)» ≠ «measurement»; an alias must not make privacy mean security).
2. Source-specific definitions stay attributed to their standard and clause; the NIST-context definition (when present) is kept separate and labelled; no silent merging; no invented official status.
3. Never claim INSO/ISO approval of anything beyond what is printed. Where a standard does not define a term, none may be invented.
4. Persian orthography: ZWNJ in compounds, consistent spelling; official forms are kept as printed even if unusual (e.g. «واپایش» for control, «مخاطره کاهی جامعگی و اخلاقی»).
5. The evidence text was transcribed from page images by a verified process: do NOT question letter-level accuracy of the printed evidence. DO question mapping, aliases, definition fidelity to the evidence text, attribution and scope.
   The cited items may include a standard's title or an outline heading: those are printed text too and are valid evidence that a term is used.
PROJECT NORMALISATION POLICY — do NOT flag these on their own (they are deliberate, documented decisions): (i) the INSO abbreviation «هومص» is expanded to «هوش مصنوعی» in headwords and the
printed form is kept as an alias; (ii) a printed parenthetical gloss stays part of the headword and the form without the gloss is an alias; (iii) a Latin acronym belongs only in the 'acronym' field; (iv) a space is inserted before an opening parenthesis (Persian typography) while the words themselves are unchanged.
Verdicts: AGREE (acceptable as a proposed approved entry), AGREE_WITH_EDIT (fixable; say exactly how), DISAGREE (wrong or misleading). Be concrete and brief. Return JSON only.

ENTRIES (JSON):
{payload}
"""


def entry_view(key: str, e: dict) -> dict:
    e2 = e.get("e2", {})
    return {"key": key, "english": e["english"], "domain": e["domain"], "change": e2.get("change"), "class": e2.get("class"),
            "old_preferred_fa": e2.get("old_preferred_fa"),
            "proposed": {"preferred_fa": e["preferred_fa"], "alternatives_fa": e["alternatives_fa"], "rejected_fa": e.get("rejected_fa", []), "acronym": e.get("acronym", ""),
                         "definition_fa": e.get("definition_fa", ""), "definition_en": e.get("definition_en", ""), "editor_note_fa": e.get("rationale")},
            "printed_evidence": [{k: r.get(k) for k in ("national_reference", "international_reference", "clause", "term_fa_printed", "term_en_printed", "definition_fa_printed",
                                                        "text_printed", "pdf_page", "printed_page") if r.get(k)} for r in e2.get("inso", [])],
            "variants_note_fa": e2.get("variants_note_fa")}


def view_sha(key: str, e: dict) -> str:
    return hashlib.sha256(json.dumps(entry_view(key, e), sort_keys=True, ensure_ascii=False).encode("utf-8")).hexdigest()


def call(model: str, purpose: str, mode: str, batch: list[tuple[str, dict]]) -> dict:
    prompt = INSTRUCTIONS.format(mode=mode, payload=json.dumps([entry_view(k, e) for k, e in batch], ensure_ascii=False, indent=1))
    r = providers.claude_call(model, prompt, purpose=purpose, schema=SCHEMA, timeout=900)
    return {"model": r.get("model"), "provider": r.get("provider"), "latency_s": r.get("latency_s"), "result": r.get("json"), "raw_text": None if r.get("json") else r.get("text")}


def collect(reviews_root: Path, current: dict[str, str]) -> dict[str, dict]:
    """key -> {'sonnet': verdict, 'opus': verdict}; only verdicts whose reviewed-content hash equals the CURRENT hash are returned."""
    out: dict[str, dict] = {}
    for run in sorted(p for p in reviews_root.glob("*") if p.is_dir()):
        legacy = json.loads((run / "_shas.json").read_text(encoding="utf-8")) if (run / "_shas.json").is_file() else {}
        for f in sorted(run.glob("*.json")):
            if f.name.startswith("_"):
                continue
            d = json.loads(f.read_text(encoding="utf-8"))
            who = "opus" if f.name.startswith("opus") else "sonnet"
            shas = d.get("shas") or legacy
            for v in (d.get("result") or {}).get("verdicts", []):
                if shas.get(v["key"]) and shas[v["key"]] == current.get(v["key"]):
                    out.setdefault(v["key"], {})[who] = {**v, "model": d["model"], "at": d["at"], "run": run.name}
    return out


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("cmd", choices=["review", "apply", "snapshot"])
    ap.add_argument("--only", choices=["changed", "new", "all"], default="all")
    ap.add_argument("--batch", type=int, default=12)
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--escalate", action="store_true")
    ap.add_argument("--run", default="r1", help="name of this review run; results live in evidence/glossary-reviews/<run>/ (earlier runs are kept)")
    args = ap.parse_args()
    root = Path(args.root)
    if args.cmd == "review" and root.resolve() != ROOT.resolve():
        raise SystemExit(f"run this tool from {root}/tools so the right model ledger is used (current ROOT={ROOT})")
    gpath = root / "translation_memory" / "glossary.json"
    doc = json.loads(gpath.read_text(encoding="utf-8"))
    reviews_root = root / "evidence" / "glossary-reviews"
    out_dir = reviews_root / args.run
    out_dir.mkdir(parents=True, exist_ok=True)
    current = {k: view_sha(k, v) for k, v in doc["terms"].items()}

    if args.cmd == "snapshot":     # bind a run made before hashes existed to the entry contents it actually reviewed (call BEFORE rebuilding the glossary)
        (out_dir / "_shas.json").write_text(json.dumps(current, ensure_ascii=False, indent=0), encoding="utf-8")
        print(f"snapshot of {len(current)} entry hashes written to {out_dir}/_shas.json")
        return 0

    if args.cmd == "review":
        valid = collect(reviews_root, current)
        wanted = ("changed", "new") if args.only == "all" else (args.only,)
        todo = [(k, v) for k, v in doc["terms"].items() if v.get("e2", {}).get("change") in wanted and v["status"] != "approved" and "sonnet" not in valid.get(k, {})]
        batches = [todo[i:i + args.batch] for i in range(0, len(todo), args.batch)]
        print(f"{len(todo)} entries need a Sonnet verdict, in {len(batches)} batches", flush=True)

        def work(i_b):
            i, b = i_b
            res = call("claude-sonnet-4-6", "terminology", "REVIEW_MAX", b)
            (out_dir / f"sonnet-{i:03d}.json").write_text(json.dumps({"keys": [k for k, _ in b], "shas": {k: current[k] for k, _ in b}, **res,
                                                                       "at": time.strftime("%Y-%m-%dT%H:%M:%S%z")}, ensure_ascii=False, indent=1), encoding="utf-8")
            return i, f"verdicts={len((res['result'] or {}).get('verdicts', []))}"

        with ThreadPoolExecutor(args.workers) as ex:
            for i, msg in ex.map(work, list(enumerate(batches))):
                print(f"batch {i}: {msg}", flush=True)
        if args.escalate:
            valid = collect(reviews_root, current)
            ch = [(k, v) for k, v in doc["terms"].items() if v.get("e2", {}).get("change") == "changed" and v["status"] != "approved" and "opus" not in valid.get(k, {})]
            for i in range(0, len(ch), 6):
                b = ch[i:i + 6]
                res = call("claude-opus-4-6", "escalation", "REVIEW_ULTRA", b)
                (out_dir / f"opus-{i // 6:03d}.json").write_text(json.dumps({"keys": [k for k, _ in b], "shas": {k: current[k] for k, _ in b}, **res,
                                                                              "at": time.strftime("%Y-%m-%dT%H:%M:%S%z")}, ensure_ascii=False, indent=1), encoding="utf-8")
                print(f"opus batch {i // 6}: verdicts={len((res['result'] or {}).get('verdicts', []))}", flush=True)
        return 0

    # ---- apply (valid verdicts only)
    valid = collect(reviews_root, current)
    stats = {"approved": 0, "kept_proposed": 0, "no_valid_verdict": 0}
    for k, e in doc["terms"].items():
        if e.get("e2", {}).get("change") not in ("changed", "new") or e["status"] == "approved":
            continue
        v = valid.get(k)
        if not v or "sonnet" not in v:
            stats["no_valid_verdict"] += 1
            continue
        e["e2"]["review"] = {"sonnet": {x: v["sonnet"].get(x) for x in ("verdict", "issues", "confidence", "model", "at", "run")}}
        if "opus" in v:
            e["e2"]["review"]["opus"] = {x: v["opus"].get(x) for x in ("verdict", "issues", "confidence", "model", "at", "run")}
        if v["sonnet"]["verdict"] == "AGREE" and v.get("opus", {}).get("verdict", "AGREE") == "AGREE":
            e["status"], e["reviewer"], e["review_mode"], e["reviewed_at"] = "approved", "claude_cli/" + v["sonnet"]["model"], "REVIEW_MAX" + ("+REVIEW_ULTRA" if "opus" in v else ""), v["sonnet"]["at"]
            e["e2"]["review"]["scope"] = ("mapping, aliases, attribution, definition fidelity to supplied evidence text, orthography; letter-level accuracy of the evidence was verified "
                                          "separately against page images")
            stats["approved"] += 1
        else:
            stats["kept_proposed"] += 1
    gpath.write_text(json.dumps(doc, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps(stats))
    return 0


if __name__ == "__main__":
    sys.exit(main())
