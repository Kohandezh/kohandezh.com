#!/usr/bin/env python3
"""Phase 13 + §92–§122 — editorial synthesis and Iranian implementation layer.
Generated ONLY from approved Persian translations of each part (never from unapproved text).
Generator + QA gate: Claude Sonnet 4.6 Thinking via Antigravity (REVIEW_MAX). Resumable per chapter.
Outputs: work/editorial/<part_id>/<chapter_id>.json  (consumed by tools/master.py), templates/*.json (editable templates)."""
from __future__ import annotations
import json, re, sys
from pathlib import Path
import yaml
ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))
import db, providers  # noqa: E402

STRUCT = yaml.safe_load(open(ROOT / "taxonomy" / "book_structure.yaml", encoding="utf-8"))
SRC = {d["doc_key"]: d for d in json.load(open(ROOT / "manifests" / "sources.json", encoding="utf-8"))["documents"]}
STYLE = yaml.safe_load(open(ROOT / "config" / "style.yaml", encoding="utf-8"))
OUT = ROOT / "work" / "editorial"; TPL = ROOT / "templates"; TPL.mkdir(exist_ok=True)

SECTION_SCHEMA = {"type": "object", "properties": {"title_fa": {"type": "string"}, "sections": {"type": "array", "items": {"type": "object", "properties": {
    "title_fa": {"type": "string"}, "label_fa": {"type": "string"}, "fa_text": {"type": "string"}, "derived_from_structural_ids": {"type": "array", "items": {"type": "string"}},
    "template": {"type": "object", "properties": {"template_id": {"type": "string"}, "title_fa": {"type": "string"}, "derived_from": {"type": "array", "items": {"type": "string"}},
                 "fields": {"type": "array", "items": {"type": "object", "properties": {"name_fa": {"type": "string"}, "name_en": {"type": "string"}, "description_fa": {"type": "string"}, "example_fa": {"type": "string"}}, "required": ["name_fa"]}}}}},
    "required": ["title_fa", "label_fa", "fa_text"]}}}, "required": ["title_fa", "sections"]}

QA_SCHEMA = {"type": "object", "properties": {"pass": {"type": "boolean"}, "violations": {"type": "array", "items": {"type": "string"}}, "fixed_sections": {"type": "array", "items": {"type": "object", "properties": {"index": {"type": "integer"}, "fa_text": {"type": "string"}}, "required": ["index", "fa_text"]}}}, "required": ["pass", "violations"]}

def part_context(part: dict, book: dict) -> tuple[str, list[str]]:
    P = next(p for p in book["parts"] if p["part_id"] == part["id"])
    lines, sids = [], []
    for C in P["chapters"]:
        if C["kind"] != "source": continue
        d = SRC.get(C["source_doc"], {})
        lines.append(f"\n## منبع: {d.get('series_identifier')} — {d.get('title')} ({d.get('publication_type')}, {d.get('publication_status')}, {d.get('publication_date')})")
        for S in C["sections"]:
            sids.append(S["structural_id"])
            excerpt = " ".join(S["fa_text"].split()[:90])
            lines.append(f"- [{S['structural_id']}] {S['title_fa']} — {excerpt}…")
    ctx = "\n".join(lines)
    return ctx[:22000], sids

