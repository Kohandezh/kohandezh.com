#!/bin/zsh
# Deterministic post-review build chain (run after all chunks are APPROVED and the editorial layer is generated).
# Usage: tools/run_post_review.sh [RELEASE_ID]   — logs → work/logs/post_review.log
set -u
cd /Users/emperor/Documents/AI/kohandezh.com/Books/ai-governance
PY=.venv313/bin/python
LOG=work/logs/post_review.log
REL=${1:-RC1}
exec > >(tee -a "$LOG") 2>&1
echo "=== POST-REVIEW BUILD START $(date) release=$REL ==="
echo "--- master (with editorial) ---"; $PY tools/master.py
echo "--- RAG + search ---";           $PY tools/rag.py
echo "--- knowledge graph (GLM) ---";  $PY -u tools/kg.py
echo "--- HTML ---";                   $PY tools/build_html.py
echo "--- DOCX ---";                   $PY tools/build_docx.py 2>&1 | grep -v FutureWarning
echo "--- PDF ---";                    $PY tools/build_pdf.py 2>&1 | grep -v -i -E "warning|deprecat"
echo "--- release + signing ---";      $PY tools/release.py "$REL"
echo "--- HTML (verify page with manifest) ---"; $PY tools/build_html.py
echo "--- release (re-zip HTML) ---";  $PY tools/release.py "$REL"
echo "--- verify ---";                 $PY tools/verify_release.py release
echo "--- release gate ---";           $PY tools/qa_final.py
echo "=== POST-REVIEW BUILD END $(date) ==="
echo "POST_REVIEW_DONE"
