# INSO-42001-1404 — uncertainties and checks

Format: item | page (PDF / printed) | why uncertain | what was done.

## A. Identity and numbering

1. National number vs. expectation | PDF 1, 2, 10 / header of every page | Expectation «INSO/BS/ISO/IEC 42001:2025, Iranian year 1404, basis ISO/IEC 42001:2023, IDT» | Confirmed exactly: Persian «استاندارد ملی ایران/بی اس/ ایزو/ آی‌ای‌سی ۴۲۰۰۱: سال ۱۴۰۴», Latin «INSO/BS/ISO/IEC 42001:2025», cover «(BS/ISO/IEC 42001:2023, IDT)». No difference found.
2. Adoption reference wording | PDF 1 vs PDF 10 (printed ط) | Cover says «BS/ISO/IEC 42001:2023, IDT»; foreword says «BS ISO/IEC 42001:2023 ... به روش «همسان»». Neither writes «ISO/IEC 42001:2023» without «BS». | Recorded both forms verbatim in identity.json; the direct source is the BSI-numbered adoption of ISO/IEC 42001:2023.
3. Edition («چاپ اول») | PDF 1 / headers | Not printed anywhere on this standard (other INSO standards print it) | edition = not printed (null); not guessed.
4. Approval date | PDF 10 (printed ط) | Printed only in Iranian calendar «1404/08/25», session no. 757 of the national IT-standards approval working group; no Gregorian date and no publication date printed | Recorded as printed; Gregorian equivalent (2025-11-16) is an extractor computation, flagged as such. «1396/10/02» in the same page is the date of the standards law, not of this standard.
5. Page offset | whole file | Front matter uses abjad letters (ب ج د ه و ز ح ط ی ک) | printed = PDF − 12 for PDF 13..81; PDF 1–2 unnumbered; PDF 3–12 = ب..ک. Verified on TOC (Annex A printed 22 → PDF 34; B 27 → 39; C 61 → 73; D 64 → 76; bibliography 67 → 79).
6. INSO 23679 reference | PDF 13 (clause 2 and clause 3 intro), 14 (3.2 note 1), 19, 45, 52, 59, 61 | Digits critical | Read on the images at all 8 places: «ملی ایران ۲۳۶۷۹: سال ۱۴۰۳», corresponding international standard printed in clause 2 only: «ISO/IEC 22989:2022، همسان». OCR (official dir) agrees on 6 pages (13, 14, 19, 45, 52, 61); PDF 59 is not matched by OCR because of a line break. 3.2 note 1 cites «زیربند ۵-۱۹» (printed visually «5-19», read as 5.19 of that standard). ISO/IEC 22989 is not listed in the bibliography.
7. Bibliography national numbers | PDF 79–81 | 17 national numbers; digit-level risk | Each number read on the image and compared with OCR: 15 of 17 agree digit for digit; ISO 9241-210 → «۵۲۴۱-۲۱۰» was zoom-checked (OCR printed a spurious leading 8); ISO 9001 → «۹۰۰۱» was zoom-checked (OCR returned no digit string). The relation wording is «براساس استاندارد ... تدوین شده است», not «همسان». [6] says «براساس استاندارد ISO 62304» for IEC 62304 (printed as is).
8. Clause-number visual order | headings vs. cross-references everywhere | Headings are visually reversed («۱-۳» = clause 3.1; TOC «۱-۴» = 4.1) while running-text cross-references read left to right («(۳-۱)» = 3.1, «زیربند ۶-۱-۳» = 6.1.3). Annex IDs: «الف-۲-۲», but A.6.2.7 is printed «الف ۶-۲-۷» (no hyphen after «الف»), A.10 header printed «الف-۱۰ارتباط ...» without a space | Normalised everywhere to dotted Latin ids (3.1, A.6.2.7, B.9.3); printed form kept in id_printed_fa (controls.json).

## B. Normative force

9. Annex A (الزامی) vs. A.1 text | PDF 34 / 22 | The annex is labelled «(الزامی)», but the first paragraph says using all control objectives and controls of Table A.1 is «الزامی نیست» and the organization may design its own (ref. 6.1.3) | Recorded A.1 as `info`; control rows themselves as printed («سازمان باید ...»). The binding mechanism is clause 6.1.3 (compare and justify), not the annex alone.
10. Annex B (الزامی) vs. its own text | PDF 39 / 27 | B.1 says organizations need not document inclusion/exclusion of this guidance in the SoA and that the guidance is not always suitable; yet the same paragraph says the annex «باید» be used as guidance | Recorded B.1 as three separate statements (info / may / shall) with modality_note_fa; the «باید» is about using the annex as guidance, not an obligation to do every item.
11. Table A.1 «باید» vs. Annex B «توصیه می‌شود» | PDF 34–38 vs. 39–72 | Same control restated: Table A.1 uses «سازمان باید ...», Annex B «واپایش» boxes use «توصیه می‌شود سازمان ...». B.2.4 and B.3.2 use «لازم است»; B.7.6 alone keeps «سازمان باید ...» | Never upgraded or downgraded: A rows `shall`; B rows `should`; B.7.6 `shall`; sentences with «لازم است» recorded as `should` with modality_note_fa («لازم است» is ambiguous; it is not «باید»); «بهتر است» noted separately. Book text should say «Table A.1 requires, Annex B recommends».
12. A.6.2.6 | PDF 36 | one control with two modalities: «باید» determine/document the components, «توصیه می‌شود» they include at least monitoring, repairs, updates, support | Control recorded as `shall`; the should-sentence is mentioned in control_statement_fa_short and modality_note_fa.
13. A.6.2.8 / B.6.2.8 | PDF 36, 60 | Printed «ثبت سوابق به اندازهٔ حداقل باشد» («record keeping at the minimum amount»), which reads oddly (ISO original = at a minimum when the system is in use); A.6.2.8 says «ثبت باید به اندازهٔ حداقل باشد» | Recorded as printed, flagged ambiguous; no reinterpretation.
14. Note to 3.26 uses «باید» | PDF 19 / 7 | Note 2 to entry in clause 3 contains «سازمان باید تمامی مخاطرات شناسایی‌شده را مستند کند» and «باید در بیانیهٔ قابلیت کاربست‌پذیری منعکس شوند» although notes are normally informative | Terms.json keeps the note verbatim; statements.json lists it under clause 3.26 as `shall` with modality_note_fa.
15. «باید» as list lead-ins | clauses 5.1, 5.2, 6.1.2, 6.1.3, 6.2, 7.2, 8.1, 9.1, 9.2.2, 9.3.2, 10.2 | Each lead-in «سازمان/مدیریت رده‌بالا باید ...» governs a lettered or dashed list | Each list item recorded as its own `shall` row only where the item is a distinct requirement (5.1, 7.2, 9.2.2 groups); in others the sentence is condensed to one row. Count of `shall` rows (79 for clauses 1–10 incl. one note) is therefore a count of statements, not of printed «باید» tokens.

