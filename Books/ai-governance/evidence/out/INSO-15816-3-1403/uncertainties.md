# Uncertainties — INSO-15816-3-1403 (INSO 15816-3:1403 = ISO/IEC TS 38505-3:2021)

Format: item | page (pdf / printed) | why uncertain | what I did.

## A. Printed inconsistencies (record as printed; do not "fix" in the book without saying so)

1. **Clause 3.13 cross-reference points to the wrong term.** | pdf 14 / printed 5 | The definition of «مالک PII» says the person to whom «اطلاعات هویتی شخصی PII (۳-۱۵)» relates. Clause 3.15 is «داده‌های ساختاریافته»; PII is 3.12. The same definition also calls PII «اطلاعات هویتی شخصی», while the 3.12 entry is titled «اطلاعات قابل‌شناسایی شخصی / داده‌های شخصی». | Re-read at 300 dpi; transcribed as printed in terms.json (3.13). Treat as a printed error/terminology slip; cite 3.12 for PII.
2. **Figure 2 label vs caption.** | pdf 21 / printed 12 | The diagram's left ellipse is labelled «ISO/IEC TR 38505-1», but the caption, clauses 3.11, 5.2.1, 5.2.3 and the introduction all print «ISO/IEC 38505-1» (no TR). 38505-2 is consistently «ISO/IEC TR 38505-2». | Recorded the label verbatim in tables_figures.json with a [sic] note; use «ISO/IEC 38505-1» (text/caption) when citing.
3. **Series statement vs "Parts 1 and 2".** | pdf 9 / printed ط; pdf 35 / printed 26 | The مقدمه says INSO 15816 has this part plus ONE other, «قسمت ۱: کاربرد استاندارد ISO/IEC 38500 در حکمرانی داده‌ها» (bibliography [15] gives it as INSO 15816-1:1398). No INSO 15816-2 is printed anywhere. ISO/IEC TR 38505-2 («Part 2: Implications of ISO/IEC 38505-1 for data management») appears only as an international reference ([16]; 5.2.1; Figure 2) and has NO national number in this document. | Do not claim an INSO 15816-2 exists on the basis of this PDF; cite TR 38505-2 as the international document only.
4. **Title variants.** | cover/pdf 4/pdf 8 vs pdf 10 | «طبقه‌بندی داده‌ها» (plural) on cover, committee page, foreword; «طبقه‌بندی داده» (singular) in the clause-1 title block. Cover English edition line says «1st Edition 2024» (= 1403). | identity.json uses the plural cover form.
5. **Role definitions differ in wording between clause 3 and 5.3.4.1.** | pdf 12 vs pdf 24 | 3.9 «متصدی داده» (data steward) = role ensuring activities conform to policies set by data governance; 5.3.4.1 «متصدی داده» = senior employee who decides how classification is fully implemented. «سرپرست داده» (footnote: Data Custodian) is used in 5.3.4.1 but is not defined in clause 3. | Both recorded as printed (terms.json 3.9; statements 5.3.4.1).
6. **Spelling variants inside the document** | pdf 11 etc. | «واپایشگر» (3.3 title) vs «واپایش‌گر / واپایش گر» (3.4 definition); «فراداده» vs «فرایند/فرآیند». | Transcribed as printed.

## B. Ambiguous normative force (no «باید»/shall exists in clauses 1–7; the whole TS is guidance)

7. **5.3.2 (pdf 23 / printed 14)** — «… نیروی محرک طبقه‌بندی داده‌ها هستند را تعریف نمایید.» is a plain imperative with no «توصیه می‌شود». | Recorded modality `should` (the standard has no shall-level text, and neighbouring clauses are all recommendations); flagged here because grammatically it is stronger than «توصیه می‌شود». Do not present as a requirement.
8. **6.4 (pdf 30 / printed 21)** — «مقرر می‌شود که برخی راهنمایی‌ها … ارائه شود» («it is stipulated/envisaged that some guidance … be provided»). | Not a «باید» nor «توصیه می‌شود»; reads as a statement of intent about future guidance. Recorded `info`.
9. **5.4 (pdf 27 / printed 18)** — «گهگاهی لازم است به‌روزرسانی … مدنظر قرار گیرد». | «لازم است» here means "it is sometimes necessary", i.e. a statement of need/occasion, not a requirement to update on a schedule. Recorded `info`.
10. **1 (pdf 10)** scope sentence contains «توصیه می‌شود» inside a relative clause describing what the standard specifies. | Recorded `info` (the sentence is descriptive); the recommendation itself is not a free-standing statement.
11. **Definition notes with «توصیه می‌شود»** (3.12 note, 3.15 note 1) are inside terminology notes; they are recommendations about interpreting/communicating the term, not normative definitions.
12. «پیشنهاد می‌شود» (5.3.3.1 "two methods are suggested"; 6 "a framework is suggested") is weaker/equal to «توصیه می‌شود»; statements recorded as should/info accordingly.
13. **7.8 (pdf 33)** carries the modal «توصیه می‌شود» but the verb is second-person («باشید»); recorded `should`.

## C. Figures/tables read from images only (no reliable text layer)