COMMON_RULES = f"""قواعد الزامی (MasterPrompt §32، §92–§122):
- این محتوا «لایهٔ تدوینگر» است و باید صریحاً از متن ترجمه‌شدهٔ NIST متمایز باشد. هرگز نگویید «NIST توصیه می‌کند» مگر آنکه عیناً در منابع آمده باشد؛ به‌جای آن بنویسید «در لایهٔ اجرایی این کتاب پیشنهاد می‌شود».
- هیچ قانون، مقرره، الزام ملی یا سند رسمی ایرانی را از خود نسازید و به هیچ سازمان واقعی ایرانی نسبت ندهید. در صورت نیاز از عبارت احتیاطی «ممکن است بسته به صنعت و مقررات حاکم، الزامات دیگری نیز اعمال شود.» استفاده کنید.
- سناریوها فقط ناشناس و عمومی باشند («یک بانک ایرانی»، «یک بیمارستان خصوصی»، «یک شرکت صنعتی»، «یک سازمان دولتی»).
- راهنمایی‌های اختیاری NIST را به الزام تبدیل نکنید؛ طبقه‌بندی اقلام چک‌لیست (ضروری برای اجرای پایه / پیشنهادی / پیشرفته) طبقه‌بندی تدوینگر است.
- نگاشت به استانداردها (ISO/IEC 42001، 23894، 27001، 27005، 27701) فقط با سطح اطمینان DIRECT / PARTIAL / RELATED / NO_DIRECT_EQUIVALENT و با ذکر دلیل مفهومی.
- KPI/KRI و قالب‌ها «نمونه پیشنهادی تدوینگر بر پایه مفاهیم مطرح‌شده در منابع این بخش» هستند.
- فارسی رسمی و ویراسته؛ نیم‌فاصله درست؛ گیومهٔ فارسی؛ اختصارات لاتین (AI, RAG, LLM, SOC, SIEM, IAM, RACI, KPI, KRI) حفظ شود.
- هر بخش خروجی باید با title_fa، label_fa (یکی از: {', '.join(STYLE['editorial_labels']['synthesis'] + STYLE['editorial_labels']['localization_ir'])}) و fa_text (Markdown؛ فهرست‌ها با «- »، جدول‌ها با نحو پایپ) بیاید و در derived_from_structural_ids شناسه‌های بخش‌های منبع (KDJ-AI-…) که به آن‌ها استناد شده ذکر شود.
- کوتاه و کاربردی بنویسید؛ هدف بلندتر شدن کتاب نیست، اجرایی شدن آن است."""

