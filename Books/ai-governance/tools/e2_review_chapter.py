#!/usr/bin/env python3
"""Policy review of edition-2 standards chapters against their cited evidence (approved Claude route).

    python3 tools/e2_review_chapter.py --root <E2 root> review P02/C58 [P04/C62 ...] [--run r1] [--group 2] [--workers 3] [--opus]
    python3 tools/e2_review_chapter.py --root <E2 root> report P02/C58 [--run r1]
    python3 tools/e2_review_chapter.py --root <E2 root> stamp  P02/C58 --run r2   # write qa{} only if the run has no open issues

Every section is reviewed ONLY against the evidence items it cites (evidence_refs) plus the project terminology rules, in groups of --group
sections. Reviewer = claude-sonnet-4-6 (REVIEW_MAX) via providers.claude_call; --opus adds claude-opus-4-6 (REVIEW_ULTRA) as a second,
independent reviewer for normative/legal-sensitive chapters. Raw results stay in evidence/chapter-reviews/<run>/. `stamp` writes the chapter's
`qa` block ONLY when every section is PASS (and, if Opus ran, PASS by both): it records reviewers, models, time and scope; it never invents a pass.

Reviewer scope (recorded in qa.scope): faithfulness of claims to the cited evidence text, numbers/pages, modality force, terminology rules,
attribution and layer separation, no certification/legal/endorsement claims. Letter-level accuracy of the evidence was verified separately
against page images and is NOT re-verified here. Run from the E2 root's tools/ so the right ledger is used.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import providers  # noqa: E402

ISSUE_TYPES = ["unsupported_claim", "number_or_page_error", "modality_drift", "terminology", "attribution", "layer_blend", "certification_or_legal_claim", "quote_not_verbatim", "nist_crossref_mismatch", "other"]
SCHEMA = {"type": "object", "properties": {"sections": {"type": "array", "items": {"type": "object", "properties": {
    "index": {"type": "integer"}, "verdict": {"type": "string", "enum": ["PASS", "PASS_WITH_EDIT", "FAIL"]},
    "issues": {"type": "array", "items": {"type": "object", "properties": {
        "type": {"type": "string", "enum": ISSUE_TYPES}, "quote": {"type": "string"}, "explanation": {"type": "string"}, "suggested_fix": {"type": "string"}},
        "required": ["type", "explanation"]}}}, "required": ["index", "verdict", "issues"]}}}, "required": ["sections"]}

PROMPT = """You are the policy reviewer (mode {mode}) for edition 2 of a Persian reference book on AI governance. Review the SECTIONS below of chapter {chapter} against the CITED EVIDENCE ONLY.
Evidence items were transcribed from page images by a verified process: do NOT question letter-level accuracy of the evidence. Judge whether the sections are FAITHFUL to it.

For every section, check:
1. unsupported_claim — any factual statement about a standard (clauses, requirements, lists, steps, numbers, pages, years, identifiers, definitions, what is or is not printed) not supported by a cited item. Quote the sentence.
2. number_or_page_error — clause number, printed page, year, standard number or count that contradicts the evidence (pages in «ص …» are PRINTED pages).
3. modality_drift — «باید» / «توصیه می‌شود / بهتر است» / «می‌تواند / مجاز است» used with a force different from the evidence modality; «لازم است / ضروری است / موظف است» treated as shall.
4. terminology — violations of the TERMINOLOGY RULES below.
5. attribution / layer_blend — compiler opinion presented as the standard's; NIST content attributed to INSO/ISO/IEEE or vice versa; mixing the three layers (what the standard says / the book's explanation / Iranian application) without labelling.
6. certification_or_legal_claim — any claim or implication of certification, legal/regulatory compliance, official approval or endorsement (INSO, ISO, IEC, IEEE, NIST), or any invented Iranian law, regulator, authority or mandate.
7. quote_not_verbatim — a blockquote that is not word-for-word the cited verbatim item.
8. nist_crossref_mismatch — a cited E1/NIST section ID whose title (given below) does not support the cross-reference made.
9. other — meaning-changing errors in the Persian text.
Be strict on 1–8, lenient on style. Verdicts per section: PASS (nothing to fix), PASS_WITH_EDIT (small, concrete fixes), FAIL (unsupported or misleading content). Each issue needs a short exact quote from the section and a concrete fix. Return JSON only.

TERMINOLOGY RULES (summary of the project file):
{terminology}

SOURCE INDEX of the declared national standards (reference only: the complete heading outline and the complete list of defined terms of each document, as transcribed from the page images).
Use it ONLY to verify clause numbers, printed headings, counts of headings/terms, and whether a heading or term exists. It is NOT a source for what a clause says. A statement that a heading/term does or does not exist, or a count of
headings/terms, is supported when this index shows it; any other absence claim ("the standard says nothing about X", "the word Y occurs only once") is supported ONLY if it is phrased as limited to the clauses actually cited.
{source_index}

