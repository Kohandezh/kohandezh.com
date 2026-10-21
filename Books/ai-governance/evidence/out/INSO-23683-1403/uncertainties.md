# Uncertainties — INSO-23683-1403 (INSO 23683:1403 = ISO/IEC 20546:2019, identical adoption)

Format: item | page (pdf / printed) | why uncertain | what was done.

## A. Numbering and cross-references printed inconsistently (verified on the page images)

1. Clause-number display | all of clause 3 and 4 | Headings and cross-reference numbers are laid out right-to-left, so «3.1.2» is displayed as «۲-۱-۳» (left to right). | Recorded everywhere in logical order (3.1.2); confirmed by 5x crop of the 3.1.2 heading and of the «(۱۱-۱-۳)» cross-reference in 3.1.2; OCR emits the same mirrored strings.
2. 3.1.19 distributed data processing | pdf 15 / printed 6 | Definition cites «داده‌پردازی (۳-۱-۱۹)», i.e. itself; data processing is 3.1.9. | Kept as printed; flagged in terms.json (cross_reference_issues). Possibly inherited from the international text — not verified.
3. 3.1.22 gather (note) and 3.1.32 scatter (note) | pdf 16 and 19 / printed 7 and 10 | Both notes say «مراجعه شود (۳-۲-۲۳)»; clause 3.2 has only three abbreviations, so 3.2.23 does not exist; scatter-gather is 3.1.33. OCR read the number as 22, the 10x crop shows 23. | Recorded as printed (3-2-23), flagged.
4. 3.1.26 non-relational model and 3.1.31 relational model | pdf 17 and 18 / printed 8 and 9 | Both cite the (logical) data model as «(۳-۱-۱۰)»; in this edition data model is 3.1.8 and 3.1.10 is data science (OCR agrees: «۱۰-۱-۳»). | Recorded as printed, flagged.
5. 4.3.1 data science | pdf 22 / printed 13 | Says data analytics follows the definition in «مدخل اصطلاحی ۳-۱-۵»; 3.1.5 is data, data analytics is 3.1.6. | Recorded in the paraphrase without changing the number; flagged here only.
6. 3.1.2 and 4.1 refer correctly (3.1.11, 3.1.5; subclauses 4.2, 4.3) — no problem, listed to show they were checked.

## B. Terminology inconsistencies / alternative printed forms (record all, do not harmonise)

