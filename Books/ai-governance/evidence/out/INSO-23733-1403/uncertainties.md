# Uncertainties - INSO-23733-1403 (INSO 23733:1403 = ISO/IEC 23894:2023, AI risk management guidance)

Format: item | page (PDF / printed) | why uncertain | what I did. Printed page = PDF page - 9 for Arabic-numeral pages.

## U1. Standard number in A.11 conflicts with the bibliography
- Page: PDF 39 (printed 30) A.11 «امنیت»; PDF 51 (printed 42) bibliography [8].
- A.11 prints «مدیریت مخاطره امنیت اطلاعات در استاندارد ملی ایران شماره ۲۷۰۱۳ [8] تعریف شده است». Bibliography [8] is «ISO/IEC 27005:2022, Information security, cybersecurity and privacy protection - Guidance on managing information security risks», with the note «استاندارد ملی ایران ایزو آی‌ای‌سی ۲۷۰۰۵: سال ۱۳۹۲ ... با استفاده از استاندارد ISO/IEC 27005:2011 تدوین شده است».
- Why uncertain: «۲۷۰۱۳» in the running text (read on a 2x crop, clearly 27013) does not match any printed bibliography entry; looks like a print error for 27005, but that is an inference.
- Action: recorded exactly as printed, flagged in statements.json (A.11). Do NOT cite «۲۷۰۱۳» as this document's reference without noting the discrepancy; the bibliography [8] is the safer anchor.

## U2. Printed order of Persian digits in clause numbers
- Page: all headings / TOC (e.g. PDF 6, 17, 22).
- Clause numbers are Persian digits joined by hyphens inside right-to-left text. Seen visually left-to-right the sequence is minor-major (clause 5.7.1 is drawn as «۱-۷-۵»; clause 5.2 as «۲-۵»); read right-to-left it is major-minor.
- Action: interpreted as major-minor (5.2 = leadership and commitment), consistent with the TOC page progression, the cross-references («زیربند ... استاندارد ملی ایران شمارهٔ ۱۳۲۴۵») and the ISO 31000-style structure. outline.json stores the normalised dotted form in number and the visual (as drawn) form in number_printed; in the statement texts the cross-references are written major-minor (e.g. «زیربند ۵-۲»), which an RTL renderer draws the same way as the print.

## U3. Modal wording that is neither «باید» nor «توصیه می‌شود»
- «توصیه می‌شود / به … توصیه می‌شود» (recommendation) is the dominant wording (about 100 occurrences in the native text layer, spread over PDF 11-40) - recorded as should.
- «باید» occurs once only, in INSO boilerplate (PDF 7 foreword: «باید همواره از آخرین تجدیدنظر استانداردهای ملی ایران استفاده کرد»). No «باید» in clauses 1-6 or the annexes.
- «سازمان موظف است» (the organization is obliged): 6.4.2.3 (PDF 27), 6.4.2.4 and 6.4.2.5 (PDF 28). Recorded as shall with modality_flag. Stronger than the surrounding register; whether the English ISO/IEC 23894:2023 text says «shall» or «should» there cannot be checked from this PDF (no English body text). Treat as a flagged obligation, not as a generic «باید».
- «لازم است» (necessary): 6.3.3 «لازم است فهرستی از ذی‌نفعان در نظر گرفته شود» (PDF 24) and Table 2 last row «در این ارزیابی، لازم است مسائل ایمنی، زیست‌محیطی، اجتماعی، فنی و مالی … مدنظر قرار گیرند» (PDF 18-19); both recorded as shall with flag (ambiguous). A third «لازم است» (PDF 20, Table 3: «آیا اقدام خاصی لازم است یا خیر») is a descriptive 'whether action is needed', not a directive, and is not counted.
- «ضروری است» (statement of necessity): 5.2 (PDF 17, importance of demonstrating leadership), A.4 (PDF 36, data consistent with intended behaviour), A.10 (PDF 38, consider sector standards). Recorded as info/should with flag because annexes are informative and the phrase is not «باید».
- «الزامی است» only in clause 2 (normative references boilerplate, PDF 10).
- Mood verbs «می‌تواند / می‌توان» are recorded as may; «ممکن است» as info.
- Action: all flagged items carry wording_printed and modality_flag; none was upgraded silently.

## U4. How the standard relates to ISO 31000 - nothing printed
- Page: PDF 8 (introduction), PDF 10 (clauses 2-3).
- The string «ISO 31000» does not occur on any page (checked in the native text layer and on the images). The only link is the national number INSO 13245:1398 («مدیریت ریسک - رهنمودها») and INSO 13246:1389 («مدیریت ریسک - واژگان»).
- The document does not state which ISO standards these national standards adopt; likewise 23679:1403. Action: international_equivalent_if_printed = null; do not present ISO 31000:2018 / ISO Guide 73:2009 / ISO/IEC 22989:2022 as printed in this document.

