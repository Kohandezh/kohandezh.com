#!/usr/bin/env python3
"""Build the edition-2 glossary from the base glossary, curated decisions and page-image-verified INSO terms.

    python3 tools/e2_build_glossary.py --root <E2 root> --base-glossary <E1 translation_memory/glossary.json>

Inputs : config/e2-glossary-decisions.json, evidence/out/<ID>/terms.json, evidence/packets/index.json, manifests/national_standards.json
Outputs: translation_memory/glossary.json (version fa-v2; plugin contract preserved), evidence/glossary-e2-report.json
         (run `python3 tools/terms_mine.py export` afterwards to regenerate glossary.csv and sync the `terms` table).

Policy implemented here
  * The base glossary is never edited in place; every run starts from it, so the tool is idempotent.
  * Any entry whose DISPLAYED fields change (preferred/alternatives/rejected/definitions/acronym) is written as status
    'proposed' with no reviewer: old approval is NOT inherited. Entries that only gain evidence metadata keep their status.
  * New entries are always 'proposed'. Approval is a separate, recorded review step.
  * Source-specific definitions stay attributed (INSO national number, clause) and are never merged silently with the NIST one.
  * Conflicts that need a human decision are listed in the report ('unresolved_pairings'), never auto-resolved.
"""
from __future__ import annotations

import argparse
import functools
import copy
import json
import re
import sys
from collections import OrderedDict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
from e2_evidence_packets import short, unique  # noqa: E402
from e2_terms_compare import norm_en, norm_fa  # noqa: E402

DOMAIN = {"INSO-42001-1404": "management_system", "INSO-23682-1403": "governance", "INSO-23684-1403": "quality", "INSO-23681-1403": "machine_learning",
          "INSO-23728-1403": "privacy", "INSO-15816-3-1403": "data_governance", "INSO-23683-1403": "big_data", "INSO-23685-1403": "security"}
PRIORITY = ["INSO-42001-1404", "INSO-23682-1403", "INSO-23684-1403", "INSO-23681-1403", "INSO-23728-1403", "INSO-15816-3-1403", "INSO-23683-1403", "INSO-23685-1403"]
POS_MARK = re.compile(r"\s*\((?:اسم|فعل)\)\s*$")
# evidence items whose printed text is quoted beyond the default 300 characters because the term sits late in the sentence (a reviewer could not see it otherwise)
FULL_TEXT_REFS = {"S23728-095"}
DISPLAYED = ("english", "preferred_fa", "alternatives_fa", "rejected_fa", "definition_fa", "definition_en", "acronym")


def concept_id(english: str) -> str:
    cid = re.sub(r"[^A-Za-z0-9]+", "_", english).strip("_").upper()[:60]
    return cid if re.match(r"^[A-Z]", cid) else "T_" + cid


