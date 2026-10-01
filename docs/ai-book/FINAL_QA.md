# Final Browser / Accessibility QA — `/ai-book/*`

**Date:** 2026-09-20 · **Result: PASS**

## Scope and method

QA target is the 13 regenerated static shells (`ai-book/*.html`, built by `_tooling/ai-book/build_visual_shell.py`). A local WordPress install does not exist (gotcha 1), so live views cannot be browser-tested; the shells are the established QA target from the Design Freeze round. The live-template parity for the one finding (QA-1) was verified in source.

Driver: gstack `browse` headless Chromium daemon. Matrix: **13 routes × 3 viewports (375×812, 768×1024, 1280×720) = 39 page-load checks**, including all 7 mandatory routes frozen at Design Freeze (landing, reader, search, ask, graph, PDF, request-PDF) plus concepts, sources, templates, glossary, cite, verify.

## Per-page-load checks (39/39 PASS)

Every route at every viewport: zero console errors, no horizontal overflow (`scrollWidth ≤ innerWidth + 1`), `html[dir=rtl]`, exactly one skip link (`.ab-skip`).

## Keyboard and landmarks

- Landing: first Tab focuses the skip link; pressing Enter moves focus to `<main id>` (verified via `document.activeElement`); subsequent tabs walk links in order.
- Reader census: 25 links, 1 button, 0 stray inputs, exactly 1 `h1`, `<main>` landmark present, `lang="fa"`.

## View-specific DOM assertions

| View | Assertions (all pass) |
| --- | --- |
| search | form present, `input[name=q]`, 3 result cards |
| ask | form present, answer block, 2 citation cards |
| request-pdf | form + consent checkbox + 5-step process list |
| graph | canvas present with accessible fallback detail |
| pdf | toolbar + stage present, 5 controls honestly `disabled` (PDF.js degraded state) |

## Findings

- **QA-1 (fixed):** the static shell's graph canvas omitted `role="img"`; the live template (`templates/ai-book.php:206`) already had `role="img"` + `aria-label`. Fixed in `build_visual_shell.py:133`, shells regenerated, graph re-verified at all 3 viewports (role=img, console clean, no overflow).
- **Note (not a defect):** one transient `file://` navigation race was observed during interactive testing (browser kept the previous page); re-navigation succeeded and the final sweep used fresh navigations with per-page assertions.

## Evidence

- 12 screenshots captured to `docs/ai-book/screenshots/` (desktop 1280×720 for all 7 mandatory routes; mobile 375×812 for landing/reader/ask/graph/request-pdf). Screenshots are preserved for human review; the pass/fail criteria of this report are the DOM-level assertions above (this session had no image-input capability to inspect the PNGs).
- Interactive transcripts are quoted in the session log; every number in this report was read from the live DOM via the browse daemon.

## Limitations

- At the time of this report the live WordPress views were unavailable. A later 2026-09-21 local stack verified the locale-prefixed landing/Reader/Search/Ask/PDF/Request routes; see `HANDOVER_CURRENT.md` for the current state.
- PDF stream, provider-gated flows (ask generation, request delivery) require configuration and are covered by unit tests, not browser QA.
