#!/usr/bin/env bash
# build.sh — minify custom JS/CSS in kohandezh.com/ (idempotent).
#
# Strategy: source files stay untouched (dev-friendly). Their minified
# .min.{js,css} siblings are regenerated on every run. HTML pages always
# reference the .min version so production gets the optimized bundle, and
# developers edit the readable source.
#
# Library files that ship pre-minified (bootstrap.min.css, swiper-bundle.*,
# gsap-bundle.min.js) are skipped. jQuery, Bootstrap's JS and the separate
# gsap/ScrollTrigger/SplitText files were removed from the site entirely.

set -e
cd "$(dirname "$0")"

JS_DIR="assets/js"
CSS_DIR="assets/css"

# Custom (hand-written) JS files — these get a .min.js sibling.
CUSTOM_JS=(
  main.js
  ai-pet.js
  linkedin-content.js
  resume-timeline.js
  404-games.js
  gsapAnimation.js
  portfolio.js
  work-image-localization.js
  home-blog-feed.js
  home-blog-scroll.js
  accessibility-enhancements.js
  carousel.js
  animation-change-text.js
  flow-field-background.js
  cwv-rum.js
  countto.js
  pixel-canvas.js
  locale-router.js
  glowing-effect.js
  demo-and-schedule.js
  limelight-nav.js
  lazy-bundle.js
  kohan-avatar.js
  page-chrome.js
  page-i18n.js
  chat-ui.js
  wisdom-quotes.js
  glare-card.js
  hud-button.js
  pearl-button.js
  timeline-date-fix.js
  contact-forms.js
  bs-lite.js
  cybernetic-grid.js
  kohan-avatar-enhance.js
  # These eight shipped a .min.js sibling but were never listed here, so every
  # edit to them was silently dropped — the clock's page-i18n listener was
  # written, minified nowhere, and the standalone pages kept rendering their
  # authored locale next to translated copy. Anything with a .min sibling
  # belongs in this list. (jquery.shim.js is the one deliberate exception: it
  # is minified by its own terser invocation — see CLAUDE.md gotcha 5.)
  clock.js
  page-context.js
  certificates-i18n.js
  certificate-lightbox.js
  blog-post-enhance.js
  blog-search.js
  pwa-register.js
)

# Custom CSS files — these get a .min.css sibling.
CUSTOM_CSS=(
  styles.css
  blog.css
  portfolio.css
  videos.css
  404-games.css
  psn.css
  certificates.css
  shiny-button.css
  glowing-effect.css
  limelight-nav.css
  pixel-canvas.css
  flow-field-background.css
  kohan-avatar.css
  page-chrome.css
  chat-ui.css
  wisdom-quotes.css
  glare-card.css
  hud-button.css
  pearl-button.css
)

js_before=0; js_after=0
css_before=0; css_after=0

echo "== minifying JS =="
for src in "${CUSTOM_JS[@]}"; do
  if [ ! -f "$JS_DIR/$src" ]; then
    echo "  skip $src (not found)"
    continue
  fi
  out="$JS_DIR/${src%.js}.min.js"
  before=$(wc -c < "$JS_DIR/$src" | tr -d ' ')
  terser "$JS_DIR/$src" \
    --compress "drop_console=false,passes=2" \
    --mangle "reserved=['\$','jQuery']" \
    --output "$out" 2>/dev/null
  after=$(wc -c < "$out" | tr -d ' ')
  js_before=$((js_before + before))
  js_after=$((js_after + after))
  printf "  %-40s %6d → %6d  (%2d%% smaller)\n" "$src" "$before" "$after" $((100 - (after * 100 / before)))
done

echo ""
echo "== minifying CSS =="
for src in "${CUSTOM_CSS[@]}"; do
  if [ ! -f "$CSS_DIR/$src" ]; then
    echo "  skip $src (not found)"
    continue
  fi
  out="$CSS_DIR/${src%.css}.min.css"
  before=$(wc -c < "$CSS_DIR/$src" | tr -d ' ')
  cleancss -o "$out" --format false "$CSS_DIR/$src" 2>/dev/null
  after=$(wc -c < "$out" | tr -d ' ')
  css_before=$((css_before + before))
  css_after=$((css_after + after))
  printf "  %-40s %6d → %6d  (%2d%% smaller)\n" "$src" "$before" "$after" $((100 - (after * 100 / before)))
done

echo ""
echo "── totals ────────────────────────────────"
printf "  JS:  %6d → %6d  (%2d%% smaller)\n" "$js_before" "$js_after" $((100 - (js_after * 100 / js_before)))
printf "  CSS: %6d → %6d  (%2d%% smaller)\n" "$css_before" "$css_after" $((100 - (css_after * 100 / css_before)))
printf "  total saved: %d KB (raw), ~3x more after gzip\n" $(((js_before + css_before - js_after - css_after) / 1024))
