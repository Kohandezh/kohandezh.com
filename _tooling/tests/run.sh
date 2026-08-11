#!/usr/bin/env bash
# run.sh — Kohandezh Knowledge test runner (Tier 0–4).
# Safe: writes nothing to the real theme. Uses the live dev server on :8735 if up.
# Exit 0 = all pass, 1 = any fail.
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PLUG="$ROOT/_tooling/wp-theme/kohandezh-knowledge"
THEME="$ROOT/_tooling/wp-theme/kohandezhcv"
SYNC="$ROOT/_tooling/wp-theme/sync-from-static.py"
BASE="${BASE_URL:-http://127.0.0.1:8735}"
PASS=0; FAIL=0
ok(){ printf "  ✓ %s\n" "$1"; PASS=$((PASS+1)); }
bad(){ printf "  ✗ %s\n" "$1"; FAIL=$((FAIL+1)); }
section(){ echo ""; echo "── $1 ──"; }

section "Tier 0 — static checks"
python3 -m py_compile "$SYNC" 2>/dev/null && ok "py_compile sync-from-static.py" || bad "py_compile sync-from-static.py"

phpfiles=$(find "$PLUG" "$THEME" -name '*.php' -type f 2>/dev/null)
phplint_fail=0
for f in $phpfiles; do php -l "$f" >/dev/null 2>&1 || { bad "php -l $f"; phplint_fail=1; }; done
[ $phplint_fail -eq 0 ] && ok "php -l ($(echo "$phpfiles" | wc -l | tr -d ' ') files)" || true

if command -v node >/dev/null 2>&1; then
  jsfail=0
  for j in "$ROOT"/assets/js/main.js "$ROOT"/assets/js/ai-pet.js; do [ -f "$j" ] && { node --check "$j" 2>/dev/null || { bad "node --check $j"; jsfail=1; }; }; done
  [ $jsfail -eq 0 ] && ok "node --check (custom JS) [optional]"
else
  echo "  (skip) node not available"
fi

forb=$(grep -rnE 'eval\(|\bexec\(|file_get_contents\(\s*["'\'']https?:|base64_decode\(' "$PLUG" "$THEME" --include='*.php' 2>/dev/null)
[ -z "$forb" ] && ok "forbidden-pattern grep clean" || { bad "forbidden patterns found"; echo "$forb"; }

section "Tier 1 — sync guardrail (parity)"
dr=$(python3 "$SYNC" --dry-run 2>&1)
echo "$dr" | grep -q "pipeline is in parity" && ok "sync dry-run parity (0 create/0 update/0 delete)" || { bad "sync not in parity"; echo "$dr"; }

section "Tier 2 — Layer A integrity (live: $BASE)"
if curl -sf -o /dev/null "$BASE/" 2>/dev/null; then
  for p in "/" "/fa.html" "/ar.html" "/de.html" "/es.html" "/fr.html" "/tr.html" "/zh.html" "/ja.html" "/PSN.html" "/Certificates.html" "/blog/" "/portfolio/"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE$p")
    [ "$code" = "200" ] && ok "200 $p" || bad "HTTP $code $p"
  done
  code404=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/this-page-does-not-exist-xyz")
  [ "$code404" = "404" ] && ok "404 on bad path ($code404)" || bad "expected 404, got $code404"
  # JSON-LD on home + fa
  python3 "$ROOT/_tooling/tests/validate-jsonld.py" "$BASE/" >/tmp/kbk-vl-home.txt 2>&1 && ok "JSON-LD valid on /" || { bad "JSON-LD on /"; tail -5 /tmp/kbk-vl-home.txt; }
  python3 "$ROOT/_tooling/tests/validate-jsonld.py" "$BASE/fa.html" >/tmp/kbk-vl-fa.txt 2>&1 && ok "JSON-LD valid on /fa.html" || { bad "JSON-LD on /fa.html"; tail -5 /tmp/kbk-vl-fa.txt; }
  # canonical/hreflang counts on a CV page
  canon=$(curl -s "$BASE/fa.html" | grep -c 'rel="canonical"')
  href=$(curl -s "$BASE/fa.html" | grep -c hreflang)
  [ "$canon" = "1" ] && [ "$href" = "11" ] && ok "fa.html canonical=1 hreflang=11" || bad "fa.html canonical=$canon hreflang=$href"
else
  bad "dev server not reachable at $BASE (start ./run-dev.sh)"
fi

section "Tier 3 — Layer B static checks (plugin not installed locally → live tests skipped)"
# Isolation contract: JSON-LD emitter + template loader gated by is_layer_b()
grep -q "KBK_Routes::is_layer_b" "$PLUG/includes/class-kbk-schema.php" && ok "JSON-LD gated by is_layer_b()" || bad "JSON-LD not gated"
grep -q "! self::is_layer_b()" "$PLUG/includes/class-kbk-routes.php" && ok "template_include gated by is_layer_b()" || bad "template_include not gated"
# No global enqueue
g=$(grep -rnE "wp_enqueue_script|wp_enqueue_style" "$PLUG" --include='*.php' 2>/dev/null)
[ -z "$g" ] && ok "no global enqueue in plugin" || { bad "unexpected enqueue"; echo "$g"; }
# Feature flags defined
grep -q "KBK_FEATURE_NEWS_FETCH" "$PLUG/kohandezh-knowledge.php" && ok "feature flags wired" || bad "missing feature flags"
# REST hides unverified
grep -q "HIDDEN_EVIDENCE" "$PLUG/includes/class-kbk-rest.php" && ok "REST hides unverified/disputed/deprecated" || bad "REST visibility rule missing"

section "Tier 4 — Layer A isolation (homepage must contain zero Layer B markers)"
if curl -sf "$BASE/" >/dev/null 2>&1; then
  body=$(curl -s "$BASE/")
  for marker in "kbk-layer-b" "kohandezh/v1" "Enterprise AI Hub"; do
    c=$(printf '%s' "$body" | grep -c "$marker")
    [ "$c" = "0" ] && ok "home has no '$marker'" || bad "home contains '$marker' (isolation leak)"
  done
else
  echo "  (skip) dev server not up"
fi

echo ""
echo "════════════════════════════════════════"
echo "  PASS=$PASS  FAIL=$FAIL"
[ $FAIL -eq 0 ] && echo "  RESULT: ALL GREEN ✅" || echo "  RESULT: FAILURES ❌"
echo "════════════════════════════════════════"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
