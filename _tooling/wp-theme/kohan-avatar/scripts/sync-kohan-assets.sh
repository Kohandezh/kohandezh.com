#!/usr/bin/env bash
#
# sync-kohan-assets.sh — refresh the plugin's Kohan assets from the
# authoritative artwork source. Copies only approved runtime assets,
# validates JSON, computes SHA-256 hashes, writes assets/kohan/version.json,
# skips unchanged files, and fails non-zero on any validation error.
#
# The source path is fixed here (server-side); it is never taken from a
# web request. Override only via the KOHAN_SRC environment variable in a
# trusted shell / CI context.
#
set -euo pipefail

# --- resolve paths -----------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DEST="$PLUGIN_DIR/assets/kohan"

SRC="${KOHAN_SRC:-/Users/emperor/Documents/Codex/2026-07-17/hatch-pet-users-emperor-codex-skills-3/outputs/Kohan-Artwork}"
SRC_RUNTIME="$SRC/runtime"
SRC_SUPP="$SRC_RUNTIME/codex-live"

log() { printf '%s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# --- verify source -----------------------------------------------------------
[ -d "$SRC" ] || fail "source directory not found: $SRC (mount or set KOHAN_SRC)"
[ -f "$SRC_RUNTIME/spritesheet.webp" ] || fail "missing atlas: $SRC_RUNTIME/spritesheet.webp"
[ -f "$SRC_RUNTIME/spritesheet.json" ] || fail "missing spritesheet.json"
[ -f "$SRC_RUNTIME/moods.json" ] || fail "missing moods.json"
[ -d "$SRC_SUPP" ] || fail "missing supplemental strips dir: $SRC_SUPP"

# --- validate JSON -----------------------------------------------------------
have_python() { command -v python3 >/dev/null 2>&1; }
validate_json() {
  local f="$1"
  if have_python; then
    python3 -c "import json,sys; json.load(open(sys.argv[1]))" "$f" \
      || fail "invalid JSON: $f"
  fi
}
validate_json "$SRC_RUNTIME/spritesheet.json"
validate_json "$SRC_RUNTIME/moods.json"
[ -f "$SRC_RUNTIME/pet.json" ] && validate_json "$SRC_RUNTIME/pet.json"

# --- atlas contract check ----------------------------------------------------
if have_python; then
  python3 - "$SRC_RUNTIME/spritesheet.json" <<'PY' || fail "atlas contract check failed"
import json, sys
d = json.load(open(sys.argv[1]))
lay = d.get("spritesheetLayout", {})
assert lay.get("columns") == 8, "columns must be 8"
assert lay.get("rows") == 11, "rows must be 11"
assert lay.get("cellWidth") == 192, "cellWidth must be 192"
assert lay.get("cellHeight") == 208, "cellHeight must be 208"
PY
fi

# --- copy helper: only write when content differs ---------------------------
mkdir -p "$DEST/supplemental"
copied=0
skipped=0
sha() { shasum -a 256 "$1" 2>/dev/null | awk '{print $1}'; }

copy_if_changed() {
  local from="$1" to="$2"
  [ -f "$from" ] || fail "approved asset missing: $from"
  if [ -f "$to" ] && [ "$(sha "$from")" = "$(sha "$to")" ]; then
    skipped=$((skipped + 1))
  else
    cp "$from" "$to"
    copied=$((copied + 1))
    log "  updated $(basename "$to")"
  fi
}

# runtime atlas + metadata (approved list only)
copy_if_changed "$SRC_RUNTIME/spritesheet.webp" "$DEST/spritesheet.webp"
copy_if_changed "$SRC_RUNTIME/spritesheet.json" "$DEST/spritesheet.json"
copy_if_changed "$SRC_RUNTIME/moods.json"       "$DEST/moods.json"
[ -f "$SRC_RUNTIME/pet.json" ]  && copy_if_changed "$SRC_RUNTIME/pet.json"  "$DEST/pet.json"
[ -f "$SRC_RUNTIME/MOODS.md" ]  && copy_if_changed "$SRC_RUNTIME/MOODS.md"  "$DEST/MOODS.md"

# processed supplemental strips (already normalized; approved names only)
for name in angry colt-threat confused drag-annoyed fall-scared goodbye russian-roulette wink; do
  copy_if_changed "$SRC_SUPP/kohan-$name.webp" "$DEST/supplemental/kohan-$name.webp"
done

# --- write supplemental-sprites.json (hashes + timing) ----------------------
if have_python; then
  python3 - "$DEST" <<'PY'
import hashlib, json, os, sys, time
dest = sys.argv[1]
supp = os.path.join(dest, "supplemental")
# frame counts + timing documented from the authoritative controller.
timing = {
    "kohan-angry.webp":            {"frames": 1, "frameMs": 1250},
    "kohan-colt-threat.webp":      {"frames": 4, "frameMs": [400,350,500,850]},
    "kohan-confused.webp":         {"frames": 1, "frameMs": 1300},
    "kohan-drag-annoyed.webp":     {"frames": 6, "frameMs": 180},
    "kohan-fall-scared.webp":      {"frames": 6, "frameMs": [230,230,300,500,180,240]},
    "kohan-goodbye.webp":          {"frames": 7, "frameMs": [340,320,420,380,360,360,700]},
    "kohan-russian-roulette.webp": {"frames": 6, "frameMs": [420,360,260,260,700,560]},
    "kohan-wink.webp":             {"frames": 1, "frameMs": 850},
}
entries = {}
for fn, meta in timing.items():
    p = os.path.join(supp, fn)
    if not os.path.isfile(p):
        continue
    entries[fn] = {**meta, "sha256": hashlib.sha256(open(p,"rb").read()).hexdigest()}
json.dump({"generatedAt": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
           "sprites": entries}, open(os.path.join(dest, "supplemental-sprites.json"), "w"),
          ensure_ascii=False, indent=2)
PY
fi

# --- write version.json (combined SHA over all approved assets) -------------
combined="$(
  {
    sha "$DEST/spritesheet.webp"
    sha "$DEST/spritesheet.json"
    sha "$DEST/moods.json"
    for f in "$DEST"/supplemental/*.webp; do sha "$f"; done
  } | sort | shasum -a 256 | awk '{print $1}'
)"
now="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
cat > "$DEST/version.json" <<JSON
{
  "hash": "$combined",
  "generatedAt": "$now",
  "spriteVersion": 2,
  "source": "authoritative"
}
JSON
log "  wrote version.json ($combined)"

# --- clear only relevant caches (best-effort; never delete unrelated files) -
if command -v wp >/dev/null 2>&1; then
  wp cache flush >/dev/null 2>&1 || true
fi

log "sync complete: $copied updated, $skipped unchanged"
exit 0
