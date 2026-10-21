# Evidence-extraction brief (edition 2, INSO standards) — read fully before starting

You are extracting **verified evidence** from ONE Iranian national standard (INSO) PDF so that a Persian book
("راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی") can cite it accurately. You are NOT writing book chapters.

## Hard rules
1. **Ground truth = the rendered page image.** The PDF's native text layer is CORRUPTED (systematic wrong Persian
   letters, e.g. «اهداف»→«اهداا», «عمومی»→«عهومی», «تصمیم»→«تصهیم», «تشخیص»→«تشخیو», «هوش مصنوعی (هومص)»→«هومو»,
   «بالاترین»→«باالترین», digits reordered). Tesseract OCR is better but still makes letter/digit errors.
   Never copy native text or OCR into a verbatim field without checking the page image with the Read tool.
2. **Verbatim fields are only for short items**: clause numbers, headings, defined terms, definitions and their notes
   (clause 3), table/control IDs and titles. Everything else is a **short neutral paraphrase in Persian** — do not
   transcribe whole paragraphs, tables of prose, or entire annexes (copyright / reuse-permission caution).
3. **Numbers are critical**: years, standard numbers, clause numbers, page numbers, counts. Re-check each on the image.
4. **Preserve normative force.** Record the modality of each statement exactly as printed:
   «باید» (shall/requirement), «بهتر است/توصیه می‌شود» (should/recommendation), «می‌تواند/می‌توان» (may/can/permission),
   or informative text/note. Never upgrade a recommendation to a requirement. If the INSO wording is ambiguous, say so.
5. **Do not invent.** If a field is not printed in the document, write `null` and say so in `notes`. Do not guess
   direct URLs, certification claims, committee names, or dates that are not on the page.
6. Keep the **printed page number** (the number shown on the page footer/header) separate from the **PDF page number**
   (1-based physical page). Record both for every cited item (`printed_page` may be `null` for front matter).
7. Prefer breadth over depth on boilerplate: front matter beyond identity, bibliography, and index pages are NOT needed.
8. Do not modify anything outside your own output directory. Do not touch the PDFs, `Raw/`, `master/`, `state/`, `config/`.
   Do not call any external model/API; use only the local files and the Read tool for images.

## Where things are (all absolute)
- Staging root: `/Users/emperor/Documents/AI/kohandezh-archive/ai-governance-e2`
- Source PDF: `<root>/Raw/New/<filename>` (read-only)
- Rendered pages (150 dpi PNG): `<root>/evidence/render/<SOURCE-ID>/p-NN.png` — the number of digits equals the digit
  count of the total page count (≤99 pages → `p-01.png`; ≥100 pages → `p-001.png`). Use `ls` to confirm.
- Tesseract OCR text (Persian+English, may be incomplete while OCR is still running): `<root>/evidence/ocr/<SOURCE-ID>/p-NN.txt`
- Native pdftotext page JSON (corrupted letters; useful only to locate pages quickly):
  `/Users/emperor/Documents/AI/kohandezh-archive/ai-standards-audit-20261017-vKr2Ds/edition-2/extracted/<SOURCE-ID>/page-XXXX.json`
- Your outputs: `<root>/evidence/out/<SOURCE-ID>/` (create it). Write JSON as UTF-8 with `ensure_ascii=False`, indent 1.

## Workflow
1. Read the cover/front matter pages and locate: Persian title, national number and Iranian year, the international
   designation exactly as printed (including TR/TS and edition/year), the adoption/equivalence statement,
   the national preface facts (publisher, any committee/approval date printed), and the PDF↔printed page offset.
2. Read the table of contents pages (view the images) and build the full clause tree.
3. Read clause 3 (terms and definitions) on the images and transcribe every entry.
4. Read the main clauses/annexes named in your assignment and record statements (paraphrase + modality + pages).
5. Cross-check every verbatim item against the OCR text with a small script (difflib ratio); anything below 0.80
   similarity must be re-read on the image and noted in `uncertainties`.
6. Write the output files (schemas below) and finish with a SHORT report (<= 250 words): files written, counts,
   open uncertainties, anything surprising (e.g. a printed number that contradicts the assignment's expectation).

## Output files (all in `<root>/evidence/out/<SOURCE-ID>/`)
### identity.json
```
{"source_id","national_reference_printed","iranian_year_printed","title_fa_printed",
 "international_reference_printed","international_year","equivalence_statement_printed","adoption_reference_printed",
 "publisher_printed","approval_or_publication_date_printed","pdf_pages_total","pdf_to_printed_offset",
 "normative_references":[{"printed_reference","national_number_if_printed","pdf_page"}],
 "notes","uncertainties":[...]}
```
### outline.json
`{"source_id","clauses":[{"number","heading_fa","heading_en_if_printed","type":"normative|informative|front|annex","pdf_page","printed_page"}]}`
### terms.json (clause 3 or the vocabulary clause)
`{"source_id","terms":[{"clause","term_fa","term_en","definition_fa","notes_fa":[...],"pdf_page","printed_page","verified_visually":true,"ocr_similarity":0.0}]}`
### statements.json (main requirement/guidance content)
`{"source_id","statements":[{"clause","topic_fa","statement_fa","modality":"shall|should|may|info","pdf_page","printed_page"}]}`
 Keep each `statement_fa` to 1–2 sentences. Capture ALL "shall" items for requirement standards; for guidance
 standards capture the key principles/steps/roles/outputs (typically 60–150 statements), not every sentence.
### tables_figures.json
`{"source_id","items":[{"kind":"table|figure","id","title_fa","pdf_page","printed_page","summary_fa","rows":[...optional compact rows: id + short title only...]}]}`
### uncertainties.md
Plain list: item, page, why uncertain, what you did.