14. **Figure 4 (pdf 32 / printed 23).** In organisation «الف» the second of five bands is partly hidden behind the arrow and its label is not legible; four labels are readable (بسیار حساس، استفاده داخلی، عمومی، طبقه‌بندی نشده). Organisation «ب» also shows a thin unlabeled sliver between bands 1 and 2. | Reported as hidden/unreadable, nothing inferred.
15. **Figure 3 (pdf 28 / printed 19).** One activity label in the «شناسایی» phase is printed with overlapping text («خط‌مشی…»/«دارایی»). 6.2 names the first activity «شناسایی دارایی‌ها», so the label is recorded as asset identification with an overlap note. The figure writes «تایید سیاست داده» where 6.1 writes «تأیید خط‌مشی داده».
16. **Table 1 (pdf 17–18 / printed 8–9).** The table body is on pdf 17; pdf 18 (printed 9) holds only a repeated header row and the footnote letters a–e (Aggregated Data, Summary Statistics, Derived Data, Telemetry, Anonymized Data). The Persian cells for «محرمانه، دارای محدودیت استفاده، استفاده داخلی، عمومی» are recorded as printed (no English counterpart printed).
17. **3.15 notes 2–3 and the source line are on pdf 15 / printed 6**, although the term heading, definition and note 1 are on pdf 14 / printed 5 (terms.json lists pdf 14). Similarly 3.12's note is on pdf 14 (heading+definition pdf 13) and 3.2's note is on pdf 11 (heading+definition pdf 10).

## D. Numbers and conventions

18. **Clause numbers** are printed in RTL visual form (clause 4.1.2 is displayed «۲-۱-۴»). All clause numbers and source-clause citations in these files are normalised to dotted logical order (e.g. «زیربند 3.1.2 استاندارد ملی … ۲۳۶۸۳»). The digit order of every printed cross-reference was cross-checked between the page image and the OCR stream; 3.13's «(۳-۱۵)» in logical order reads "3-15", i.e. clause 3.15 (see item 1).
19. **Page numbering**: body printed = pdf − 9 (pdf 10 = printed 1 … pdf 36 = printed 27); front matter printed with Persian abjad letters ب…ط on pdf 2–9 (cover pdf 1 unnumbered). In JSON: integers for body, letter strings for front matter, null for the cover.
20. **WEF figures in 4.1.1 (pdf 15 / printed 6)**: «بین سال‌های ۲۰۱۸ تا ۲۰۲۲ دوبرابر و سپس در عرض سه سال مجدداً دوبرابر»; «۵ میلیارد جستجو در روز»; «۴۵۶ هزار توییت در هر دقیقه». Digits re-checked at 300 dpi and against OCR. They are the standard's own secondary citation of a WEF estimate (dated); no WEF source is given in the bibliography.
21. **TOC depth**: the printed contents lists only two levels; sub-subclauses (e.g. 5.2.x, 5.3.4.x, 5.3.6.x) were taken from the body pages. Every TOC page number matches the body (pdf = printed + 9).
22. **Clause types** in outline.json (normative / informative) are MY assignment by role (1–3 and 5–7 = body clauses; 4 = background; bibliography = informative). The document itself does not label clauses normative/informative and has no annexes.
23. **Ezafe hamza**: in a few Persian words (e.g. «دادهٔ», «حوزهٔ») I could not always tell on the 150–300 dpi image whether the printed ezafe hamza mark is present; transcriptions follow the visible glyphs and standard orthography. This affects orthography only, not meaning.

## E. OCR cross-check (BRIEF step 5)

24. Method: normalised (digits, ZWNJ, hamza/diacritics, punctuation removed) best-window difflib ratio against the page OCR (page, +1, −1). 119 verbatim items checked (15 term titles, 15 definitions, 18 notes, 65 clause headings, 6 table/figure titles). All items ≥ 0.80 except five, which I re-read on the page images and on 300 dpi crops; each matches the image and is OCR damage (Tesseract bidi/ligature errors), not transcription error:
   - 3.4 definition 0.591 — OCR scrambles the line order and the italic cross-reference «واپایش‌گر داده (۳-۳)».
   - 3.6 definition 0.673 — OCR drops «بالقوهٔ» and splits the line.
   - 3.10 note 1 0.701 — OCR garbles «یادآوری» and the footnote markers.
   - 3.13 definition 0.682 — OCR garbles «PII» and the cross-reference.
   - heading 5.3.7 0.759 — OCR garbles «مدیریت» (image reads «بازخورد مدیریت به نهادهای حکمران»; TOC does not list this level).
   Clause headings are scored against both the body page and (for two-level headings) the TOC; heading 1 (body 0.645, OCR garbles «دامنهٔ») scores 0.968 on the TOC, and 5.3 (TOC 0.6; body 1.0), 6.3 (TOC 0.75; body 1.0), 6.4 (TOC 0.706; body 0.90) are fine on the body page. The only heading with no good OCR match on either page is 5.3.7 (above).
   `ocr_similarity` in terms.json = minimum over definition and note ratios for that term (not over the source line).
25. Native PDF text layer was not used for any verbatim field.
