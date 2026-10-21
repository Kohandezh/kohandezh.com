# Uncertainties — INSO-23684-1403 (INSO 23684:1403 = ISO/IEC 25059:2023, quality model for AI systems)

Method note: all 30 PDF pages were viewed as rendered images; key pages (3.x terms, Figures 1/2/A.1, normative references, preface, cross-references) were re-read on 250–300 dpi crops. OCR cross-check (Tesseract, normalised difflib ratio) was run for every verbatim field in terms.json and every figure label in quality_model.json; a second, sparse-text OCR pass was run on the two figure pages (scratch only). Result: terms.json — 36 verbatim fields checked, 2 below 0.80 (both English source brackets garbled by OCR); quality_model.json — 63 figure/clause labels checked, 9 below 0.80 (figure text that Tesseract drops). Section C lists each with what was done.

## A. Items that contradict expectations or the document itself

1. **No contradiction with the assignment.** Expected INSO 23684 / Iranian year 1403 / ISO/IEC 25059:2023 — all confirmed (cover, header on every page, preface). Cover also prints "Identical with ISO/IEC 25059: 2023" and "2024"; preface says «معادل یکسان». No difference found.
2. **Figure 1 vs text name of the modified sub-characteristic** (pdf 15 / printed 6 vs pdf 12–13, 17 / printed 3–4, 8): the figure prints «صحت عملکردی» (confirmed by both OCR passes); clause 3.2.3, the TOC and clause 5.4 print «صحت کارکردی». Treated as one item (functional correctness, marker m); quality_model.json uses the clause term as term_fa and keeps the figure spelling in term_fa_as_printed_in_figure.
3. **Clause 6.3 (pdf 21 / printed 12)** says transparency is a sub-characteristic of satisfaction «در مدل کیفیت محصول» (confirmed on 300-dpi crop). Clause 6.1, 5.6 and Figure 2 place satisfaction in the quality-in-use model. Recorded as printed (statement 6.3) with the discrepancy noted; do not cite 6.3 as saying "product quality model".
4. **Figure A.1 (pdf 22 / printed 13)**: the figure labels the quality-requirements division «ISO/IEC 2303n» and the evaluation division «ISO/IEC 2304n» (confirmed on crop); the text on pdf 22–23 says 2503n and 2504n. Also the figure calls the 2502n division «بخش سنجش کیفیت» while the text calls it «بخش اندازه‌گیری کیفیت». The bottom label's bidi order is scrambled in print; logical reading: «بخش‌های تعمیم ISO/IEC 25050 تا ISO/IEC 25099». Recorded both spellings in tables_figures.json.
5. **Bibliography [1]** (pdf 29): lists ISO/IEC 25000:2014 but its national note says the Persian standard was prepared from ISO/IEC 25000:2005.
6. **Clause 3.1.3** (pdf 11 / printed 2): definition says «درونی/بیرونی» while its note says «داخلی/خارجی» for the same measure types; source reference printed as «4.61» of ISO/IEC 25040:2011 (read twice on crop; kept as printed — OCR 0.767 because OCR garbles the Latin/Persian mix in that line).
7. **Clause 3.2.3 source** (pdf 13 / printed 4) prints the 25010 clause as «۴,۲,۱,۲» (Persian digits, commas) unlike dotted Latin elsewhere; reading = 4.2.1.2.

## B. Interpretation choices (not errors)

