# AI Book Design Freeze

`DESIGN_STATUS = FROZEN`

Frozen on 2026-09-19 after repository discovery, canonical-book audit, design-system extraction, responsive prototype, accessibility-tree inspection, and browser QA.

## Frozen decisions

- Extend the existing Kohandezh dark/lime long-form system; no site redesign.
- Persian-first, RTL, local fonts, logical CSS properties.
- Preserve `/fa/ai-book/*` routes, slugs, stable anchors, content IDs, citations, provenance and origin labels.
- Three-column desktop, two-column tablet, single-column mobile reader.
- Canonical book data remains read-only and crosses a validated PHP ingestion boundary.
- Use isolated Layer-B WordPress ownership; no TypeScript/framework migration.
- Search/RAG/KG/PDF are integrations after the reader/data boundary, not prerequisites for this freeze.

## Browser evidence

Reference prototype: `docs/ai-book/prototype/index.html`.

- Chromium desktop 1280×720: sticky TOC and visible in-page rail; no horizontal overflow.
- Tablet 768×1024: sticky TOC, rail hidden; no horizontal overflow.
- Mobile 375×812: single-column RTL layout, horizontal chapter selector; `scrollWidth == clientWidth == 375`.
- Accessibility snapshot exposed skip link, navigation landmarks, one H1, hierarchical H2s, labeled complementary regions, labeled table region and disabled future action.
- Browser console: no errors.

## Change control

Implementation must match this freeze. Fix accessibility/usability defects directly. Any material change to layout, route family, origin semantics, or visual tokens requires an ADR in `DECISIONS.md`, updated screenshots, and a new freeze revision. Visual bug fixes do not reopen design discovery.
