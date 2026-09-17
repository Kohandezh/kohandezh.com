# Local image optimization and WordPress browser QA

## Scope and implementation

Stage 3: 36 WebP derivatives for 18 originals (portrait, portrait thumbnail, KSF logo, six award images, nine Microsoft badges). Original images remain unchanged. Responsive candidates and matching portrait preload were added to source pages; badge runtime supplies srcset. The WordPress generator rewrites every srcset candidate to the theme URL. The atlas was not recompressed: preserving sprite quality and frame geometry was preferred after removing its duplicate request.

Rebuild derivatives: `python3 _tooling/optimize-display-images.py`. Rebuild minified JS and sync the theme after source edits.

Original total: 631196 bytes. Small candidates: 126350 bytes (80% reduction). Larger candidates: 292082 bytes (54% reduction). These are file totals, not a guarantee of total page transfer savings: browser density and viewport select candidates.

Portrait: 181000 bytes original; 480px candidate 34254 bytes; 800px candidate 66350 bytes. Full 1086px portrait remains an available high-density candidate. Original certificate archive/lightbox targets were not replaced.

## Real local WordPress test

Docker WordPress at http://localhost:8888, kohandezhcv theme, Kohan Avatar and AI Hub plugins active. Database is local test data (including Hello world), not a clone of production content. No production requests were used for measurement or deployment.

Browser viewport checks: 1440×1000 desktop, 390×844 mobile. English dark, Persian dark/light, Arabic light inspected. Persian and Arabic mobile document width and scrollWidth both 390. No broken nonempty loaded image URLs on inspected pages; Arabic console error list empty.

Observed network entries on English page:
- One spritesheet URL: `/wp-content/plugins/kohan-avatar/assets/kohan/spritesheet.webp?v=b99d4a054cc8`, encoded size 794502 bytes.
- No legacy theme ai-pet or primary kohan-avatar JS/CSS download in measured resources. The distinct theme enhancement script remains intentionally.
- Portrait selected w480; encoded size 34254 bytes, transferSize 34554 on the measured load.
- One Pet DOM instance.

These were ordinary reloads with browser cache enabled. Some transfer sizes were zero due to cache; this is not a cold-cache performance benchmark or Lighthouse run. A temporary local-only MU plugin copied Performance Resource Timing entries into a hidden DOM report for inspection; it was removed after measurement and is not in release artifacts.

Interactions: mobile chat opened visibly; no message was sent to an AI provider. Persian Let's talk scrolled to contact (scrollTop 13376.5, contact top 75.77px). Arabic Pet drag moved it from bottom-right to x189/y595. Full ten-language visual QA, exhaustive scroll regression and model answer quality were not covered by this focused pass.

Docker startup automatically restarted previously configured Live Avatar containers. All six running Live Avatar services were stopped immediately after detection. No Live Avatar setting was enabled or code changed. Local WordPress remains available.

## Verification and caveats

Full npm test passed after the image build. Additional responsive/preload tests verify all ten language pages and theme URL conversion. Theme/root release artifacts rebuilt; originals and prior edits preserved. No commit, push or production deployment.

Remaining later-stage observations: local database content differs from production; Arabic still contains some English labels; light-mode navigation contrast deserves the scheduled accessibility pass. Do not describe this focused test as a complete whole-site visual sign-off.
