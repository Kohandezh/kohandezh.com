#!/bin/zsh
# Phases 3b–7: re-extract line-numbered drafts, normalize sources, chunk, book structure, terminology mining + GLM proposals.
# Idempotent; safe to re-run. Logs → work/logs/prep.log
set -u
cd /Users/emperor/Documents/AI/kohandezh.com/Books/ai-governance
PY=.venv313/bin/python
LOG=work/logs/prep.log
exec > >(tee -a "$LOG") 2>&1
echo "=== PREP START $(date) ==="
for d in NIST-AI-100-4-IPD NIST-AI-800-1-IPD; do
  echo "--- re-extract $d (line numbers) ---"
  $PY tools/extract2.py --doc $d --load-db 2>&1 | grep -v -E "pymupdf_layout|Document parser|Tesseract|OCR on page"
done
echo "--- normalize sources ---"; $PY tools/normalize_sources.py
echo "--- chunk ---"; $PY tools/chunk.py
echo "--- book structure ---"; $PY tools/book_structure.py
echo "--- terminology: mine ---"; $PY tools/terms_mine.py mine
echo "--- terminology: propose (GLM-5.3) ---"; $PY -u tools/terms_mine.py propose
echo "=== PREP END $(date) ==="
echo "PREP_DONE"