8. **Clause numbers are mirrored by RTL layout.** Headings print e.g. «۱-۵» for clause 5.1 and «۱-۲-۳» for 3.2.1 (TOC confirms hierarchy). All JSON files use logical order (parent first). Same convention applied to cross-references into INSO 23679: «۵-۱۲» (clause 5.2) → 5.12; «۲-۹-۱۱-۵» (clause 5.3) → 5.11.9.2; «۵-۱۵-۵» (clause 5.7) → 5.15.5. These three could not be verified against INSO 23679 here; clauses 5.2 and 5.7 cite different INSO 23679 clauses (5.12 vs 5.15.5) for «واپایش‌پذیری» — possibly different sub-concepts, not checked.
9. **change_for_ai = "unchanged"** for every main characteristic and every inherited sub-characteristic is an inference from (a) no a/m marker in the figure, (b) clause 5.1/6.1 calling the main characteristics unchanged («مشخصه‌های اصلی تغییر نیافته»), (c) no explanation in clauses 5–6. The document itself says (5.1, 6.1) that "some sub-characteristics have different meanings or contexts than in ISO/IEC 25010" and that these are explained in the clause — only the items in 5.2–5.7 and 6.2–6.3 are explained, but the document does not enumerate "unchanged" sub-characteristics. The wording «مشخصه‌های اصلی تغییر نیافته» could also be read as "those main characteristics that are unchanged"; no main characteristic carries a marker in either figure.
10. **Usability column of Figure 1** (pdf 15) shows «مناسب بودن» and «بازشناسی‌پذیری» as two separate rows (9 rows in total), evenly spaced like other rows. The document does not say whether they are one split sub-characteristic or two; kept as two printed rows (PQ.5.1, PQ.5.2). Do not claim the exact number of inherited usability sub-characteristics from this document.
11. **English names**: only the 8 main product-quality characteristics, «رفتار زمانی» (Time Behaviour), the two quality-in-use characteristics «کارایی»/«اثربخشی» and the new/modified terms of clause 3 have an English name printed. All other inherited sub-characteristics and «پوشش بافت / نبود مخاطره / رضایت‌مندی» have term_en = null (not supplied from memory).
12. **Parent of intervenability**: Figure 1 puts «مداخله‌پذیری» under «امنیت»; clause 5.7 never names the parent (user controllability, functional adaptability and robustness have text-confirmed parents).
13. **Transparency** is defined once (3.1.5, under «مفاهیم کلی») and serves both as usability sub-characteristic (5.6, Figure 1) and satisfaction sub-characteristic (6.3, Figure 2). Clause 5.6 does not use the word «جدید» for it; the "added" label rests on marker a in both figures.
14. **Lists differ by design**: the note to 3.3.1 lists 10 societal/ethical themes; clause 6.2 lists 14 themes of ISO/IEC TR 24368 (adds safety & security, human-centred design, environmental sustainability, ways of working) and explains that safety/security and sustainable environments are covered by other SQuaRE risk-mitigation sub-characteristics.
15. **Modality**: only four statements use shall-type wording («باید» / «الزامی است»): clause 2, 5.1, 6.1, 6.2. Clause 5.4 «لازم است … سنجیده شوند» (necessity inside a descriptive explanation) is recorded as info; INSO wording is neither «باید» nor «بهتر است». No «بهتر است / توصیه می‌شود» occurs anywhere.
16. Front-matter page labels: pdf 5 shows a glyph that looks like the digit ٥ but is the abjad letter «ه» (sequence ب ج د ه و ز ح ط; TOC lists preface = ز, introduction = ح).
17. The international equivalents of INSO 23679 and INSO 23681 (clause 2.2–2.3, normative) are not printed in this document; the national version of ISO/IEC 25010 is named «ایزو آی‌ای‌سی ۲۵۰۱۰: سال ۱۳۹۱» with no numeric INSO number. Whether the Persian text differs from the English ISO/IEC 25059 in these substitutions cannot be checked from this PDF.
18. The document has no numbered tables; only Figures 1, 2 and A.1.

## C. OCR cross-check items below 0.80 (all re-read on the 300-dpi image; OCR is the weak side)

| item | page (pdf) | ratio | why / what done |
|---|---|---|---|
| 3.1.2 source «4.4.6 … 25010:2011» | 11 | 0.791 | OCR mangles English inside RTL bracket (read «6 … 150/1۳80»); crop confirms «4.4.6» |
| 3.1.3 source «4.61 … 25040:2011» | 11 | 0.767 | same cause; crop confirms «4.61» |
| 3.2.5 definition | 13 | 0.809 | OCR emits the sentence out of order («…حفظ نماید SLL میزانی که»); words match on crop |
| Fig. 1 labels PQ.2 قابلیت نگهداری, PQ.2.4 قابلیت تجدید نظر, PQ.3.3 عدم انکار, PQ.3.4 پاسخگویی, PQ.4 قابلیت اطمینان, PQ.5.4 قابلیت عملیاتی, PQ.5.9 شفافیت, PQ.7.3 ظرفیت | 15 | 0.53–0.72 | Tesseract drops or garbles diagram text (also in sparse mode); each label read again on 300-dpi crops of the figure |
| Fig. 2 label QU.2.3 مخاطره کاهی زیست محیطی | 20 | 0.706 | OCR drops two lines of the box; read on crop |
| other figure labels | 15, 20 | ≥ 0.80 or not applicable | — |

OCR also mis-reads clause digits (e.g. «۳-۲-۲» for 3.2.3 on pdf 13, «۲-۱-۳» for 3.1.3) — clause numbers were therefore taken from the image / TOC, not OCR.
