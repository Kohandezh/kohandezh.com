#!/usr/bin/env python3
"""Editorial / localization layer via in-session Claude subagents (MasterPrompt §13, §32, §92–§122).

  list                               → chapters to generate and their status
  prompt PART CHAPTER                → writes the generation prompt (+schema) to work/editorial_prompts/<PART>_<CHAPTER>.gen.md
  submit PART CHAPTER JSON           → validates & stores the generated chapter (qa pending), extracts templates
  qa-prompt PART CHAPTER             → writes the §119 quality-gate prompt for the stored chapter
  qa-submit PART CHAPTER JSON        → applies fixes / records verdict (qa pass|fail)
Book-level pieces use PART=BOOK and CHAPTER=preface|how_to_use."""
from __future__ import annotations
import argparse, os, json, sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db  # noqa: E402
import editorial as ed  # noqa: E402

import os
AGENT_LABEL = os.environ.get("EDITORIAL_AGENT_LABEL", "unspecified-agent")  # e.g. opencode-agent/glm-5.3 or claude-code-subagent/sonnet
PD = ROOT / "work" / "editorial_prompts"; PD.mkdir(parents=True, exist_ok=True)
OUT = ed.OUT

def book():
    return json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))

def targets():
    t = [("BOOK", "preface", "preface", None), ("BOOK", "how_to_use", "how_to_use", None)]
    for part in ed.STRUCT["parts"]:
        for ch in part["chapters"]:
            if ch["kind"] != "source":
                t.append((part["id"], ch["id"], ch["kind"], part))
    return t

def status_of(part_id, ch_id):
    p = OUT / part_id / f"{ch_id}.json"
    if not p.exists(): return "TODO"
    d = json.load(open(p, encoding="utf-8")); q = d.get("qa") or {}
    return "DONE" if q.get("pass") else ("QA_PENDING" if q.get("pass") is None else "QA_FAILED")

def cmd_list():
    for part_id, ch_id, kind, _ in targets():
        print(json.dumps({"part": part_id, "chapter": ch_id, "kind": kind, "status": status_of(part_id, ch_id)}))

def cmd_prompt(part_id, ch_id):
    b = book()
    if part_id == "BOOK":
        parts_ctx = "\n".join(f"- {P['part_id']} {P['title_fa']}: " + "، ".join(f"{ed.SRC[d].get('series_identifier')} ({ed.SRC[d].get('publication_type')}, {ed.SRC[d].get('publication_status')}, {ed.SRC[d].get('publication_date')})" for d in P["sources"]) for P in b["parts"])
        prompt = f"شما تدوینگر ارشد نسخهٔ فارسی «{b['title_fa']}» (کهن‌دژ) هستید.\n\n{ed.COMMON_RULES}\n\nوظیفه: {ed.BOOK_PROMPTS[ch_id]}\n\nبخش‌ها و منابع:\n{parts_ctx}"
        kind = ch_id
    else:
        part = next(p for p in ed.STRUCT["parts"] if p["id"] == part_id); ch = next(c for c in part["chapters"] if c["id"] == ch_id)
        ctx, sids = ed.part_context(part, b)
        prompt = (f"شما تدوینگر ارشد نسخهٔ فارسی «{b['title_fa']}» (کهن‌دژ) هستید. بخش: {part['id']} — {part['title_fa']}.\n\n{ed.COMMON_RULES}\n\nوظیفه: {ed.KIND_PROMPTS[ch['kind']]}\n\n"
                  f"محتوای تأییدشدهٔ این بخش (خلاصهٔ هر زیربخش با شناسهٔ استنادپذیر):\n{ctx}")
        kind = ch["kind"]
    path = PD / f"{part_id}_{ch_id}.gen.md"
    path.write_text("<!-- JSON SCHEMA FOR YOUR OUTPUT -->\n" + json.dumps(ed.SECTION_SCHEMA, ensure_ascii=False) + "\n\n<!-- PROMPT -->\n" + prompt + "\n\nفقط JSON مطابق اسکیما تولید کنید و آن را در فایل خروجی بنویسید.", encoding="utf-8")
    print(json.dumps({"part": part_id, "chapter": ch_id, "kind": kind, "prompt_file": str(path), "output_file_suggestion": str(PD / f"{part_id}_{ch_id}.gen.json")}, ensure_ascii=False))