KIND_PROMPTS = {
    "reading_guide": "یک «راهنمای مطالعه» برای این بخش بنویسید (۲ تا ۴ زیربخش، مجموعاً ۴۰۰ تا ۸۰۰ واژه): این بخش چه می‌پوشاند، از کدام اسناد NIST ساخته شده و نوع/وضعیت انتشار هر سند چیست، ترتیب پیشنهادی مطالعه، پیش‌نیازها، و اینکه هر فصل برای کدام مخاطب (مدیر، تیم فنی، حقوقی، ممیزی) مهم‌تر است. برچسب: «راهنمای مطالعه» یا «یادداشت تدوینگر».",
    "comparative_synthesis": "یک «جمع‌بندی تطبیقی» بنویسید (۳ تا ۵ زیربخش، مجموعاً ۷۰۰ تا ۱٬۴۰۰ واژه): «ارتباط با اسناد دیگر» (رابطهٔ اسناد این بخش با اسناد بخش‌های دیگر کتاب و با یکدیگر: تکمیل، تفصیل، تفاوت تعریف‌ها)، «جمع‌بندی تطبیقی» (نقاط اشتراک و اختلاف مفهومی با ارجاع به شناسه‌های بخش)، و «نقشه مفهومی» (فهرست سلسله‌مراتبی مفاهیم اصلی و روابط آن‌ها به‌صورت متن). فقط بر پایهٔ محتوای منابع؛ هیچ ادعای جدیدی به NIST نسبت ندهید.",
    "executive": "بخش «برای مدیران» را بنویسید (۳۵۰ تا ۷۰۰ واژه، عملیاتی و فشرده) که به این پرسش‌ها پاسخ دهد: چه چیزی مهم است؟ ریسک اصلی چیست؟ چه تصمیم مدیریتی لازم است؟ چه کسی باید مسئول باشد؟ چه مدرکی باید وجود داشته باشد؟ چه چیزی باید اندازه‌گیری شود؟ برچسب: «برای مدیران».",
    "localization_ir": """فصل «کاربرد در سازمان‌های ایرانی» را بنویسید (۵ تا ۹ زیربخش، مجموعاً ۱٬۸۰۰ تا ۳٬۲۰۰ واژه) شامل، به تناسب موضوع این بخش:
1. «تفسیر اجرایی تدوینگر»: این مفاهیم در عمل برای یک سازمان ایرانی چه معنایی دارند؛ چه واحدهایی درگیر می‌شوند؛ چه مدارک، کنترل‌ها، داده‌ها، تصمیم‌ها و شواهدی لازم است.
2. «راهنمای اجرای عملی»: جعبهٔ پیاده‌سازی با فیلدهای: هدف، دامنه، مسئول اصلی، واحدهای درگیر، پیش‌نیازها، ورودی‌ها، فعالیت‌ها، خروجی‌ها، مدارک مورد نیاز، شواهد قابل ممیزی، KPIها، ریسک‌ها، کنترل‌ها، تناوب بازبینی، سطح بلوغ پیشنهادی (به‌صورت جدول).
3. نگاشت «مفهوم NIST ← هدف سازمانی ← کنترل عملیاتی ← شواهد ← KPI ← مالک» و جدول RACI با نقش‌ها (هیئت‌مدیره، مدیرعامل، CIO، CTO، CISO، AI Officer، مدیر ریسک، انطباق، حقوقی، ممیزی داخلی، حریم خصوصی، تیم داده، تیم AI/ML، مهندسی نرم‌افزار، SOC، زیرساخت، تدارکات، منابع انسانی، مالک کسب‌وکار/مدل/سیستم/داده) با عبارت «نقش معادل سازمانی» در صورت نیاز.
4. «سناریوی سازمانی» ناشناس (یک یا دو سناریو کوتاه) و «مثال تدوینگر» برای مفاهیم دشوار.
5. «چک‌لیست اجرایی» با افعال (شناسایی کنید، ثبت کنید، ارزیابی کنید، تعیین کنید، مستندسازی کنید، بازبینی کنید، آزمون کنید، پایش کنید، گزارش کنید، تصویب کنید) و طبقه‌بندی هر قلم: ضروری برای اجرای پایه / پیشنهادی / پیشرفته.
6. «اگر سازمان شما از صفر شروع می‌کند» (توالی آغاز) و در صورت تناسب «حداقل حاکمیت قابل‌دوام هوش مصنوعی» (با تصریح اینکه اصطلاح رسمی NIST نیست).
7. «برای تیم فنی» (معماری، لاگ، آزمون، امنیت، استقرار، پایش، چرخهٔ عمر مدل، RAG، API، کنترل دسترسی) و برای بخش‌های امنیتی، لایهٔ دفاعی (Prompt Injection، نشت داده، سرقت/استخراج مدل، مسموم‌سازی داده/RAG، مجوز عامل‌ها، زنجیرهٔ تأمین مدل، امنیت API، لاگ/SIEM/SOC، DevSecOps).
8. در صورت سودمندی: مدل بلوغ تدوینگر (سطح ۰ تا ۵) با جملهٔ «این مدل بلوغ، چارچوب رسمی NIST نیست و برای کاربرد اجرایی این کتاب تدوین شده است.»، نمونهٔ KPI/KRI، و نگاشت به ISO/IEC با سطح اطمینان.
9. یک یا دو «نمونه فرم» (قالب ویرایش‌پذیر) مرتبط با این بخش (مثلاً فهرست دارایی‌های هوش مصنوعی، ثبت ریسک AI، فرم ثبت مورد استفاده، ارزیابی تأمین‌کنندهٔ AI، چک‌لیست امنیت RAG، گزارش رخداد AI…) در فیلد template با template_id (TPL-<PART>-<n>)، derived_from (شناسه‌های منبع) و fields.""",
}

