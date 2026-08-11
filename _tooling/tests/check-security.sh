#!/usr/bin/env bash
# check-security.sh — forbidden-pattern + capability/nonce heuristics for the
# kohandezh-knowledge plugin + theme. Non-destructive (read-only grep).
# Exit 0 = clean, 1 = findings.
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PLUG="$ROOT/_tooling/wp-theme/kohandezh-knowledge"
THEME="$ROOT/_tooling/wp-theme/kohandezhcv"
status=0

echo "== forbidden patterns (eval/exec/remote file_get_contents/base64_decode/hardcoded keys) =="
for d in "$PLUG" "$THEME"; do
  hits=$(grep -rnE 'eval\(|\bexec\(|\bshell_exec\(|\bsystem\(|file_get_contents\(\s*["'\'']https?:|base64_decode\(|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,}' "$d" --include='*.php' 2>/dev/null)
  if [ -n "$hits" ]; then echo "FAIL $d"; echo "$hits"; status=1; else echo "OK   $d clean"; fi
done

echo ""
echo "== REST permission callbacks present on every register_rest_route (plugin) =="
n=$(grep -c "register_rest_route" "$PLUG"/includes/*.php 2>/dev/null)
p=$(grep -cA6 "register_rest_route" "$PLUG"/includes/*.php 2>/dev/null | grep -c "permission_callback")
echo "routes=$n  with permission_callback≈$p"

echo ""
echo "== nonce verification on admin POST handlers =="
for h in admin_post_ ; do
  hits=$(grep -rn "add_action.*$h" "$PLUG" --include='*.php' 2>/dev/null)
  echo "$hits" | head -5
done
grep -rn "check_admin_referer\|check_ajax_referer\|wp_verify_nonce" "$PLUG" --include='*.php' 2>/dev/null | head

echo ""
echo "== escaping/sanitization spot check (count) =="
echo "esc_* in plugin: $(grep -roE 'esc_(html|attr|url|js|textarea)\(' "$PLUG" --include='*.php' | wc -l | tr -d ' ')"
echo "sanitize_* in plugin: $(grep -roE 'sanitize_[a-z_]+\(' "$PLUG" --include='*.php' | wc -l | tr -d ' ')"

exit $status
