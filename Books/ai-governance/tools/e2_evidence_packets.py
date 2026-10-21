#!/usr/bin/env python3
"""Turn page-image-verified evidence (evidence/out/<ID>/*.json) into citable packets.

Every evidence item gets a stable ID so that drafted sections and reviewers can cite exactly what a claim rests on:
    T<short>-<clause>      defined term (verbatim term + definition)
    V<short>-<n>           vocabulary / footnote pair printed by the translators
    S<short>-<n>           statement (neutral paraphrase + modality as printed)
    C<short>-<id>          control (ISO/IEC 42001 Annex A)
    Q<short>-<id>          quality-model item (ISO/IEC 25059)
    F<short>-<id>          table / figure
    O<short>-<n>           outline row
<short> is the national number without the year (42001, 23682, 15816-3 ...).

Outputs
    evidence/packets/<SOURCE-ID>.md     compact Markdown packet for writers
    evidence/packets/index.json         {item_id: {source_id, kind, clause, pdf_page, printed_page, text}} for reviewers

Evidence is never edited here; this is a read-only transformation. Re-running regenerates identical IDs (order-stable).

    python3 tools/e2_evidence_packets.py --root <E2 root>
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path


def short(sid: str) -> str:
    m = re.match(r"INSO-(.+)-\d{4}$", sid)
    return m.group(1) if m else sid


def page(item: dict) -> str:
    pdf, printed = item.get("pdf_page"), item.get("printed_page")
    if item.get("pdf_page_end"):
        pdf = f"{pdf}–{item['pdf_page_end']}"
    if item.get("printed_page_end"):
        printed = f"{printed}–{item['printed_page_end']}"
    return f"pdf {pdf}" + (f" / printed {printed}" if printed not in (None, "") else "")


def unique(item_id: str, seen: dict) -> str:
    """Return item_id, or item_id+b/c/d… when the ID was already issued (same clause number printed on several rows)."""
    if item_id not in seen:
        return item_id
    for suffix in "bcdefghijklmnopqrstuvwxyz":
        if item_id + suffix not in seen:
            return item_id + suffix
    n = 2
    while f"{item_id}-{n}" in seen:
        n += 1
    return f"{item_id}-{n}"


def load(path: Path):
    return json.loads(path.read_text(encoding="utf-8")) if path.is_file() else None


def build(root: Path) -> dict:
    out = root / "evidence" / "packets"
    out.mkdir(parents=True, exist_ok=True)
    index: dict[str, dict] = {}
    for d in sorted((root / "evidence" / "out").iterdir()):
        if not d.is_dir():
            continue
        sid, sh = d.name, short(d.name)
        ident, outline = load(d / "identity.json"), load(d / "outline.json")
        terms, stmts = load(d / "terms.json"), load(d / "statements.json")
        figs, ctrl, qm = load(d / "tables_figures.json"), load(d / "controls.json"), load(d / "quality_model.json")
        md: list[str] = [f"# Evidence packet — {sid} (national {sh})", "",
                         "All items verified against rendered page images. Quote ONLY T-items (terms/definitions) verbatim; paraphrase everything else.",
                         "Cite item IDs in `evidence_refs`. Page = PDF page / printed page.", ""]

        def add(item_id: str, kind: str, clause, item: dict, text: str):
            index[item_id] = {"source_id": sid, "kind": kind, "clause": clause, "pdf_page": item.get("pdf_page"), "printed_page": item.get("printed_page"), "text": text,
                              **({"pdf_page_end": item["pdf_page_end"], "printed_page_end": item["printed_page_end"]} if item.get("printed_page_end") else {})}

        if ident:
            md += ["## Identity (printed)", ""]
            for k in ("national_reference_printed", "iranian_year_printed", "title_fa_printed", "title_en_printed", "international_reference_printed",
                      "equivalence_statement_printed", "approval_or_publication_date_printed", "ics_printed", "pdf_pages_total", "pdf_to_printed_offset"):
                if ident.get(k) not in (None, ""):
                    md.append(f"- {k}: {json.dumps(ident[k], ensure_ascii=False) if not isinstance(ident[k], str) else ident[k]}")
            for r in ident.get("normative_references") or []:
                md.append(f"- normative reference: {json.dumps(r, ensure_ascii=False)[:420]}")
            md.append("")
        if ident:
            md += ["## Identity items (reviewable; I<short>-id, -nr = normative reference, -rel = related/bibliography reference)", ""]
            keys = ("national_reference_printed", "iranian_year_printed", "title_fa_printed", "title_en_printed", "international_reference_printed", "equivalence_statement_printed",
                    "adoption_reference_printed", "approval_or_publication_date_printed", "ics_printed", "pdf_pages_total", "pdf_to_printed_offset")
            body = " | ".join(f"{k}: {json.dumps(ident[k], ensure_ascii=False) if not isinstance(ident[k], str) else ident[k]}" for k in keys if ident.get(k) not in (None, ""))
            add(f"I{sh}-id", "identity", None, {"pdf_page": 1, "printed_page": None}, body[:1500])
            md.append(f"- I{sh}-id | {body[:1500]}")
            ad = ident.get("approval_or_publication_date_printed")
            if ad not in (None, "", {}):      # kind 'identity_date' (not 'identity'): cite it where needed; it is not auto-attached to every section
                dtext = json.dumps(ad, ensure_ascii=False) if not isinstance(ad, str) else ad
                add(f"I{sh}-date", "identity_date", None, {"pdf_page": ad.get("pdf_page") if isinstance(ad, dict) else None, "printed_page": ad.get("printed_page") if isinstance(ad, dict) else None}, dtext[:900])
                md.append(f"- I{sh}-date | approval / publication date as printed | {dtext[:900]}")
            for n, r in enumerate(ident.get("normative_references") or [], 1):
                text = json.dumps(r, ensure_ascii=False)[:600]
                add(f"I{sh}-nr{n}", "identity", r.get("clause"), r, text)
                md.append(f"- I{sh}-nr{n} | {text}")
            for rk in ("related_national_standards_printed_in_text_or_bibliography", "related_national_standards_printed_in_bibliography", "bibliography_standards_cited"):
                for n, r in enumerate(ident.get(rk) or [], 1):
                    text = json.dumps(r, ensure_ascii=False)[:600]
                    add(f"I{sh}-rel{n}", "identity", None, r if isinstance(r, dict) else {}, text)
                    md.append(f"- I{sh}-rel{n} | {text}")
            # further printed identity facts that earlier had no item ID (bibliography entries, the transcriber's recorded uncertainties/notes, committee):
            # kind 'identity_extra' = citable where needed, never auto-attached to every section
            for rk, tag in (("bibliography_informative_printed", "bib"), ("uncertainties", "unc"), ("notes", "inote")):
                vals = ident.get(rk)
                if isinstance(vals, list):
                    for n, r in enumerate(vals, 1):
                        text = (json.dumps(r, ensure_ascii=False) if not isinstance(r, str) else r)[:700]
                        add(f"I{sh}-{tag}{n}", "identity_extra", r.get("clause") if isinstance(r, dict) else None, r if isinstance(r, dict) else {}, text)
                        md.append(f"- I{sh}-{tag}{n} | {rk} | {text}")
                elif isinstance(vals, str) and vals.strip():
                    add(f"I{sh}-{tag}1", "identity_extra", None, {}, vals[:700])
                    md.append(f"- I{sh}-{tag}1 | {rk} | {vals[:700]}")
            if ident.get("technical_committee_printed"):
                tc = ident["technical_committee_printed"]
                tct = (json.dumps(tc, ensure_ascii=False) if not isinstance(tc, str) else tc)[:700]
                add(f"I{sh}-tc", "identity_extra", None, {}, tct)
                md.append(f"- I{sh}-tc | technical_committee_printed | {tct}")
            for rk in ("normative_references_note", "normative_references_statement"):
                if ident.get(rk):
                    add(f"I{sh}-{rk[-4:]}", "identity", 2, {}, str(ident[rk])[:600])
                    md.append(f"- I{sh}-{rk[-4:]} | {str(ident[rk])[:600]}")
            md.append("")
        if outline:
            md += ["## Outline (clause | heading | pages)", ""]
            for n, c in enumerate(outline.get("clauses", []), 1):
                oid = f"O{sh}-{n:03d}"
                text = f"{c.get('number')} {c.get('heading_fa')}"
                add(oid, "outline", c.get("number"), c, text)
                md.append(f"- {oid} | {c.get('number')} | {c.get('heading_fa')} | {page(c)} | {c.get('type', '')}")
            md.append("")
        if terms:
            md += ["## Terms and definitions (VERBATIM — quotable, short)", ""]
            for t in terms.get("terms", []):
                tid = unique(f"T{sh}-{t.get('clause')}", index)
                notes = " / ".join(t.get("notes_fa") or [])
                text = f"{t.get('term_fa')} | {t.get('term_en')} — {t.get('definition_fa')}" + (f" [NOTES: {notes}]" if notes else "")
                add(tid, "term", t.get("clause"), t, text)
                md.append(f"- {tid} | cl.{t.get('clause')} | {page(t)} | **{t.get('term_fa')}** ({t.get('term_en')}): {t.get('definition_fa')}" + (f"  \n  notes: {notes}" if notes else ""))
            for n, p in enumerate(terms.get("vocabulary_footnote_pairs") or [], 1):
                vid = f"V{sh}-{n:03d}"
                text = f"{p.get('term_fa_as_printed')} | {p.get('term_en_as_printed')}"
                add(vid, "vocabulary", None, p, text)
                md.append(f"- {vid} | {page(p)} | {text}")
            md.append("")
        if stmts:
            md += ["## Statements (paraphrase; modality as printed: shall / should / may / info)", ""]
            for n, s in enumerate(stmts.get("statements", []), 1):
                sidx = f"S{sh}-{n:03d}"
                text = f"[{s.get('modality')}] {s.get('topic_fa') or ''} — {s.get('statement_fa')}"
                add(sidx, "statement", s.get("clause"), s, text)
                md.append(f"- {sidx} | cl.{s.get('clause')} | {s.get('modality')} | {page(s)} | {s.get('topic_fa') or ''} — {s.get('statement_fa')}")
            md.append("")
        if ctrl:
            md += ["## Controls (Annex A)", ""]
            for o in ctrl.get("objectives", []):
                md.append(f"### {o.get('id')} {o.get('title_fa')}")
                bid = f"C{sh}-{o.get('id')}"            # the printed block (control-objective) heading itself is evidence too
                add(bid, "control_block", o.get("id"), o, f"{o.get('id')} {o.get('title_fa')} — هدف واپایشی: {o.get('objective_fa_short') or ''}")
                md.append(f"- {bid} | block title (printed) | {o.get('title_fa')} | objective: {o.get('objective_fa_short') or ''}")
                for c in o.get("controls", []):
                    cid = f"C{sh}-{c.get('id')}"
                    text = f"{c.get('id')} {c.get('title_fa')} — {c.get('control_statement_fa_short')} [{c.get('modality')}]"
                    add(cid, "control", c.get("id"), c, text)
                    md.append(f"- {cid} | {c.get('title_fa')} | {c.get('control_statement_fa_short')} | {c.get('modality')}")
            md.append("")
        if qm:
            md += ["## Quality model items (VERBATIM terms/definitions)", ""]
            for q in qm.get("items", []):
                qid = f"Q{sh}-{q.get('id')}"
                text = f"{q.get('term_fa')} | {q.get('term_en')} | {q.get('change_for_ai')} — {q.get('definition_fa')}"
                add(qid, "quality", q.get("clause"), q, text)
                md.append(f"- {qid} | cl.{q.get('clause')} | {q.get('term_fa')} ({q.get('term_en')}) | AI-change: {q.get('change_for_ai')} | {q.get('definition_fa') or ''}")
            md.append("")
        if figs:
            md += ["## Tables and figures", ""]
            for n, f in enumerate(figs.get("items", []), 1):
                fid = unique(f"F{sh}-{f.get('id') or str(f.get('kind', 'x')) + str(n)}", index)
                rows = f.get("rows")
                text = f"{f.get('kind')} {f.get('id')} {f.get('title_fa')} — {f.get('summary_fa')}" + (f" ROWS: {json.dumps(rows, ensure_ascii=False)[:900]}" if rows else "")
                add(fid, "figure", f.get("id"), f, text)
                md.append(f"- {fid} | {f.get('kind')} | {f.get('title_fa')} | {page(f)} | {f.get('summary_fa')}" + (f"\n  rows: {json.dumps(rows, ensure_ascii=False)[:1200]}" if rows else ""))
            md.append("")
        unc = d / "uncertainties.md"
        if unc.is_file():
            md += ["## Uncertainties recorded by the extraction worker (respect them; do not resolve by guessing)", "", unc.read_text(encoding="utf-8")[:6000], ""]
            # each numbered line is also a citable item U<short>-<NN> (kind 'uncertainty'): the transcriber's own record of cross-reference slips, term variants,
            # keyword scans and things the document does NOT contain, verified against the page images when it was written
            for line in unc.read_text(encoding="utf-8").splitlines():
                m = re.match(r"^(\d+)\. (.+)$", line.strip())
                if m:
                    add(f"U{sh}-{int(m.group(1)):02d}", "uncertainty", None, {}, m.group(2)[:1200])
        (out / f"{sid}.md").write_text("\n".join(md), encoding="utf-8")
    ext = root / "evidence" / "external-items.json"
    if ext.is_file():      # externally verified public facts (e.g. official catalogue pages), each with URL and access date
        items = json.loads(ext.read_text(encoding="utf-8"))
        for item_id, it in items.items():
            index[item_id] = {k: it.get(k) for k in ("source_id", "kind", "clause", "pdf_page", "printed_page", "text", "url", "accessed")}
        (out / "EXTERNAL.md").write_text("# Externally verified public facts (read-only, dated)\n\n" + "\n".join(f"- {k} | {v['url']} | accessed {v['accessed']} | {v['text']}" for k, v in items.items()) + "\n", encoding="utf-8")
    (out / "index.json").write_text(json.dumps(index, ensure_ascii=False, indent=0), encoding="utf-8")
    return index


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    args = ap.parse_args()
    idx = build(Path(args.root))
    kinds: dict[str, int] = {}
    for v in idx.values():
        kinds[v["kind"]] = kinds.get(v["kind"], 0) + 1
    print(json.dumps({"items": len(idx), **kinds}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
