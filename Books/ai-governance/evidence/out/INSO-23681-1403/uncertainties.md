# INSO-23681-1403 — uncertainties and checks

Format: item | page (PDF / printed) | why uncertain | what was done.

## Identity and expectation check
- National number, year, international basis | PDF 1, 8 | Assignment expected INSO 23681 (1403) = ISO/IEC 23053:2022. | Read on images: cover prints «استاندارد ملی ایران ۲۳۶۸۱ چاپ اول ۱۴۰۳», «INSO 23681 1st Edition 2024», «Identical with ISO/IEC 23053:2022», ICS 35.020; preface (PDF 8, ح) gives ISO/IEC 23053:2022 and the «معادل یکسان» method. NO difference found.
- Publication date | PDF 1, 8 | Only the approval date 1403/05/09 (740th session of the national IT standards committee) and the year 1403 / 2024 are printed; no separate publication date. | Recorded null for publication date; recorded the approval date in identity.json.
- ISO number of the only normative reference (INSO 23679) | PDF 10 / printed 1 | 23679 is cited by national number and title only («مفاهیم و اصطلاحات هوش مصنوعی»); its ISO twin is not printed here. | Not guessed.
- «مورد الف» | PDF 8 | Preface refers to «روش اشاره شده در مورد الف» (INSO boilerplate); the referenced text is not in this PDF. | Noted only.

## Normative force (informative document; scattered «باید / ضروری است / لازم است»)
The main body has no explicit normative/informative labels (only Annex A is labelled «آگاهی‌دهنده»; clause 2 is «مراجع الزامی»). Modality was recorded as printed; statements marked shall/should/may sit inside descriptive text. Instances:
- 6.3, PDF 21 / printed 12: «پس باید در انتخاب مجموعه‌های داده ... دقت شود» (shall).
- 6.4, PDF 23 / printed 14: «باید داده‌های آموزشی، اعتبارسنجی و آزمایشی از هم جدا باشند»; «ضروری است ... توزیعی تاحدامکان مشابه» (two statements).
- 6.5.3.1, PDF 25 / printed 16: «فراپارامترها نیز باید انتخاب شوند» (descriptive).
- 6.5.3.6, PDF 32 / printed 23: «داده ورودی باید به عوامل مختلف تقسیم شود» (description of decision trees).
- 6.5.4.6, PDF 34 / printed 25: «درجهٔ چندجمله‌ای باید ...» (description of least-squares fitting).
- 6.5.5.7, PDF 40 / printed 31: «چنین کاربردی ... توصیه نمی‌شود» (negative recommendation, cites [10][11]); recorded as `should`.
- 7.2, PDF 43 / printed 34: «باید ... توجه ویژه‌ای شود».
- 8.1, PDF 48 / printed 39: «لازم است ... تعریف گردد».
- 8.3, PDF 52 / printed 43: «باید از هم مجزا باشند»; «لازم است ... به مجموعه‌های مجزا ... تقسیم شوند».
- 8.4, PDF 54 / printed 45: «انتخاب الگوریتم ... الزامی است».
- 8.5, PDF 56 / printed 47: «توصیه می‌شود انتخاب مدل با مجموعه‌دادهٔ آزمایشی انجام نشود» (should) and «صرفاً باید میان مدل‌های اندکی انجام شود» (shall) — recorded as two statements.
- 8.6, PDF 57 / printed 48: «ضروری است توجه ویژه‌ای ... به محیط هنگام اجرا».
- Annex A.2.2.2, PDF 62 / printed 53: «حقیقت پایه ... باید مشخص شود» (annex is informative).
- «ممکن است ... الزامی باشد» (6.4/8.3/8.4, PDF 53-54) was recorded as `may`, not `shall`.
- «توصیه می‌شد» in 6.5.5.1 (PDF 35) belongs to the definition of false positives/negatives, not a requirement; not recorded as should.

