# Uncertainties — INSO-23685-1403 (INSO 23685:1403, identical with IEEE Std 2813:2020)

Format: item | page (pdf / printed) | why uncertain | what I did.

## A. Identity and edition

1. IEEE edition/status | pdf 1, 8 | Only "IEEE Std 2813:2020, Big Data Business Security Risk Assessment" is printed (cover: "Identical with IEEE Std 2813:2020"). No IEEE status (active/withdrawn), publication or approval date, or "IEEE Standard for ..." title wording is printed. | Recorded `ieee_edition_and_status_printed = null`; did not guess. The IEEE original is not in the staging files, so no comparison with it was possible.
2. Approval date in Gregorian | pdf 8 / ح | Printed only as Jalali ۱۴۰۳/۰۶/۱۸. | Gregorian 2024-09-08 is computed and labelled "derived, not printed".
3. Session number | pdf 8 / ح | Printed in words ("هفتصد و چهل و هفتمین اجلاسیه"), so 747 is my reading of the words. | Recorded the words verbatim; read on a 300 dpi crop.
4. Normative references | pdf 12 / 2 | Clause 2 lists two Iranian standards (INSO 23683:1403; INSO 13245:1398). Their international equivalents are not printed. | Recorded national numbers only; no international equivalent asserted.
5. English title casing | pdf 1 vs 8 | Cover: "Big data business security risk assessment"; preface: "Big Data Business Security Risk Assessment". | Both recorded; no choice made.
6. Persian title spacing | pdf 1, 4, 8, 11 | "مخاطره (ریسک)" has a space before the bracket on the cover and on the body title page (pdf 11), none in the commission title (pdf 4) and the preface (pdf 8). | Used the form on the body title page (pdf 11).
7. Front-matter page labels | pdf 2–10 | Printed pages are Persian letters ب ج د ه و ز ح ط ی. The glyph on pdf 5 (ه) looks like a Persian digit ۵. | Assigned by the abjad sequence and the TOC (foreword = ح, introduction = ط), which match. Letters are stored as strings; body pages as integers.

## B. Content versus the assignment's expectation

8. Expected "process" content is absent | whole document | The assignment expected an assets/threats/vulnerabilities process with steps, inputs/outputs, roles, criteria and reporting, plus annexes. The document has none of these as such: 22 printed pages, no annex, no bibliography, no numbered table, no roles clause. | Recorded what is there: a two-level technical framework (portrait level + algorithm level), the "risk index" 0–100, operations/monitoring/audit modules, and clause 6 (data protection through six data-lifecycle stages). Stated this plainly in `statements.json` notes.
9. Clause type labels | outline | The text does not mark clauses normative/informative. | `type: normative` for clauses 1–6 is my organising label; per-sentence force is in statements.json.

## C. Modality (normative force) — ambiguous items

10. Clause 2 "الزامی است" | pdf 12 / 2 | Obligation to use the two references is expressed with «الزامی است», not «باید». | Marked `shall` with a `modality_note`.
11. NOTE under 5-1-5 | pdf 20 / 10 | «باید» appears inside a «یادآوری» (NOTE): accounts of one person «باید به هم مرتبط باشند». A NOTE's normative force is unclear. | Marked `shall` with a `modality_note`; flag when citing.
12. 6-5 (ب) mixed wording | pdf 31 / 21 | Sentence opens with «توصیه می‌شود» but ends «حقوق و حریم خصوصی کاربران، همواره باید محافظت شود». | Marked `should` with a note; the embedded «باید» is not upgraded to a separate requirement.
13. 4-3 (ث) lead-in | pdf 15–16 / 5–6 | «حداقل باید شامل … باشد» governs a four-item list that continues on the next page. | The lead-in and each of the four items are `shall`, with notes.
14. Sibling clauses differ | pdf 23–26 / 13–16 | 5-2-2-2 (image), 5-2-2-4 (audio), 5-2-2-5 (video) use «باید دارای قابلیت‌های زیر باشد»; 5-2-2-3 (text) and 5-2-3-1 use «شامل … است»; 5-2-2-1 (URL) uses the imperative «در نظر بگیرید». | Recorded as printed: image/audio/video `shall`; text and personal identification `info`; URL `info` with note. Whether the difference is intended is unknown.
15. Imperative forms | pdf 20, 23, 32 | 5-1-7 («لحاظ نمایید»), 5-2-2-1 («در نظر بگیرید») and sub-items of 6-6 / 6-7 are imperative. | 5-1-7 and 5-2-2-1 are `info` (no modal word). Items under 6-6/6-7 are `should` because the lead-in is «توصیه می‌شود».
16. Redundant «توصیه می‌شود» | pdf 31 / 21 | 6-4 repeats «توصیه می‌شود» inside the already-recommended list; 6-5 uses both «توصیه می‌شود» and «بهتر است». | Recorded as `should`.
17. 6-6 (ب) referent | pdf 32 / 22 | «اهمیت و حساسیت آن را … ارزیابی نمایید»: it is unclear whether «آن» means the data or the third party. | Paraphrased as "data/third party" and flagged in the note.