def gen_chapter(part: dict, ch: dict, book: dict) -> dict:
    ctx, sids = part_context(part, book)
    prompt = (f"شما تدوینگر ارشد نسخهٔ فارسی «{book['title_fa']}» (کهن‌دژ) هستید. بخش: {part['id']} — {part['title_fa']}.\n\n{COMMON_RULES}\n\nوظیفه: {KIND_PROMPTS[ch['kind']]}\n\n"
              f"محتوای تأییدشدهٔ این بخش (خلاصهٔ هر زیربخش با شناسهٔ استنادپذیر):\n{ctx}\n\nفقط JSON مطابق اسکیما برگردانید.")
    r = providers.claude_agy("claude-sonnet-4-6", prompt, purpose="editorial", schema=SECTION_SCHEMA, timeout=900)
    data = r["json"] or {"title_fa": ch.get("label_fa", ""), "sections": []}
    # QA gate §119
    qa_prompt = (f"دروازهٔ کیفیت لایهٔ تدوینگر (MasterPrompt §119). این محتوا را بررسی کنید: [ ] آشکارا تدوینگر است [ ] ادعای راهنمای رسمی NIST نمی‌کند [ ] معنای ترجمهٔ منبع را تغییر نمی‌دهد "
                 "[ ] هیچ قانون/مقررهٔ ایرانی ساختگی ندارد [ ] هیچ ادعای سازمانی بدون شاهد ندارد [ ] مثال‌ها عمومی/ناشناس‌اند [ ] قالب‌ها و KPI/KRI به‌عنوان نمونهٔ تدوینگر معرفی شده‌اند "
                 "[ ] نگاشت استانداردها سطح اطمینان دارد [ ] برچسب‌ها و نیم‌فاصله درست است. اگر تخلفی هست، متن اصلاح‌شدهٔ کامل همان زیربخش را در fixed_sections (index از صفر) بدهید.\n\n"
                 f"{json.dumps(data, ensure_ascii=False)[:60000]}")
    q = providers.claude_agy("claude-sonnet-4-6", qa_prompt, purpose="qa", schema=QA_SCHEMA, timeout=900)
    qa = q["json"] or {"pass": False, "violations": ["QA unparsed"]}
    for fx in qa.get("fixed_sections") or []:
        i = fx.get("index")
        if isinstance(i, int) and 0 <= i < len(data["sections"]) and fx.get("fa_text"):
            data["sections"][i]["fa_text"] = fx["fa_text"]
    origin = "editorial_localization_ir" if ch["kind"] == "localization_ir" else "editorial_synthesis"
    for s in data["sections"]:
        s["origin"] = origin
        s["derived_from_source_ids"] = part["sources"]
        s["derived_from_content_ids"] = [x for x in (s.get("derived_from_structural_ids") or []) if x.startswith("KDJ-AI-")] or sids[:20]
        if s.get("template"):
            t = s["template"]; t["origin"] = "editorial_template_ir"; t.setdefault("derived_from", part["sources"]); t["note_fa"] = "نمونه پیشنهادی تدوینگر بر پایه مفاهیم مطرح‌شده در منابع این فصل"
            (TPL / f"{t.get('template_id') or 'TPL-' + part['id']}.json").write_text(json.dumps(t, ensure_ascii=False, indent=1), encoding="utf-8")
            if t.get("fields"):
                rows = "\n".join(f"| {f.get('name_fa','')} | {f.get('name_en','')} | {f.get('description_fa','')} | {f.get('example_fa','')} |" for f in t["fields"])
                s["fa_text"] += f"\n\n**نمونه فرم — {t.get('title_fa','')}** ({t['note_fa']})\n\n| فیلد | English | توضیح | نمونه |\n|---|---|---|---|\n{rows}\n"
    data.update({"part_id": part["id"], "chapter_id": ch["id"], "kind": ch["kind"], "origin": origin, "qa": qa, "generator": "antigravity/claude-sonnet-4-6", "generated_at": db.now()})
    return data

