# Writer brief — edition 2 standards chapters (read fully before writing)

You draft ONE (or two) chapter(s) of the Persian book «راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی» (edition 2, 2026E2) from
**verified evidence** about an Iranian national standard (INSO). You do NOT translate the standard. You explain it, in your own words,
for Persian-speaking governance / risk / security / engineering readers, with precise attribution. Your draft will be reviewed by an
independent policy reviewer against the evidence, then by the editor-in-chief. Unsupported claims will be deleted.

## Files (all absolute; staging root = /Users/emperor/Documents/AI/kohandezh-archive/ai-governance-e2)
- Evidence packet: `<root>/evidence/packets/<SOURCE-ID>.md` (read all of it, including the "Uncertainties" section at the end)
- Citable item index (used by the validator): `<root>/evidence/packets/index.json`
- Terminology decisions + hard content rules: `<root>/evidence/terminology-decisions.md`  ← binding
- E1 section index with E2 structural IDs for cross-references: `<root>/evidence/e1-index.md`
- Page images, ONLY for resolving a doubt about a number/term: `<root>/evidence/render/<SOURCE-ID>/p-NN.png` (Read tool)
- Output: `<root>/work/editorial/<PART>/<CHAPTER>.json`   (create the folder if needed)
- Validator: `python3 <root>/tools/e2_validate_chapter.py --root <root> work/editorial/<PART>/<CHAPTER>.json`
Do not touch anything else (no PDFs, `master/`, `state/`, `config/`, `manifests/`, other chapters, `evidence/out`). Do not call external APIs.

## The three layers (never blend, always label)
1. **What the standard says** — neutral paraphrase + short verbatim definitions; every statement carries a clause and printed page, e.g. «(بند ۶.۴.۲، ص ۲۴)».
2. **Compiler's explanation / NIST comparison** — your reading, clearly phrased as the book's own («در این کتاب …»).
3. **Iranian organizational application** — generic practice, anonymous scenarios, optional template. Origin `editorial_localization_ir`.

## JSON schema
```
{ "title_fa": "...", "title_en": "...", "part_id": "P02", "chapter_id": "C58", "kind": "standards_integration",
  "origin": "editorial_synthesis", "generator": "<your model name if you know it, else 'claude (delegated worker)'>",
  "generated_at": "<ISO time>", "qa": {"pass": null, "status": "pending_policy_review"},
  "sections": [ { "title_fa": "...", "title_en": "", "label_fa": "<allowed label>", "fa_text": "<markdown>",
                  "origin": "editorial_synthesis" | "editorial_localization_ir",
                  "derived_from_source_ids": ["INSO-23733-1403", "NIST-AI-100-1"],
                  "derived_from_content_ids": ["KDJ-AI-2026E2-P02-C08-S03"],      // only IDs that exist in e1-index.md
                  "evidence_refs": ["S23733-014", "T23733-3.1"],                    // IDs from index.json: EVERY factual claim must trace to one
                  "template": { optional, see below } } ] }
```
Build the JSON with a small Python script (`json.dump(..., ensure_ascii=False, indent=1)`), not by hand, to avoid escaping errors.
Allowed labels — synthesis origin: یادداشت تدوینگر · راهنمای مطالعه · ارتباط با اسناد دیگر · جمع‌بندی تطبیقی · نقشهٔ مفهومی(نقشه مفهومی) ·
معرفی استاندارد ملی · اصطلاحات رسمی استاندارد · خلاصهٔ تدوینگر از استاندارد · نگاشت تطبیقی با NIST · محدودیت‌ها و احتیاط‌ها.
Localization origin: کاربرد در سازمان‌های ایرانی · راهنمای اجرای عملی · چک‌لیست اجرایی · نمونه فرم · سناریوی سازمانی · تفسیر اجرایی تدوینگر · مثال تدوینگر · برای مدیران · برای تیم فنی · اگر سازمان شما از صفر شروع می‌کند.
(Exact spelling is in `<root>/config/style.yaml`; the validator checks it.)
Optional template (only where the assignment allows; ID given in the assignment):
`{"template_id":"TPL-P02-7","title_fa":"...","derived_from":[...],"fields":[{"name_fa","name_en","description_fa","example_fa"}]}` — the pipeline appends the field table to the section.

## fa_text format
Markdown without headings (the section title is the heading). Use bold lead-ins, `- ` lists, pipe tables with a header + `|---|` row,
and blockquotes (`> ...`) ONLY for verbatim definitions: each followed by a source line inside the same blockquote,
e.g. `> «<definition>»\n> [INSO 42001، بند ۳.۷، ص ۴]`. Max 10 quotes per chapter, ≤ 45 words each, and each must be backed by a cited T/Q/V item
(the validator compares). Everything else must be paraphrase. Open each synthesis section with one italic line naming the layer, e.g.
«*خلاصهٔ تدوینگر از INSO 23733:1403؛ متن استاندارد نیست.*». English identifiers (ISO/IEC 23894:2023, AIMS) are LTR islands inside RTL text.

## Modality (most important)
Report the standard's own force. «باید» only for printed shall-level requirements; «توصیه می‌شود / بهتر است» for recommendations; «می‌تواند / مجاز است»
for permissions; plain statements for information. «لازم است / ضروری است / موظف است / الزامی است» printed in an informative or guidance
context are NOT «باید»: say "با عبارت «لازم است» چاپ شده است" and classify honestly. Where the packet records a printed oddity
(e.g. an annex labelled «الزامی» that is descriptive), report it neutrally in a «محدودیت‌ها و احتیاط‌ها» section. Never upgrade, never soften.

## NIST comparison
Cross-reference E1 material instead of repeating it. Use a table: موضوع | در NIST (E1) [ID] | در استاندارد ملی [clause] | سطح تطابق | دلیل مفهومی.
Levels (editorial, with a reason): DIRECT / PARTIAL / RELATED / NO_DIRECT_EQUIVALENT. Never say the documents are equivalent, never imply
NIST endorses or maps to ISO/INSO. Definitions differ — attribute each (see the risk entry in the terminology file).

## Iranian application (layer 3)
Generic and practical: purpose, scope, responsible roles (generic: مدیرعامل، مرجع حکمران، مدیر مخاطره، مسئول حریم خصوصی، تیم داده، مهندسی نرم‌افزار، ممیزی داخلی…),
inputs, activities, outputs, evidence, KPIs, review cadence — as a table or checklist; classify checklist items ضروری برای اجرای پایه / پیشنهادی / پیشرفته
and say this classification is the compiler's. NO Iranian law, authority, regulator, mandate, certification body or real organization; use
«ممکن است بسته به صنعت و مقررات حاکم، الزامات دیگری نیز اعمال شود.» Anonymous scenarios only. Say that applying the book creates no certification
and no legal compliance, and that INSO/ISO/IEC/IEEE did not review this book.

## Self-check before finishing
1. Run the validator; fix every ERROR; read every warning and either fix it or be ready to defend it (e.g. «واپایش (کنترل)» is fine; bare «کنترل» is not).
2. Re-read: every number, clause number, page and year must match the packet. If the packet is ambiguous or silent, OMIT the claim.
3. Section count and words must meet the assignment. Do not pad. Quality over length.
4. Final report to the editor (≤ 250 words): file path(s), sections, words, validator result, any claim you could not support and removed,
   any evidence ambiguity the editor should rule on. Do not claim the chapter is "reviewed" or "approved" — it is a draft.