def parse_term(term_fa: str) -> tuple[str, list[str]]:
    """printed Persian term -> (preferred, aliases).

    Policy: the headword is the term AS PRINTED (a parenthetical gloss such as «(رگرسیون)» stays part of it); the form without the gloss is an alias; a
    single-word gloss next to a single-word term is also an alias. The INSO abbreviation «هومص» is expanded to «هوش مصنوعی» in the headword only; the
    printed form is kept as an alias. Part-of-speech markers «(اسم)»/«(فعل)» are dropped. Spacing before «(» is normalised."""
    t = POS_MARK.sub("", term_fa or "").strip()
    t = re.sub(r"(?<=\S)\(", " (", t)
    pref, aliases = t, []
    if "هومص" in pref:
        aliases.append(pref)
        pref = pref.replace("هومص (هوش مصنوعی)", "هوش مصنوعی").replace("هومص", "هوش مصنوعی")
    stripped = re.sub(r"\s*\([^()]*\)", "", pref).strip()
    if stripped and stripped != pref:
        aliases.append(stripped)
        glosses = re.findall(r"\(([^()]+)\)", pref)
        if len(glosses) == 1 and len(glosses[0].split()) == 1 and len(stripped.split()) == 1:
            aliases.append(glosses[0].strip())
    return pref, [a for a in dict.fromkeys(aliases) if a and a != pref]


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", required=True)
    ap.add_argument("--base-glossary", required=True)
    args = ap.parse_args()
    root = Path(args.root)
    dec = json.loads((root / "config" / "e2-glossary-decisions.json").read_text(encoding="utf-8"))
    base_doc = json.loads(Path(args.base_glossary).read_text(encoding="utf-8"))
    terms: "OrderedDict[str, dict]" = OrderedDict((k, copy.deepcopy(v)) for k, v in base_doc["terms"].items())
    registry = {s["doc_key"]: s for s in json.loads((root / "manifests" / "national_standards.json").read_text(encoding="utf-8"))["standards"]}
    index = json.loads((root / "evidence" / "packets" / "index.json").read_text(encoding="utf-8"))

    def nat_short(sid: str) -> str:
        r = registry[sid]
        return f"INSO {r['national']['number']}"

    # ---- verified term items with the same stable IDs as the evidence packets
    titems: dict[str, dict] = {}
    for d in sorted((root / "evidence" / "out").iterdir()):
        tf = d / "terms.json"
        if not tf.is_file():
            continue
        for rec in json.loads(tf.read_text(encoding="utf-8")).get("terms", []):
            tid = unique(f"T{short(d.name)}-{rec.get('clause')}", titems)
            titems[tid] = {"item_id": tid, "source_id": d.name, **rec}

    def ref_record(item_id: str) -> dict:
        if item_id in titems:
            t = titems[item_id]
            r = registry[t["source_id"]]
            return {"item_id": item_id, "source_id": t["source_id"], "national_reference": nat_short(t["source_id"]), "international_reference": r["international"]["designation"],
                    "clause": t.get("clause"), "term_fa_printed": t.get("term_fa"), "term_en_printed": t.get("term_en"), "definition_fa_printed": t.get("definition_fa"),
                    "pdf_page": t.get("pdf_page"), "printed_page": t.get("printed_page"), "source_sha256": r["sha256"]}
        it = index[item_id]
        r = registry[it["source_id"]]
        return {"item_id": item_id, "source_id": it["source_id"], "national_reference": nat_short(it["source_id"]), "international_reference": r["international"]["designation"],
                "clause": it.get("clause"), "text_printed": it["text"][: 700 if item_id in FULL_TEXT_REFS else 300], "pdf_page": it.get("pdf_page"), "printed_page": it.get("printed_page"), "source_sha256": r["sha256"]}

    def compose_inso(refs: list[dict], preferred: str = "", note: str = "") -> str:
        parts, seen = [], set()
        for r in refs:
            d = (r.get("definition_fa_printed") or "").strip()
            if d and d not in seen:
                seen.add(d)
                main = parse_term(r.get("term_fa_printed") or "")[0]
                if preferred and main and norm_fa(main) != norm_fa(preferred):
                    parts.append(f"تعریف پایهٔ «{main}» در استاندارد ملی ({r['national_reference']}، بند {r['clause']}): {d}")
                else:
                    parts.append(f"تعریف در استاندارد ملی ({r['national_reference']}، بند {r['clause']}): {d}")
        return " ".join(parts) + (" " + note if parts and note else "")

    report = {"deduplicated": [], "removed": [], "changed": [], "metadata_only": [], "new": [], "unresolved_pairings": [], "acronym_only": [], "skipped_abbreviations": 0}

    def merge_aliases(target: dict, donors: list[dict]) -> None:
        alts = list(target.get("alternatives_fa") or [])
        for d in donors:
            for x in [d.get("preferred_fa")] + list(d.get("alternatives_fa") or []):
                if x and x != target["preferred_fa"] and x not in alts and x not in (target.get("rejected_fa") or []):
                    alts.append(x)
            target["source_documents"] = sorted(set(target.get("source_documents") or []) | set(d.get("source_documents") or []))
        target["alternatives_fa"] = alts

    # ---- 0. hygiene: base-data defects that contradict the glossary's own policy (status preserved; everything listed in the report)
    hyg = dec.get("hygiene", {})
    base_snapshot = {k: {f: copy.deepcopy(v.get(f)) for f in DISPLAYED} for k, v in terms.items()}
    report["hygiene"] = []
    for k, e in terms.items():
        notes = []
        rej = list(e.get("rejected_fa") or [])
        if hyg.get("remove_preferred_from_rejected") and e["preferred_fa"] in rej:
            e["rejected_fa"] = rej = [x for x in rej if x != e["preferred_fa"]]
            notes.append("preferred form removed from rejected_fa (contradiction)")
        if hyg.get("remove_rejected_from_alternatives"):
            keep = [a for a in (e.get("alternatives_fa") or []) if a not in rej]
            if keep != (e.get("alternatives_fa") or []):
                notes.append("rejected form(s) removed from alternatives_fa: " + "، ".join(a for a in e["alternatives_fa"] if a in rej))
                e["alternatives_fa"] = keep
        for fld in ("rejected_fa", "alternatives_fa"):
            lst = list(e.get(fld) or [])
            fixed = [functools.reduce(lambda a, kv: a.replace(kv[0], kv[1]), hyg.get("zwnj_fixes", {}).items(), x) for x in lst]
            fixed = list(dict.fromkeys(fixed))
            if fixed != lst:
                notes.append(f"{fld}: " + ("missing ZWNJ restored " if any(a != b for a, b in zip(lst, fixed)) else "") + ("duplicates removed" if len(set(lst)) != len(lst) else ""))
                e[fld] = fixed
        if k in hyg.get("acronym_clear", []) and e.get("acronym"):
            notes.append(f"unrelated acronym «{e['acronym']}» cleared (term-mining artifact)")
            e["acronym"] = ""
        if notes:
            e.setdefault("e2", {"change": "hygiene"}).setdefault("hygiene", []).extend(notes)
            report["hygiene"].append({"key": k, "notes": notes})

    # ---- 1. de-duplicate shared concept IDs (not a semantic change; status preserved)
    for g in dec["dedupe"]:
        canon = terms[g["canonical_key"]]
        donors = [terms.pop(k) for k in g["drop"] if k in terms]
        merge_aliases(canon, donors)
        canon.setdefault("e2", {}).setdefault("merged_keys", []).extend(g["drop"])
        report["deduplicated"].append({"canonical_key": g["canonical_key"], "dropped": g["drop"], "reason": g["reason"]})
    # ---- 2. remove entries merged elsewhere
    for k, spec in dec["remove"].items():
        if k in terms:
            donor = terms.pop(k)
            merge_aliases(terms[spec["merge_into"]], [donor])
            report["removed"].append({"key": k, "merged_into": spec["merge_into"], "reason": spec["reason"]})

    def apply_change(key: str, spec: dict) -> None:
        e = terms[key]
        before = base_snapshot.get(key) or {f: copy.deepcopy(e.get(f)) for f in DISPLAYED}
        old_pref, old_status, old_reviewer, old_rationale = e["preferred_fa"], e["status"], e.get("reviewer"), e.get("rationale")
        refs = [ref_record(r) for r in spec.get("inso_refs", [])]
        alts = list(e.get("alternatives_fa") or [])
        rej = list(e.get("rejected_fa") or [])
        for x in spec.get("rejected_add", []):
            if x not in rej:
                rej.append(x)
        rej = [x for x in rej if x not in spec.get("rejected_remove", [])]
        if spec.get("preferred_fa") and spec["preferred_fa"] != old_pref:
            e["preferred_fa"] = spec["preferred_fa"]
            if old_pref not in alts and old_pref not in rej:
                alts.append(old_pref)
        for x in spec.get("alternatives_add", []):
            if x not in alts and x != e["preferred_fa"] and x not in rej:
                alts.append(x)
        alts = [x for x in alts if x not in spec.get("alternatives_remove", []) and x != e["preferred_fa"] and x not in rej]
        e["alternatives_fa"], e["rejected_fa"] = alts, rej
        if spec.get("acronym"):
            e["acronym"] = spec["acronym"]
        for fld, val in (spec.get("set") or {}).items():
            if fld not in ("definition_en", "definition_fa", "english"):
                raise SystemExit(f"'set' may only change definition_en/definition_fa/english, not {fld!r}")
            e[fld] = val
        mode = spec.get("definition_mode", "keep")
        e2 = e.setdefault("e2", {})
        NIST_LABEL = "تعریف در بافت NIST (متن‌های ترجمه‌شدهٔ این کتاب): "
        if mode in ("inso_only", "dual") and refs and compose_inso(refs, e["preferred_fa"]):
            old_fa, old_en = (e.get("definition_fa") or "").strip(), (e.get("definition_en") or "").strip()
            inso_text = compose_inso(refs, e["preferred_fa"], spec.get("definition_note_fa", ""))
            if old_fa and not old_fa.startswith("تعریف"):
                e["definition_fa"] = inso_text + f" {NIST_LABEL}{old_fa}"
                e["definition_en"] = f"NIST context: {old_en}" if old_en else ""
                e2["nist_definition_fa"], e2["nist_definition_en"] = old_fa, old_en
            else:
                e["definition_fa"] = inso_text
        elif mode == "keep" and (e.get("definition_fa") or "").strip() and not e["definition_fa"].startswith(("تعریف", NIST_LABEL)) \
                and all(s.startswith("NIST-") for s in (e.get("source_documents") or [])):
            e2["nist_definition_fa"], e2["nist_definition_en"] = e["definition_fa"], e.get("definition_en", "")
            e["definition_fa"] = NIST_LABEL + e["definition_fa"]
            if (e.get("definition_en") or "").strip():
                e["definition_en"] = "NIST context: " + e["definition_en"]
        if refs:
            e["source_documents"] = sorted(set(e.get("source_documents") or []) | {r["source_id"] for r in refs})
        e["rationale"] = spec["note_fa"]
        e2.update({"class": spec["class"], "old_preferred_fa": old_pref, "inso": refs, "old_rationale": old_rationale})
        after = {f: e.get(f) for f in DISPLAYED}
        if after != before:
            e["status"], e["reviewer"], e["review_mode"], e["reviewed_at"] = "proposed", None, None, None
            e2.update({"change": "changed", "previous_status": old_status, "previous_reviewer": old_reviewer})
            report["changed"].append({"key": key, "class": spec["class"], "preferred": [old_pref, e["preferred_fa"]], "previous_status": old_status})
        else:
            e2["change"] = "metadata_only"
            report["metadata_only"].append({"key": key, "class": spec["class"]})

    # ---- 3. curated changes
    claimed: set[str] = set()
    for key, spec in dec["changes"].items():
        if key not in terms:
            raise SystemExit(f"decision for unknown key {key!r}")
        apply_change(key, spec)
        claimed.update(spec.get("inso_refs", []))

    # ---- 4. pair remaining verified terms with base entries; create new entries otherwise
    by_en: dict[str, list[str]] = {}
    for k, v in terms.items():
        by_en.setdefault(norm_en(v.get("english", "")), []).append(k)
    used_cids = {v["concept_id"] for v in terms.values()}
    groups: "OrderedDict[str, list[dict]]" = OrderedDict()
    forced = dec.get("force_new", {})
    for tid, t in titems.items():
        if tid in claimed:
            continue
        en = (t.get("term_en") or "").strip()
        if tid in forced:
            groups.setdefault(forced[tid]["key"], []).append(t)
            continue
        if not en:
            report["skipped_abbreviations"] += 1
            continue
        hits = by_en.get(norm_en(en), [])
        if hits:
            base_key = hits[0]
            e = terms[base_key]
            if norm_fa(t["term_fa"]) == norm_fa(e["preferred_fa"]):
                e2 = e.setdefault("e2", {"change": "metadata_only", "class": "match"})
                e2.setdefault("inso", []).append(ref_record(tid))
                e["source_documents"] = sorted(set(e.get("source_documents") or []) | {t["source_id"]})
                if not any(m["key"] == base_key for m in report["metadata_only"]):
                    report["metadata_only"].append({"key": base_key, "class": "match"})
            elif not re.search(r"[\u0600-\u06FF]", t["term_fa"] or ""):
                report["acronym_only"].append({"item": tid, "printed": t["term_fa"], "term_en": en, "base_key": base_key, "note": "the standard prints only the Latin acronym; no official Persian term"})
            else:
                report["unresolved_pairings"].append({"item": tid, "term_fa": t["term_fa"], "term_en": en, "base_key": base_key, "base_preferred_fa": e["preferred_fa"]})
            continue
        if not re.search(r"[\u0600-\u06FF]", t["term_fa"] or ""):
            report["acronym_only"].append({"item": tid, "printed": t["term_fa"], "term_en": en, "base_key": None, "note": "the standard prints only the Latin acronym; no Persian term, no entry created"})
            continue
        if t.get("entry_type") == "abbreviation" or not (t.get("definition_fa") or "").strip():
            report["skipped_abbreviations"] += 1          # abbreviation-table rows carry no definition: not glossary concepts by themselves
            continue
        groups.setdefault(norm_en(en), []).append(t)

    for gkey, items in groups.items():
        items.sort(key=lambda t: (t["item_id"] not in forced, PRIORITY.index(t["source_id"]) if t["source_id"] in PRIORITY else 99))
        first = items[0]
        f = forced.get(first["item_id"], {})
        english = f.get("english") or re.sub(r"\s*\((?:noun|verb)\)\s*$", "", first["term_en"]).strip() or first["term_en"]
        if f.get("english"):
            english = f["english"]
        pref, aliases = parse_term(f.get("preferred_fa") or first["term_fa"])
        pref = f.get("preferred_fa") or pref
        variants: list[str] = []
        for it in items[1:]:
            p2, a2 = parse_term(it["term_fa"])
            for x in [p2] + a2:
                if x != pref and x not in aliases and x not in variants:
                    variants.append(x)
        refs = [ref_record(it["item_id"]) for it in items]
        key = f.get("key") or gkey
        cid = concept_id(english)
        if cid in used_cids:
            cid = f"{cid}_{short(first['source_id']).replace('-', '_')}"
        used_cids.add(cid)
        cls = "new-entry" if not variants else "new-entry+inter-standard-variation"
        entry = OrderedDict(concept_id=cid, english=english, acronym=f.get("acronym", ""), preferred_fa=pref, alternatives_fa=[x for x in dict.fromkeys(aliases + variants + f.get("extra_alternatives", [])) if x != pref], rejected_fa=[], definition_en="",
                            definition_fa=compose_inso(refs, pref), domain=DOMAIN.get(first["source_id"], "standards"), source_documents=sorted({it["source_id"] for it in items}),
                            status="proposed", reviewer=None, review_mode=None,
                            rationale="اصطلاح تعریف‌شده در " + " و ".join(dict.fromkeys(f"{r['national_reference']} بند {r['clause']}" for r in refs)) + "؛ بازبینی‌نشده.",
                            high_impact=False, reviewed_at=None)
        entry["e2"] = {"change": "new", "class": cls, "old_preferred_fa": None, "inso": refs}
        if variants:
            entry["e2"]["variants_note_fa"] = "صورت‌های دیگر در استانداردهای ملی دیگر: " + "، ".join(variants)
        terms[key] = entry
        report["new"].append({"key": key, "preferred_fa": pref, "sources": [r["source_id"] for r in refs], "class": cls})

    # ---- 5. curated additions to freshly created entries (e.g. a printed variant from another standard)
    for key, spec in dec.get("new_overrides", {}).items():
        e = terms[key]
        refs = [ref_record(r) for r in spec.get("extra_refs", [])]
        e["e2"]["inso"] = e["e2"].get("inso", []) + refs
        e["alternatives_fa"] = [x for x in dict.fromkeys(e["alternatives_fa"] + [x for x in spec.get("alternatives_add", []) if x != e["preferred_fa"]]) if x not in spec.get("alternatives_remove", [])]
        e["source_documents"] = sorted(set(e["source_documents"]) | {r["source_id"] for r in refs})
        if spec.get("note_fa"):
            e["rationale"] = e["rationale"] + " " + spec["note_fa"]
            if spec.get("alternatives_add"):
                e["e2"]["class"] = "new-entry+inter-standard-variation"

    out = OrderedDict(glossary_version=dec["glossary_version"], edition_id="2026E2", base_glossary_version=base_doc.get("glossary_version"), terms=terms)
    (root / "translation_memory" / "glossary.json").write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    counts = {"base_terms": len(base_doc["terms"]), "e2_terms": len(terms), "deduplicated_groups": len(report["deduplicated"]), "removed": len(report["removed"]),
              "changed": len(report["changed"]), "metadata_only": len(report["metadata_only"]), "new": len(report["new"]),
              "unresolved_pairings": len(report["unresolved_pairings"]), "acronym_only": len(report["acronym_only"]), "hygiene": len(report["hygiene"]), "skipped_abbreviation_rows": report["skipped_abbreviations"]}
    (root / "evidence" / "glossary-e2-report.json").write_text(json.dumps({"counts": counts, **report}, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(json.dumps(counts, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())