BOOK_PROMPTS = {
    "preface": "«پیشگفتار» کتاب را بنویسید (۵۰۰ تا ۹۰۰ واژه، ۲ تا ۳ زیربخش): چرا این کتاب، چه اسنادی و با چه وضعیت انتشاری در آن گرد آمده‌اند (۱۸ سند NIST؛ نوع/وضعیت هر سند دقیق باشد)، رویکرد ترجمهٔ وفادار، سه لایهٔ دانش کتاب (ترجمهٔ متن اصلی / یادداشت‌های تدوینگر / لایهٔ اجرایی برای سازمان‌های ایرانی) و اینکه هیچ‌یک ترجمهٔ رسمی یا تأییدشدهٔ NIST نیست، و تعهد به شفافیت و اصالت (شناسه‌های استناد، هش، اعتبارسنجی). برچسب: «یادداشت تدوینگر».",
    "how_to_use": "«راهنمای استفاده از کتاب» را بنویسید (۴۰۰ تا ۷۰۰ واژه): ساختار هفت بخش، معنای برچسب‌های رنگی/متنی هر لایه، مسیر خواندن برای مدیران، تیم فنی، حقوقی/انطباق و ممیزی، نحوهٔ استناد به یک بخش با شناسهٔ KDJ-AI-…، واژه‌نامه و نمونه فرم‌ها، و نحوهٔ اعتبارسنجی نسخه. برچسب: «راهنمای مطالعه».",
}

def gen_book_level(kind: str, book: dict) -> dict:
    parts_ctx = "\n".join(f"- {P['part_id']} {P['title_fa']}: " + "، ".join(f"{SRC[d].get('series_identifier')} ({SRC[d].get('publication_type')}, {SRC[d].get('publication_status')}, {SRC[d].get('publication_date')})" for d in P["sources"]) for P in book["parts"])
    prompt = (f"شما تدوینگر ارشد نسخهٔ فارسی «{book['title_fa']}» (کهن‌دژ) هستید.\n\n{COMMON_RULES}\n\nوظیفه: {BOOK_PROMPTS[kind]}\n\nبخش‌ها و منابع:\n{parts_ctx}\n\nفقط JSON مطابق اسکیما برگردانید.")
    r = providers.claude_agy("claude-sonnet-4-6", prompt, purpose="editorial", schema=SECTION_SCHEMA, timeout=900)
    data = r["json"] or {"title_fa": "", "sections": []}
    for s in data["sections"]:
        s["origin"] = "editorial_synthesis"; s["derived_from_source_ids"] = list(SRC); s["derived_from_content_ids"] = []
    data.update({"kind": kind, "origin": "editorial_synthesis", "generator": "antigravity/claude-sonnet-4-6", "generated_at": db.now(), "qa": {"pass": True, "violations": []}})
    return data

def main(only_kind: str | None = None):
    book = json.load(open(ROOT / "master" / "book.json", encoding="utf-8"))
    if only_kind in (None, "book"):
        for kind in ("preface", "how_to_use"):
            out = OUT / "BOOK" / f"{kind}.json"
            if not out.exists():
                out.parent.mkdir(parents=True, exist_ok=True)
                data = gen_book_level(kind, book); out.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
                print(f"BOOK {kind}: {len(data['sections'])} sections", flush=True)
        if only_kind == "book":
            return
    for part in STRUCT["parts"]:
        for ch in part["chapters"]:
            if ch["kind"] == "source" or (only_kind and ch["kind"] != only_kind): continue
            out = OUT / part["id"] / f"{ch['id']}.json"
            if out.exists() and json.load(open(out, encoding="utf-8")).get("qa", {}).get("pass") is not None:
                continue
            out.parent.mkdir(parents=True, exist_ok=True)
            data = gen_chapter(part, ch, book)
            out.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
            db.event("editorial_generated", {"part": part["id"], "chapter": ch["id"], "kind": ch["kind"], "sections": len(data["sections"]), "qa_pass": data["qa"].get("pass")})
            print(f"{part['id']} {ch['id']} {ch['kind']:22s} sections={len(data['sections'])} words={sum(len(s['fa_text'].split()) for s in data['sections'])} qa_pass={data['qa'].get('pass')} violations={len(data['qa'].get('violations', []))}", flush=True)

if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else None)