## OCR cross-check (difflib window ratio; below 0.80 re-read on the image)
- Term 3.1.4 definition, PDF 11 (ratio 0.797): the footnote mark ³ inside the sentence and OCR noise. Re-read on the image: transcription confirmed.
- Term 3.2.1 «پس انتشار» definition, PDF 12 (ratio 0.657): OCR text garbled (stray Latin/words). Re-read on the image: transcription confirmed.
- Abbreviation table (clause 4): AUC (0.46), CNN (0.56), DCNN (0.53), MAE (0.50), CapsNet (0.77), RNN (0.79) — short strings, OCR splits the three table columns. All 32 rows re-read on images PDF 14-15: confirmed. English expansions and abbreviations were read visually (not OCR-compared).
- Other terms: 0.84-1.00. Outline headings: all matched ≥0.85 except PDF 4/8/9/64 front headings (OCR noise on short bold lines) and 6.2.6 / 6.2.7 (OCR glitch «کاهش Tole!», «plu ... وظابف»); these were verified on image crops: «کاهش ابعاد» (with footnote mark ³) and «سایر وظایف».

## Figures (described from images; no boxes invented)
- Fig. 12 (PDF 49 / printed 40) vs. body text: (a) the fourth cross-cutting arrow reads «ایمنی، تاب‌آوری، استواری و انصاف» while the text of 8.1 says «... و عدالت»; (b) deployment column lists «دسته بندی» while 8.6 text says «بسته‌بندی» (likely a translation slip for "packaging"); (c) operations column lists «حفظ، تعمیر، به روز رسانی، پایش» while 8.7 text says «نگهداری و تعمیر»; (d) the pipeline arrow is labelled «کانال ارتباطی ML» while the caption and text say «زنجیره»; (e) only seven life-cycle boxes are printed (no separate "continuous validation" box); the last box carries a footnote mark and footnote «a-Retirement». All recorded exactly as printed.
- Fig. 13 (PDF 59 / printed 50): first top-row box carries two overlapping labels «تنظیم وظیفه» / «تعریف مسئله»; the text speaks of two steps «تعریف مسئله» and «پیکربندی وظیفه».
- Fig. 8 (PDF 43 / printed 34): a box is labelled «آزمایشی مدل» (as printed; reads like "model testing"); the exact routing of some dashed arrows was read at moderate resolution.
- Fig. 6 (PDF 41 / printed 32): long method lists transcribed from a 2x zoom; minor orthographic variants (e.g. «الگوریتم‌های ژن‌شناسی (ژنتیک)») follow the print and could contain small reading errors.
- Fig. 4 curve values are approximate readings from the picture.
- Fig. 1 (PDF 16 / printed 7): second band has no printed title; recorded as «نوار بدون عنوان چاپی».

## Terminology inconsistencies inside the document
- «شبکه محفظه‌ای» (heading 6.5.3.2.7) vs «شبکه (عصبی) کپسولی» (Fig. 3, abbreviation table); «همامیخته» (headings, tables) vs «هم‌آمیخته» in some body text.
- «فراپارامتر» (6.5.3.1, term 3.2.2) vs «ابرپارامتر» (8.4, Fig. 8).
- Term 3.1.1 definition begins «یادگیری ماشینی که ...» (not «مدل ML») unlike 3.1.2.
- 6.5.5.5 text says ROC plots TPR against «نرخ منفی کاذب» while Fig. 4 legend says X = FPR (نرخ مثبت کاذب).

## Not present
- No numbered tables (only the unnumbered clause 4 abbreviation table).
- No roles/stakeholders list or RACI; «ذی‌نفعان» appears only in 8.3 and 8.5. No control IDs. No clause giving requirements for organizations.
- English headings appear only for terms (clause 3); clause headings have no English equivalents.
- Clause numbers 6.2.x and 6.5.x are not listed in the printed TOC; they were taken from page headings (Persian print shows them reversed, e.g. «۱-۲-۶» = 6.2.1).