def cmd_submit(part_id, ch_id, jpath):
    data = json.load(open(jpath, encoding="utf-8"))
    if not isinstance(data.get("sections"), list) or not data["sections"]:
        raise SystemExit("sections[] required")
    if part_id == "BOOK":
        origin, kind, sids = "editorial_synthesis", ch_id, []
        sources = list(ed.SRC)
    else:
        part = next(p for p in ed.STRUCT["parts"] if p["id"] == part_id); ch = next(c for c in part["chapters"] if c["id"] == ch_id)
        origin = "editorial_localization_ir" if ch["kind"] == "localization_ir" else "editorial_synthesis"; kind = ch["kind"]
        _, sids = ed.part_context(part, book()); sources = part["sources"]
    for s in data["sections"]:
        s["origin"] = origin; s["derived_from_source_ids"] = sources
        s["derived_from_content_ids"] = [x for x in (s.get("derived_from_structural_ids") or []) if str(x).startswith("KDJ-AI-")] or sids[:20]
        if s.get("template"):
            t = s["template"]; t["origin"] = "editorial_template_ir"; t.setdefault("derived_from", sources); t["note_fa"] = "نمونه پیشنهادی تدوینگر بر پایه مفاهیم مطرح‌شده در منابع این فصل"
            tid = t.get("template_id") or f"TPL-{part_id}-{ch_id}"
            (ed.TPL / f"{tid}.json").write_text(json.dumps(t, ensure_ascii=False, indent=1), encoding="utf-8")
            if t.get("fields") and "نمونه فرم —" not in s["fa_text"]:
                rows = "\n".join(f"| {f.get('name_fa','')} | {f.get('name_en','')} | {f.get('description_fa','')} | {f.get('example_fa','')} |" for f in t["fields"])
                s["fa_text"] += f"\n\n**نمونه فرم — {t.get('title_fa','')}** ({t['note_fa']})\n\n| فیلد | English | توضیح | نمونه |\n|---|---|---|---|\n{rows}\n"
    data.update({"part_id": part_id, "chapter_id": ch_id, "kind": kind, "origin": origin, "qa": {"pass": None, "violations": []}, "generator": AGENT_LABEL, "generated_at": db.now()})
    out = OUT / part_id / f"{ch_id}.json"; out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("editorial_generated", {"part": part_id, "chapter": ch_id, "kind": kind, "sections": len(data["sections"]), "generator": "subagent"})
    print(json.dumps({"stored": str(out), "sections": len(data["sections"]), "words": sum(len(s["fa_text"].split()) for s in data["sections"])}, ensure_ascii=False))

def cmd_qa_prompt(part_id, ch_id):
    p = OUT / part_id / f"{ch_id}.json"; data = json.load(open(p, encoding="utf-8"))
    prompt = ("دروازهٔ کیفیت لایهٔ تدوینگر (MasterPrompt §119). این محتوا را بررسی کنید: [ ] آشکارا تدوینگر است [ ] ادعای راهنمای رسمی NIST نمی‌کند [ ] معنای ترجمهٔ منبع را تغییر نمی‌دهد "
              "[ ] هیچ قانون/مقررهٔ ایرانی ساختگی ندارد [ ] هیچ ادعای سازمانی بدون شاهد ندارد [ ] مثال‌ها عمومی/ناشناس‌اند [ ] قالب‌ها و KPI/KRI به‌عنوان نمونهٔ تدوینگر معرفی شده‌اند "
              "[ ] نگاشت استانداردها سطح اطمینان دارد [ ] برچسب‌ها و نیم‌فاصله درست است [ ] فارسی رسمی و ویراسته است. اگر تخلفی هست، متن اصلاح‌شدهٔ کامل همان زیربخش را در fixed_sections (index از صفر) بدهید.\n\n"
              + json.dumps({"title_fa": data.get("title_fa"), "sections": [{"title_fa": s["title_fa"], "label_fa": s.get("label_fa"), "fa_text": s["fa_text"]} for s in data["sections"]]}, ensure_ascii=False))
    path = PD / f"{part_id}_{ch_id}.qa.md"
    path.write_text("<!-- JSON SCHEMA FOR YOUR OUTPUT -->\n" + json.dumps(ed.QA_SCHEMA, ensure_ascii=False) + "\n\n<!-- PROMPT -->\n" + prompt, encoding="utf-8")
    print(json.dumps({"part": part_id, "chapter": ch_id, "prompt_file": str(path), "output_file_suggestion": str(PD / f"{part_id}_{ch_id}.qa.json")}, ensure_ascii=False))

def cmd_qa_submit(part_id, ch_id, jpath):
    qa = json.load(open(jpath, encoding="utf-8")); p = OUT / part_id / f"{ch_id}.json"; data = json.load(open(p, encoding="utf-8"))
    for fx in qa.get("fixed_sections") or []:
        i = fx.get("index")
        if isinstance(i, int) and 0 <= i < len(data["sections"]) and fx.get("fa_text"):
            data["sections"][i]["fa_text"] = fx["fa_text"]
    data["qa"] = {"pass": bool(qa.get("pass")) or bool(qa.get("fixed_sections")), "violations": qa.get("violations", []), "reviewer": AGENT_LABEL, "at": db.now()}
    p.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    db.event("editorial_qa", {"part": part_id, "chapter": ch_id, "pass": data["qa"]["pass"], "violations": len(data["qa"]["violations"])})
    print(json.dumps(data["qa"], ensure_ascii=False)[:400])

if __name__ == "__main__":
    ap = argparse.ArgumentParser(); ap.add_argument("cmd", choices=["list", "prompt", "submit", "qa-prompt", "qa-submit"]); ap.add_argument("args", nargs="*")
    a = ap.parse_args()
    {"list": lambda: cmd_list(), "prompt": lambda: cmd_prompt(*a.args), "submit": lambda: cmd_submit(*a.args), "qa-prompt": lambda: cmd_qa_prompt(*a.args), "qa-submit": lambda: cmd_qa_submit(*a.args)}[a.cmd]()