## C. Typographical or logical oddities in the printed Persian (recorded as printed)

16. 9.2.2 item (ب) | PDF 31 / 19 | Printed «میزان را به نحوی انتخاب کند» where «ممیزان» (auditors) is evidently meant | Statement row quotes «میزان» and says «احتمالاً ممیزان»; image zoomed twice.
17. 4.3 last paragraph | PDF 21 / 9 | List printed «... عملیات، عملکرد، ارزشیابی، بهبود، واپایش‌ها و اهداف» (stray comma splits «performance evaluation») | Paraphrased as the clause intends; flagged here.
18. 6.1.2 note | PDF 24 / 12 | Note refers to «ردیف د) ردیف ۱)» although the list letter in the clause is «ت)» (analysis item) | Recorded the note as «استفاده از ارزیابی تأثیر مطابق ۶-۱-۴» without quoting the letter; flagged.
19. B.6.1.3 title | PDF 36 vs 52 | Table A.1 title «... سامانه هومص»; Annex B title «... سامانه‌های هومص» (plural) | Outline uses the B form; controls.json uses the A form (each as printed).
20. Term 3.25 | PDF 18 | English «Data Quality» printed with capital D and Q; term 3.24 Persian «ارزیابی تأثیر سامانه هومص» | verbatim.
21. «واپایش» not «کنترل» | whole doc | national terminology | Use «واپایش» when quoting; terminology list in identity.json notes.

## D. OCR cross-check (BRIEF step 5; official OCR directory used; difflib window similarity on normalised Persian)

OCR is poor for large-type definition lines, and tables in Annex A are read cell by cell. Items below fell under 0.80; **all were re-read on the page images** (pp. 13–19, 20, 27, 34–38, 51 viewed full-page at 150 dpi) and the transcription kept:

- 3.6 definition «نتیجه‌ای که باید حاصل شود.» (0.56) — OCR dropped the line (PDF 15); image reads exactly so.
- 3.7 (min 0.68, notes with Latin «ISO Guide 73» garbled) — image verified.
- 3.11 definition «نتیجه قابل‌اندازه‌گیری.» (0.63) — OCR dropped the line (PDF 16).
- 3.20 definition (0.54) — OCR dropped the line (PDF 17).
- 3.23 definition (0.55) — OCR dropped the line (PDF 18).
- Controls A.2.3 (0.60), A.8.2 (0.72), A.10.2 (0.69) — multi-line table cells; image verified.
- Outline headings 4.3 (0.77, «تعیین دامنه نظام ... هومص» split by OCR), 7 «پشتیبانی» (0.63), B.6.1 (0.79) — image verified.

ocr_similarity in terms.json is the definition similarity; per-item scores for controls and outline headings are in their files. All other verbatim items scored ≥ 0.80.

## E. Coverage limits

22. Annex B / C / D are summarised (paraphrase, ≈ 1–3 statements per control group), not transcribed; per BRIEF rule 2. Annex C item titles are given verbatim in outline.json (C.2.1–C.2.11, C.3.1–C.3.7) and in the single listing rows C.2 and C.3 of statements.json.
23. No numbered figure exists in the document; the only table is Table A.1 (tables_figures.json). The PDF's embedded images are the INSO logo (PDF 1) and a QR code (PDF 2).
24. Commission members (PDF 5–7) are not itemised; only the chair, the secretary and the member count (23, counted on the images) are in identity.json.
25. «AI system» is not defined in clause 3 of this standard (defined in INSO 23679 = ISO/IEC 22989:2022); clause 3 holds 26 entries (3.1–3.26). terms.json has all 26; 3.1 spans PDF 13–14, 3.10 spans 15–16, 3.21 spans 17–18, 3.26 spans 18–19 (pdf_page = page of the term heading, pdf_page_end where the body continues).
26. Page images for PDF 1–81 were all rendered; no page was missing. Annex B (PDF 39–72) statements were re-checked page by page against the images after drafting and corrected (which sentences use «لازم است», «بهتر است» or «باید», and page numbers).
