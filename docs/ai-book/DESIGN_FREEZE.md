# AI Book Design Freeze

`DESIGN_STATUS = FROZEN`

Freeze revision 2 completed on 2026-09-19 after the full Master Prompt required all major route shells. Revision 1's single reader prototype was reopened for completeness and superseded by the 13-route implementation under `ai-book/`.

## Frozen decisions

- Extend the existing Kohandezh dark/lime long-form system; no site redesign.
- Persian-first, RTL, local fonts, logical CSS properties.
- `/ai-book/*` owns the Hub/application shell; `/fa/ai-book/*` remains canonical for existing Persian content and citation anchors.
- Three-column desktop, two-column tablet, single-column mobile reader.
- Canonical book data remains read-only and crosses a validated PHP ingestion boundary.
- Use isolated Layer-B WordPress ownership; no TypeScript/framework migration.
- Search, Ask/RAG, KG, PDF and request pages have final-looking shells with honest disabled/config-required states. Their backends follow the reader/data boundary.

## Frozen route and component map

Thirteen route shells are implemented: landing, reader, search, ask, concepts, graph, templates, sources, glossary, PDF, request PDF, citation and verification. Shared components include `AiBookShell`, header/navigation/footer, page hero, cards, metadata, TOC, content layers, tables, search/results, cited-answer cards, graph canvas/detail/list fallback, PDF shell, request form/process, citation output and verification card. Static component names are represented by scoped `.ab-*` CSS and reusable generator functions in `_tooling/ai-book/build_visual_shell.py`.

Desktop reader uses TOC + 52–70ch content + related knowledge. Tablet hides the related rail. Mobile is content-first with a horizontal TOC; application navigation becomes an accessible disclosure. Source translation uses blue, editorial synthesis lime and Iranian localization warm amber, each with a text label so meaning does not depend on color.

## Browser evidence

Production-shaped visual shell: `ai-book/`. Legacy revision-1 prototype remains only as design history at `docs/ai-book/prototype/`.

- Seven mandatory routes were tested at 375×812, 768×1024 and 1280×720: landing, reader, search, ask, graph, PDF and request PDF.
- All 21 route/viewport combinations reported `lang=fa`, `dir=rtl`, a present H1, loaded styles/main, no horizontal overflow and no console errors.
- Reference screenshots: `docs/ai-book/screenshots/home-desktop.png`, `read-desktop.png`, `graph-desktop.png`, `request-pdf-mobile.png`.
- All 13 generated HTML documents parse successfully. Generator Python compiles and shared JavaScript passes `node --check`.

## Change control

Implementation must match this freeze. Fix accessibility/usability defects directly. Any material change to layout, route family, origin semantics, or visual tokens requires an ADR in `DECISIONS.md`, updated screenshots, and a new freeze revision. Visual bug fixes do not reopen design discovery.