7. big data | «کلان‌داده» (title, 3.1.2, clause 4); «کلان داده» with a space (ToC entry for Annex A); «بزرگ‌داده» (3.1.33 «مجموعه‌های بزرگ‌داده»; 4.3.9 «مقیاس بزرگ‌داده»); «مجموعه‌های بزرگ داده» (3.1.18 note). English only «big data».
8. 3.1.22 gather | heading «گردآورد(جمع‌آوری)» (two forms inside one heading); 3.1.22 note calls scatter-gather «پراکنش-جمع‌آوری»; 3.1.32 note «پراکندن- گردآوری»; 3.1.33 heading «پراکنش - گردآورد»; body text of 3.1.33 and 4.3.9 uses «پراکنش - گردآوری» / «گردآوری». Four different Persian renderings of scatter/gather.
9. 3.1.28 partially structured data | heading «داده نیمه‌ساختاریافته»; note 1 says industry usually calls it «داده‌های شبه ساختاریافته» (English footnote «Semi-structured data»); 3.1.35 uses «ساختاریافته», 3.1.37 uses «ناساختمند» (not «غیرساختاریافته»); 4.3.6 title «ساختاریافته و ناساختمند».
10. 3.1.36 SQL | two Persian heading lines «زپ‌سا» and «زبان پرسمان ساخت‌یافته» (note spells «ساختاریافته»); notes and A.8 write «زپ سا» with a space; the 3.1.25 note renders NoSQL as «غیرزپ سا» / «نه فقط زپ سا» (checked on the image at 1.6x).
11. 3.1.23 horizontal scaling and 3.1.38 vertical scaling | pdf 16 and 20 | Both notes say the respective scaling «برای بهبود عملکرد، مقیاس‌افزایی نیز نامیده می‌شود» — the same alternative name for two opposite notions (likely a translation slip: scale-out vs scale-up). | Recorded verbatim in both entries; paraphrase in statements.json says «ابهام در متن فارسی». Do not cite either note as a distinct synonym.
12. 3.1.19 / 3.1.20 vs 4.3.8 / 4.3.9 | headings «پردازش داده های توزیعی» (with a space), «سامانه پرونده توزیعی» vs clause 4 «پردازش داده توزیع شده», «سامانه پرونده توزیع شده».
13. Introduction (pdf 8, printed ح) | lists the characteristics as «حجم، سرعت، بدیل و/یا تغییرپذیری»; 3.1.2 and 4.2.3 use «گونه» for variety. «بدیل» is printed (checked on a 1.4x crop).
14. 3.1.10 vs 4.3.1 | 3.1.10: «فرآیند کشف، یا فرضیه و آزمون فرض»; 4.3.1: «ناوش یا آزمون فرض». «ناوش» (navigation) also appears in 3.1.14 note.
15. cloud computing | term «محاسبات ابری» (3.1.3, Annex A.5) but the bibliography cites INSO 21069:1395 under the title «رایانش ابری» (pdf 30).
16. 3.1.21 file | «مجموعه‌ای نام‌دار از پرونده‌ها» — a file defined as a named set of «files»; the source (ISO/IEC 2382 04.07.10) is probably «records» (not verifiable here). Recorded verbatim.
17. 4.3.8 | «تبدیل‌های استخراج صوری، تبدیل و بارگذاری» (footnotes: Transformation, Load) — printed wording is odd (likely extract-transform-load); paraphrase says «استخراج، تبدیل و بارگذاری» without the abbreviation ETL, which is not printed.
18. 3.1.28 translator footnote | pdf 18 / printed 9 | An unnumbered translator note at the foot of the page says the source text has «Parsible» where «Parsable» (تجزیه‌پذیر) seems correct. Recorded in terms.json (translator_footnotes_en); not part of the ISO definition.

## C. Normative force

19. The standard contains no «باید» and no «بهتر است». The only recommendation is 3.1.9 note 2 «توصیه می‌شود اصطلاح داده‌پردازی، مترادف با پردازش اطلاعات استفاده نشود» → modality should (statements.json).
20. «ضروری است» occurs in the introduction (pdf 9, printed ط, aspects to be considered together), in 4.2.3 (pdf 21, printed 12, machine-readable meaning of data) and in A.3 (pdf 26, printed 17, algorithms adapted to horizontal scaling); «الزامی است» in A.6 (pdf 27, printed 18) only describes datasets for which high security is mandatory; A.7 (pdf 27-28) says an organisation «الزام قانونی و قیمومیتی دارد» to ensure privacy requirements are supported and enforced — a statement of a legal/fiduciary duty, not a «shall» of the standard. | All recorded as modality info with modality_printed holding the printed wording; do not upgrade to «shall».
21. Annex A is printed as informative («آگاهی‌دهنده»); clauses 1-4 are labelled `normative` in outline.json only structurally (main body) — the printed text does not mark clause 4 informative, but it contains no requirements.

## D. Content expected by the assignment but absent

22. Big data analytics lifecycle, roles/activities, relation to artificial intelligence and machine learning | whole document | Not present. Keyword scan of all 30 OCR pages («هوش», «مصنوعی», «یادگیری», «نقش», «فعالیت») finds «هوش مصنوعی» only in the secretary's affiliation (pdf 4) and «نقش» only in «دسترسی مبتنی بر نقش» (A.6). Data analytics (3.1.6) and data science (3.1.10, 4.3.1) are defined but not related to AI/ML. | statements.json therefore holds 47 statements from the overview, scope, clause 4 and Annex A instead of items on AI/ML or roles.
23. A.11 «محاسبات تفاضلی» | pdf 29 / printed 20 | Printed as «تجزیه و واکاوش داده‌های گسترش‌یافته با استفاده از محاسبات تفاضلی» — meaning unclear («differential»?). The paraphrase keeps the printed phrase. A.11 also says «(به یادآوری ۱ مراجعه شود)» although only one unnumbered note exists.

## E. Page / layout / transcription points