## D. Possible editorial or translation inconsistencies in the printed text (kept as printed)

18. Figure 4 repeats Figure 3 | pdf 22–23 / 12–13 | Figure 4 is titled «شکل ۴ مدل هوش مصنوعی» (no hyphen), but its graphic is identical to Figure 3: middle column still «مدل قاعده / قاعده ۱ … قاعده n». | Recorded both as printed and described the mismatch in the figure summary.
19. Figure 1 labels vs text | pdf 16 / 6 | Figure 1's Operations box says «سازمان‌دهی امنیتی» and «سامانه عملیاتیِ راهبرد امنیتی»; the list below it and clause 5-2-4 say «مدیریت امنیت» and «راهبرد امنیتی». The figure title («چارچوب ارزیابی مخاطره کسب‌وکار کلان‌داده») also omits «امنیتی/امنیت», which the clause 4 heading has. | Recorded the figure labels exactly as printed; the text-side names appear in statements.
20. Figure 2 title | pdf 21 / 11 | «ارتباط مرتبط» (footnote: Relevant Relation) reads like a literal translation. | Recorded as printed.
21. Rate definitions in 5-2-4-1 and 5-2-4-4 | pdf 27, 29 / 17, 19 | «نرخ سالم» is defined as confirmed risk events ÷ total inspected among untracked events (footnote: Healthy Rate), which reads like a miss rate. 5-2-4-4 (ب) separately mentions «نرخ ناپیدایی» (Missing Rate) and «نرخ بازشناسی سالم». | Paraphrased the formulas as printed; did not correct or re-interpret them.
22. 5-2-5 lead-in | pdf 29 / 19 | The clause is about new-risk awareness, but its list is introduced with «ممیزی امنیتی شامل موارد زیر است». | Recorded with a note; the list content is new-risk-awareness functions.
23. «فنون ایستا» | pdf 21 / 11 | 5-2-1-1 says «فنون ایستا» can adjust and correct rules; the term is not explained. | Quoted in the statement and flagged.
24. Threat-analysis sentence in 6-1 | pdf 30 / 20 | «هر عامل تهدیدی را … تجزیه‌وتحلیل می‌کند»: the grammatical subject is unclear (the technology or the system). | Not recorded as a requirement; the 6-1 statements cover only the legal-compliance disclaimer and the lifecycle recommendation.
25. Introduction citation | pdf 9 / ط | BSI guide is printed as «pas201:2018» (lower case, no space). | Not used in the statements; noted here only.
26. Persian vocabulary | pdf 15–29 | Footnote English glosses show non-standard Persian (e.g. «پویش سوداگری» = Campaign Arbitrage; «شدآمد» = traffic; «بدیل‌های مخرب» = Malicious Variants; «سیم‌کارت‌ها» = Simcard Pool). | Kept the printed Persian terms.

## E. OCR cross-check (BRIEF step 5)

27. Method | all pages | Official OCR (`evidence/ocr/INSO-23685-1403/`) was complete when the check ran (my own run of the same tesseract config in scratch was used only while waiting). Normalised (digits, yeh/kaf, ZWNJ, spaces, punctuation) difflib window search per page.
28. Results | — | Clause-3 terms: definitions 0.879–0.989, term names 1.000 except 3-1-6 (0.700). Identity strings 0.807–1.000. Abbreviation rows 0.909–1.000. Outline headings: nearly all 1.000.
29. Items under 0.80, all re-read on the page images (and 300 dpi crops where noted) and confirmed:
   - «1 مفاهیم کلی» (pdf 11, 0.667): OCR dropped the heading line; visible on the image.
   - «3 تعاریف، سرنام و کوته‌نوشت‌ها» (pdf 12, 0.727): footnote superscripts garble the line.
   - «شاخص مخاطره» heading of 3-1-6 (pdf 13, 0.700): OCR dropped the bold Persian heading and kept only «risk index»; heading is on the image. Because of this, 3-1-6's `ocr_similarity` shows 0.700 (the definition itself scored 0.989).
   - «تک‌چهره IP» (pdf 18, 0.750): mixed-direction Latin letters.
   - «شکل ۲- ارتباط مرتبط» (pdf 21, 0.636): OCR garbled the caption.
   - Front-matter labels (cover 0.333; «مقدمه» 0.600; «پیش‌گفتار» 0.750; «کمیسیون فنی تدوین استاندارد» 0.500; contact page 0.758): the cover label is my descriptive label, not verbatim; the other headings are on the images (pdf 4, 8, 9).
30. Numbers re-checked on images | — | 23685, ۱۴۰۳, 2813:2020, ICS 35.020, 23683/1403, 13245/1398, date ۱۴۰۳/۰۶/۱۸, 5 and 7 in "استاندارد ملی ایران شمارهٔ ۵، بند ۷", risk-index examples 100/60/40, scale ۰–۱۰۰, page offset (pdf 11 = printed 1; pdf 32 = printed 22), all TOC page numbers against body pages.
31. Paraphrases | statements.json | Statements are paraphrases and were not OCR-matched; each was written from the page image. Risk-index examples, indicator lists and figure labels were checked on 300 dpi crops.