## U5. Table پ-1 (Annex C): row label on a continuation fragment
- Page: PDF 46 bottom (printed 37), PDF 47 (printed 38).
- The row labelled «طراحی و توسعه» starts at the bottom of PDF 46 with only the first two lines of each cell. PDF 47 holds one tall row whose cell texts complete those sentences grammatically, but whose label cell reads «درستی‌سنجی و اعتبار سنجی» (with a space inside «اعتبار سنجی»). No separate content block for a verification/validation stage can be identified.
- Why uncertain: either the label belongs to a (content-less) following row, or the print has merged the two stages. Action: both labels recorded as printed; the content is attributed to the «طراحی و توسعه» row by grammar and flagged. The book should not assert distinct verification/validation guidance from this table.
- Also on PDF 48: «عملیات، پایش» and «اعتبارسنجی مداوم» appear as two label cells sharing one content row.

## U6. Table 3: apparent duplication of cell text across two rows
- Page: PDF 20 bottom / PDF 21 (printed 11-12).
- The AI-column text of the row «روابط با ذی‌نفعان داخلی …» and of the next row «روابط و تعهدات قراردادی؛» is almost identical (perceptions affected by opacity/bias; needs met by special-purpose systems; need to educate stakeholders), with one extra item (privacy / fundamental rights expectations) in the second. Recorded as printed; whether the source ISO table repeats this cannot be checked here.

## U7. Table پ-1, last row: monitoring cell wording
- Page: PDF 50 (printed 41). The monitoring-and-review cell of «از رده خارج کردن یا جایگزینی» repeats the «اثبات تحقق‌پذیری مفهوم» (POC) wording of the Inception row. Possibly a print duplication; recorded as printed.

## U8. Terminology is not uniform inside the document (affects book vocabulary)
- «ارتباط و مشاوره» (introduction PDF 8, 5.4.5 PDF 22) vs heading 6.2 «تبادل اطلاعات و مشاوره» (PDF 23) for communication and consultation; «ثبت و گزارش» (introduction) vs heading 6.7 «مستندسازی و گزارش».
- «بافت» (introduction, headings 5.4.1/6.3, footnote «Context») vs «زمینهٔ کاربرد» in 6.3.1 body text; the PDF 8 note says INSO 13245:1398 uses «فضا» for context.
- «ارزیابی مخاطره» (6.4 assessment) vs «ارزشیابی مخاطره» (6.4.4 evaluation) vs «ارزشیابی» (5.6 framework evaluation); 6.4.3.2/6.4.3.3 use «ارزیابی پیامد / احتمال».
- Table 1 footnotes b, c, f: this document uses «جامع» (comprehensive), «همخوان» (consistent), «فراگیر» (inclusive); the print states that INSO 13245:1398 uses «فراگیر», «پایدار», «جامع» respectively. Table 4 footnote b: «هم‌خوانی» (consistency) here vs «ثبات» in 13245.
- Headings 6.4.2.4 and 6.4.2.6 both contain «پیامدها»; only 6.4.2.6 has the English footnote «Consequences» (6.4.2.4 footnote «Events» is attached to «رویدادهای»).
- Action: pairs recorded in terms.json (vocabulary_footnote_pairs); no harmonisation attempted.

## U9. Print typos in English footnotes (recorded verbatim)
- «Far Reachin» (Table 1 footnote e, PDF 16); «Treatment risk» (5.1 footnote 1, PDF 16) vs «Risk treatment» (6.5 footnote, PDF 32); Table 1 footnote letters run a-i then l-q (no j, k); «Legals» in the INSO boilerplate on PDF 3.

## U10. Vocabulary pairs: anchor phrase partly inferred
- terms.json / vocabulary_footnote_pairs: the English word is as printed in the footnote; the Persian anchor phrase was identified by reading the sentence where the superscript sits. For superscripts attached inside longer phrases the exact span can be one word longer or shorter than recorded. Re-check on the page before quoting a pair in the book.
- Clause 3 defines no terms, so terms.json «terms» is empty on purpose (not an omission).

## U11. Table 1 column heading wording
- PDF 11: the right-most data column is headed «پیامدهای احتمالی توسعه و استفاده از هوش مصنوعی» with footnote a «Implications»; the Persian reads as "possible implications [of] development and use of AI" - no ezafe between «احتمالی» and «توسعه». Recorded as printed.

