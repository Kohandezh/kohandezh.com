#!/bin/zsh
# Edition build chain for the book root this script lives in (never any other root). Usage: tools/run_e2_build.sh [RELEASE_ID] [BASE_E1_ROOT]
# Order: master -> E1/E2 map -> carry KG cache -> RAG -> KG -> HTML -> DOCX -> PDF -> release -> HTML (verify page) -> release -> verify -> release gate -> E2 gate.
# No `set -e`: every step's exit code is reported and the first failure stops the chain.
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 2
PY=${PY:-python3}
REL=${1:-E2-RC1}
BASE=${2:-/Users/emperor/Documents/AI/kohandezh.com/Books/ai-governance}
mkdir -p work/logs
LOG=work/logs/e2_build.log
exec > >(tee -a "$LOG") 2>&1
echo "=== E2 BUILD START $(date) root=$ROOT release=$REL ==="
step() { local name="$1"; shift; echo "--- $name ---"; "$@"; local rc=$?; if [ $rc -ne 0 ]; then echo "STEP FAILED: $name (exit $rc)"; echo "E2_BUILD_FAILED"; exit $rc; fi; }
step "templates" $PY tools/e2_materialize_templates.py --root "$ROOT"
step "master" $PY tools/master.py
# hashed example IDs inside editorial text depend on this build's content IDs: refresh them, and rebuild the master only if something changed
if $PY tools/e2_refresh_hashed_ids.py --root "$ROOT" | grep -q '"changed_files": \[\]'; then echo "hashed IDs already current"; else step "master (after hashed-ID refresh)" $PY tools/master.py; fi
step "E1->E2 map" $PY tools/e1_e2_map.py --base-root "$BASE" --root "$ROOT"
step "KG cache carry-over" $PY tools/e2_kg_carry_cache.py --root "$ROOT"
step "RAG + search" $PY tools/rag.py
step "knowledge graph" $PY -u tools/kg.py
step "HTML" $PY tools/build_html.py
echo "--- DOCX ---"; $PY tools/build_docx.py 2>&1 | grep -v FutureWarning; [ ${pipestatus[1]} -eq 0 ] || { echo "E2_BUILD_FAILED docx"; exit 1; }
echo "--- PDF ---"; $PY tools/build_pdf.py 2>&1 | grep -v -i -E "warning|deprecat"; [ ${pipestatus[1]} -eq 0 ] || { echo "E2_BUILD_FAILED pdf"; exit 1; }
step "authoring and review log" $PY tools/e2_authoring_log.py --root "$ROOT"
step "release + signing" $PY tools/release.py "$REL"
step "HTML (verify page with manifest)" $PY tools/build_html.py
step "release (re-zip HTML)" $PY tools/release.py "$REL"
step "verify" $PY tools/verify_release.py release
echo "--- release gate ---"; $PY tools/qa_final.py; echo "qa_final exit=$?"
echo "--- E2 gate ---"; $PY tools/qa_e2.py --root "$ROOT" --base-root "$BASE"; echo "qa_e2 exit=$?"
echo "=== E2 BUILD END $(date) ==="
echo "E2_BUILD_DONE"
