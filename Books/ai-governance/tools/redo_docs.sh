#!/bin/zsh
# Re-extract (with line-number stripping), re-chunk and re-translate specific documents. Usage: tools/redo_docs.sh DOC_KEY [DOC_KEY...]
set -u
cd /Users/emperor/Documents/AI/kohandezh.com/Books/ai-governance
PY=.venv313/bin/python
LOG=work/logs/redo_docs.log
for d in "$@"; do
  echo "=== $(date) redo $d ===" >> $LOG
  $PY tools/extract2.py --doc $d --load-db 2>&1 | grep -v -E "pymupdf_layout|Document parser|Tesseract|OCR on page" >> $LOG
  $PY - "$d" <<'EOF' >> $LOG 2>&1
import sys, sqlite3
d = sys.argv[1]
con = sqlite3.connect("state/project.db"); con.execute("PRAGMA journal_mode=WAL")
for t in ("translations", "reviews", "approved"):
    con.execute(f"DELETE FROM {t} WHERE chunk_id LIKE ?", (d + "::%",))
n = con.execute("DELETE FROM chunks WHERE doc_key=?", (d,)).rowcount
con.execute("DELETE FROM tm WHERE source_refs LIKE ?", ("%" + d + "::%",))
con.commit(); print(f"purged {n} chunks + translations/reviews for {d}")
EOF
  rm -rf "work/translations/$d" "work/reviews/$d" "work/checkpoints/$d"
  $PY tools/normalize_sources.py > /dev/null 2>&1
  $PY tools/chunk.py --doc $d >> $LOG 2>&1
  $PY tools/pipeline.py run --no-review --translate-workers 8 --review-workers 1 --escalation-workers 1 --doc $d >> $LOG 2>&1
done
echo "REDO_DONE $(date)" >> $LOG