## U12. Native text layer
- The PDF text layer is corrupted (e.g. «هومص» abbreviation, digits). Every verbatim field was read on the page image; native text was used only to locate pages and to count modal words (page-level counts of «باید», «موظف», «الزامی», «ضروری»).

## U13. Dates
- Approval date printed «۱۴۰۳/۰۸/۰۶» (745th session of the national committee for information technology standards) - read twice, including a 2x crop. No Gregorian equivalent is printed; none is asserted. Cover gives «2024» for the edition year; «چاپ اول ۱۴۰۳» is the only publication information.

## U14. Cross-references used in the text
- 5.1 cites INSO 23682:1403 as [1] and 6.3.3 cites INSO 22910:1400; bibliography [1] = «فناوری اطلاعات - حکمرانی فناوری اطلاعات - پیامدهای حکمرانی استفاده از هوش مصنوعی در سازمان‌ها» (23682:1403), [2] = «مسئولیت اجتماعی و خیر همگانی - راهنما» (22910:1400). These are listed in the bibliography only; they are not normative references. Their ISO equivalents are not printed.

## OCR cross-check (BRIEF step 5)
- OCR text for all 51 pages (Tesseract fas+eng) became available after I had already read every page on the images and written the outputs; the cross-check below was run afterwards (script: difflib.SequenceMatcher best-window ratio on text normalised for digits, ye/kaf, ZWNJ, diacritics, spaces, quotes/punctuation).
- Checked: 86 outline headings, 5 table titles, 83 vocabulary pairs (English word on the footnote page; Persian anchor on the footnote page and, because the anchor usually sits 1-5 pages before the footnote list, also on nearby pages), clause 3 sentence, 16 identity strings.
- Result: English footnote words: 82 of 83 exact substring matches, 1 at 0.80-0.99; Persian anchors (nearby pages): 63 exact, 8 in 0.80-0.99, 12 below 0.80; outline: 69 exact (many are short generic headings such as «کلیات», so not discriminating), 12 in 0.80-0.99, 5 below 0.80; tables 4 exact + 1 at 0.913; identity 7 exact, 4 in 0.80-0.99, 2 below 0.80. Per-item ratios are stored in the JSON files (ocr_similarity, ocr_similarity_en, ocr_similarity_fa_nearby_pages, ocr_crosscheck_similarity).
- Items below 0.80, all re-read on the page image (2x crops or full page) - none changed my transcription; cause = Tesseract failure, not a transcription error:
  - Cover title (PDF 1; 0.717): Tesseract dropped the first title line «فناوری اطلاعات –» and misread the Persian edition number/year as «yyvry» / «۱۴۳۰۳» (image: ۲۳۷۳۳ and ۱۴۰۳).
  - Foreword heading «پیش‌گفتار» (PDF 7; 0.75) and the approval sentence «هفتصد و چهل و پنجمین اجلاسیه … ۱۴۰۳/۰۸/۰۶» (PDF 7; 0.439): the OCR dropped a whole line («چهل و پنجمین اجلاسیه … تصویب شد. اینک این») and garbled neighbours; read on a 2x crop of the image - session «هفتصد و چهل و پنجمین» (745th), date «۱۴۰۳/۰۸/۰۶».
  - Generic headings «تطبیق» (5.7.1, PDF 22), «کلیات» (6.4.1 and 6.4.2.1, PDF 26): OCR garbled the heading line; images show «۱-۷-۵ تطبیق», «۱-۴-۶ کلیات», «۱-۲-۴-۶ کلیات».
  - Persian anchors of footnotes in table cells or small superscript contexts (OCR drops superscripts and cell text): «یکپارچه‌سازی» (PDF 8), «سفارشی» and «موضوع‌های داده» (Table 1, PDF 12), «انسان در حلقه» and «هم‌خوانی» (Table 4, PDF 25), «دعاوی حقوقی» (PDF 30), «آسیب مادی» (PDF 31), «تنوع کافی» (PDF 36), «تعداد داده» (PDF 37), «نوسانات فرعی» (PDF 42), «مؤلفه» (PDF 44), «اثبات تحقق‌پذیری مفهوم» (Table پ-1 cell, PDF 46). All confirmed on the images.
  - Table 1 footnote «l -Data Modalities»: OCR shows the footnote letter detached (read as «1»); the image shows the letter «l» (letters j and k are not used). Left as «l».
- Not covered by OCR checking: statement_fa paraphrases (not verbatim), summary_fa fields, notes; these rest on visual reading only.