24. Page numbering | Front matter uses Persian alphabet letters (ب..ط); the ه on pdf 5 looks like the digit ۵. Treated as the letter ه by position (pdf 2=ب, 3=ج, 4=د, 5=ه, 6=و, 7=ز, 8=ح, 9=ط). Body: printed = pdf - 9.
25. Entries split across pages | 3.1.8 (heading pdf 12, definition pdf 13), 3.1.19 (definition pdf 15, [source] line pdf 16), 4.2.4 (pdf 21-22), 4.3.7, 4.3.9, A.4, A.7, A.9 | Entry pages: pdf_page = page of the heading; pdf_pages lists all.
26. 3.1.12 note 3 | pdf 14 / printed 5 | Printed «…با استفاده از [ISO/IEC 2382-15:1999]ISO/IEC، استاندارد شده است.» — a stray extra «ISO/IEC» token sits next to the bracket; its exact position is uncertain because of mixed-direction layout. Recorded as printed with the token after the bracket.
27. 3.1.34 streaming data | pdf 19 / printed 10 | The definition carries a third footnote marker but only footnotes 1-2 are printed on that page. Recorded; footnote 3 unknown.
28. 3.1.4 / 3.1.5 etc. | [منبع: ...] lines cite ISO/IEC 2382:2015 entry numbers (21220586, 2121272, 2121413, 2125519, 01.01.06, 2122374, 17.06.06, 18.01.08, 04.07.10, 17.06.05, 03.02.01, 17.04.08, 17.04.05, 17.04.04) in mixed formats, and 3.1.11 cites ISO 19115-2:2009 (4.7), 3.1.34 cites ISO/IEC 19784-4:2011 (4.4). Digits re-read on the images; OCR agrees for all of them except where noted below.
29. 3.1.3 source line | pdf 11 / printed 2 | Cites INSO 21069:1395 entry «۳-۲-۵» (logical 3.2.5) and says the definition was modified from «الگویی برای فعال کردن دسترسی شبکه به مخزنی مقیاس پذیر و کشسان ...». Recorded in source_fa.
30. Committee page | pdf 4-5 | Only chair, secretary, editor and the number of members (9) are recorded in identity.json; the editor's surname «رثائی» was read on a 3x crop because OCR garbled it.
31. Cover «۲۳۶۸۳» | pdf 1 | OCR read the cover number as ۳۳۶۸۳; the page image and every running header (OCR agrees there) give ۲۳۶۸۳, and the cover's English side prints «INSO 23683». The English «2024» next to ۱۴۰۳ is cover text; no Gregorian date is printed in Persian.

## F. OCR cross-check (BRIEF step 5)

32. Method: Tesseract OCR (fas+eng, same tessdata/settings as the shared run) on the 30 rendered pages. The shared `evidence/ocr/INSO-23683-1403/` folder appeared after my own run and its 30 page files are byte-identical to my private output (cmp, 0 differences), so the results below hold for the shared OCR. Similarity = difflib ratio of the normalised definition+notes+source against the best-matching OCR span (Persian digits unified, ZWNJ/spaces/punctuation stripped; exact containment scores 1.0).
33. Result: 33 of 38 term entries >= 0.83 (26 of them >= 0.90); the three abbreviation rows of 3.2 score 1.0 (each part found verbatim in the OCR). Five entries scored below 0.80 — 3.1.15 (0.53), 3.1.16 (0.73), 3.1.19 (0.56), 3.1.26 (0.66), 3.1.32 (0.73): in each case OCR garbled italic cross-reference words or dropped a line; all five were re-read on 1.5x crops of the page images and the transcription was confirmed (flagged with ocr_note in terms.json). Borderline: 3.1.2 (0.83) and 3.1.3 (0.85), OCR dropped a few words.
34. Outline headings: the cover and address-page rows (pdf 1, 2) are descriptive labels (heading_is_descriptive), not printed headings. OCR misses several bold headings (پیش‌گفتار, مقدمه, کمیسیون فنی, اینترنت چیزها, پیوست الف, کتابنامه) so they cannot be cross-checked; all were read directly on the page images, and the ToC page (pdf 6) lists پیش‌گفتار, مقدمه, پیوست الف and کتابنامه with matching page numbers.
35. Statements are paraphrases (not verbatim) and were written from the page images, not from OCR.