SECTIONS (JSON; evidence = the ONLY items each section may rely on):
{payload}
"""


def load_context(root: Path):
    index = json.loads((root / "evidence" / "packets" / "index.json").read_text(encoding="utf-8"))
    e1 = {}
    for line in (root / "evidence" / "e1-index.md").read_text(encoding="utf-8").splitlines():
        m = re.match(r"- (KDJ-AI-2026E2-P\d{2}-C\d{2}-S\d{2}) \| [^|]+ \| (.*)", line)
        if m:
            e1[m.group(1)] = m.group(2)
    term = (root / "evidence" / "terminology-decisions.md").read_text(encoding="utf-8")
    return index, e1, term


def section_payload(i: int, s: dict, index: dict, e1: dict) -> dict:
    ev = []
    for ref in s.get("evidence_refs", []):
        it = index.get(ref)
        if it:
            pe = f"–{it['printed_page_end']}" if it.get("printed_page_end") else ""
            pages = f"pdf {it.get('pdf_page')}" + (f"–{it['pdf_page_end']}" if it.get("pdf_page_end") else "") + (f" / printed {it.get('printed_page')}{pe}" if it.get("printed_page") not in (None, "") else "")
            ev.append({"id": ref, "kind": it["kind"], "clause": it.get("clause"), "pages": pages, "text": it["text"][:1400]})
    have = {e["id"] for e in ev}
    for ref, it in index.items():      # identity/normative-reference items of every DECLARED source are always available to the reviewer
        if it["kind"] == "identity" and it["source_id"] in s.get("derived_from_source_ids", []) and ref not in have:
            ev.append({"id": ref, "kind": "identity", "clause": it.get("clause"), "pages": "", "text": it["text"][:900]})
    return {"index": i, "title": s["title_fa"], "label": s["label_fa"], "origin": s["origin"], "declared_sources": s.get("derived_from_source_ids", []),
            "cross_references": {c: e1.get(c, "?") for c in s.get("derived_from_content_ids", [])}, "text": s["fa_text"], "evidence": ev}


def source_index(index: dict, source_ids: list[str]) -> str:
    """Compact heading outline + term list per declared INSO source (prompt context; deliberately NOT part of the content-bound section hash)."""
    lines: list[str] = []
    for sid in sorted(set(source_ids)):
        outline = [(v.get("clause"), v["text"]) for k, v in index.items() if v.get("source_id") == sid and v["kind"] == "outline"]
        terms = [(v.get("clause"), v["text"].split(" — ")[0].replace("**", "")) for k, v in index.items() if v.get("source_id") == sid and v["kind"] == "term"]
        if not outline and not terms:
            continue
        lines.append(f"== {sid}: {len(outline)} outline headings, {len(terms)} defined terms")
        lines += [f"H {c or '-'} | {t[5:] if t.startswith('None ') else t}" for c, t in outline]
        lines += [f"T {c or '-'} | {t}" for c, t in terms]
    return "\n".join(lines) if lines else "(none: sources without an index)"


def chapter_path(root: Path, ch: str) -> Path:
    return root / "work" / "editorial" / f"{ch}.json"


def section_sha(i: int, s: dict, index: dict, e1: dict) -> str:
    """Hash of EXACTLY what the reviewer sees for this section (text, labels, cited evidence, cross-reference titles)."""
    return hashlib.sha256(json.dumps(section_payload(i, s, index, e1), sort_keys=True, ensure_ascii=False).encode("utf-8")).hexdigest()


def collect(out_root: Path, tag: str, current: dict[int, str]) -> dict[tuple[str, int], dict]:
    """(reviewer, section index) -> verdict, only when the reviewed-content hash equals the CURRENT hash (latest run wins)."""
    res: dict[tuple[str, int], dict] = {}
    for run in sorted(p for p in out_root.glob("*") if p.is_dir()):
        for f in sorted(run.glob(f"{tag}-*.json")):
            d = json.loads(f.read_text(encoding="utf-8"))
            who = "opus" if "-opus-" in f.name else "sonnet"
            shas = d.get("section_shas") or {}
            for sec in (d.get("result") or {}).get("sections", []):
                if shas.get(str(sec["index"])) and shas[str(sec["index"])] == current.get(sec["index"]):
                    res[(who, sec["index"])] = {**sec, "model": d["model"], "at": d["at"], "run": run.name}
    return res


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("cmd", choices=["review", "report", "stamp"])
    ap.add_argument("chapters", nargs="+")
    ap.add_argument("--run", default="r1")
    ap.add_argument("--group", type=int, default=2)
    ap.add_argument("--workers", type=int, default=3)
    ap.add_argument("--opus", action="store_true")
    ap.add_argument("--only", default=None, help="comma list of section indexes to (re)review")
    args = ap.parse_args()
    root = Path(args.root)
    if args.cmd == "review" and root.resolve() != ROOT.resolve():
        raise SystemExit(f"run from {root}/tools so the right model ledger is used (ROOT={ROOT})")
    index, e1, term = load_context(root)
    reviews_root = root / "evidence" / "chapter-reviews"
    out = reviews_root / args.run
    out.mkdir(parents=True, exist_ok=True)

    for ch in args.chapters:
        doc = json.loads(chapter_path(root, ch).read_text(encoding="utf-8"))
        tag = ch.replace("/", "-")
        current = {i: section_sha(i, s, index, e1) for i, s in enumerate(doc["sections"])}
        valid = collect(reviews_root, tag, current)
        only = {int(x) for x in args.only.split(",")} if args.only else None

        if args.cmd == "review":
            todo = {who: [i for i in range(len(doc["sections"])) if (only is None or i in only) and (who, i) not in valid] for who in (("sonnet", "opus") if args.opus else ("sonnet",))}
            jobs = [(who, idxs[k:k + args.group]) for who, idxs in todo.items() for k in range(0, len(idxs), args.group)]
            print(f"{ch}: {sum(len(v) for v in todo.values())} section reviews needed in {len(jobs)} calls", flush=True)

            def work(job):
                who, g = job
                payload = json.dumps([section_payload(i, doc["sections"][i], index, e1) for i in g], ensure_ascii=False, indent=1)
                model, purpose, mode = ("claude-sonnet-4-6", "review", "REVIEW_MAX") if who == "sonnet" else ("claude-opus-4-6", "escalation", "REVIEW_ULTRA")
                declared = [x for i in g for x in doc["sections"][i].get("derived_from_source_ids", [])]
                prompt = PROMPT.format(mode=mode, chapter=ch, terminology=term[:9000], payload=payload, source_index=source_index(index, declared))
                t = time.time()
                r = providers.claude_call(model, prompt, purpose=purpose, schema=SCHEMA, timeout=900)
                path = out / f"{tag}-{who}-{g[0]:02d}.json"
                if path.exists():                      # same group re-reviewed within one run: keep both, never overwrite a record
                    path = out / f"{tag}-{who}-{g[0]:02d}-{int(time.time())}.json"
                path.write_text(json.dumps({"chapter": ch, "sections": g, "section_shas": {str(i): current[i] for i in g}, "model": r.get("model"), "provider": r.get("provider"),
                                            "latency_s": r.get("latency_s"), "result": r.get("json"), "raw_text": None if r.get("json") else r.get("text"),
                                            "at": time.strftime("%Y-%m-%dT%H:%M:%S%z")}, ensure_ascii=False, indent=1), encoding="utf-8")
                return f"{who} {g}: {time.time() - t:.0f}s"
            with ThreadPoolExecutor(args.workers) as ex:
                for msg in ex.map(work, jobs):
                    print(f"{ch} {msg}", flush=True)
            continue

        total = {"PASS": 0, "PASS_WITH_EDIT": 0, "FAIL": 0, "missing_or_stale": 0}
        open_issues = []
        for i in range(len(doc["sections"])):
            for who in ("sonnet", "opus") if args.opus or any(w == "opus" for w, _ in valid) else ("sonnet",):
                r = valid.get((who, i))
                if r is None:
                    total["missing_or_stale"] += 1
                    continue
                total[r["verdict"]] += 1
                if r["verdict"] != "PASS":
                    open_issues.append((who, i, doc["sections"][i]["title_fa"], r))
        print(f"\n== {ch}: {json.dumps(total)}")
        if args.cmd == "report":
            for who, i, title, r in open_issues:
                print(f"\n[{who} §{i} {r['verdict']}] {title}")
                for iss in r["issues"]:
                    print(f"   - {iss['type']}: {iss['explanation'][:420].replace(chr(10), ' ')}\n       quote: {(iss.get('quote') or '')[:200]}\n       fix: {(iss.get('suggested_fix') or '')[:300]}")
            continue
        if open_issues or total["missing_or_stale"]:
            print("not stamped: open issues, or reviews missing/stale for the current text")
            continue
        opus_ran = any(w == "opus" for w, _ in valid)
        doc["qa"] = {"pass": True, "violations": [], "reviewer": "claude_cli/claude-sonnet-4-6" + ("; claude_cli/claude-opus-4-6" if opus_ran else ""),
                     "review_mode": "REVIEW_MAX" + ("+REVIEW_ULTRA" if opus_ran else ""), "runs": sorted({r["run"] for r in valid.values()}), "at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
                     "content_bound": True,
                     "scope": ("faithfulness of claims to the cited evidence, numbers/pages, modality force, terminology rules, attribution/layer separation, no certification/legal/"
                               "endorsement claims; letter-level accuracy of the evidence was verified separately against page images and is not re-verified by the reviewer"),
                     "author": doc.get("generator"), "not_reviewed_by": "INSO / ISO / IEC / IEEE / NIST"}
        chapter_path(root, ch).write_text(json.dumps(doc, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
        print("stamped qa.pass = True (verdicts are bound to the current text)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
