# AI Book Design Freeze

`DESIGN_STATUS = FROZEN_REVISION_6`

Freeze revision 6 completed on 2026-09-24 after owner review requested a stronger book-reading feeling and Light Mode. The Reader is now a full-bleed surface with no title/content card shell; the 72ch measure remains intentionally constrained for legibility. Related knowledge uses simple section rules rather than a card. A persistent, system-aware Light/Dark control shares Kohandezh's existing `darkMode` preference. Canonical text, IDs and provenance remain unchanged. Revision 5 established human-readable Governance subjects.

## Frozen decisions

- Extend the existing Kohandezh dark/lime long-form system; no site redesign.
- Persian-first, RTL, local fonts, logical CSS properties.
- `/ai-book/*` owns the Hub/application shell; `/fa/ai-book/*` remains canonical for existing Persian content and citation anchors.
- Two-column desktop reader (sticky chapter TOC + 72ch article); content-first tablet/mobile reader with a collapsible chapter index.
- Canonical book data remains read-only and crosses a validated PHP ingestion boundary.
- Use isolated Layer-B WordPress ownership; no TypeScript/framework migration.
- Search, Ask/RAG, KG, PDF and request pages have final-looking shells with honest disabled/config-required states. Their backends follow the reader/data boundary.
- Navigation hierarchy is fixed: compact site utility bar → sticky AI Book product navigation → page-local navigation/TOC. On mobile the site utility bar keeps only owner, Blog and Knowledge links; the complete book menu is an accessible disclosure.

## Frozen route and component map

Thirteen route shells are implemented: landing, reader, search, ask, concepts, graph, templates, sources, glossary, PDF, request PDF, citation and verification. Shared components include `AiBookShell`, header/navigation/footer, page hero, cards, metadata, TOC, content layers, tables, search/results, cited-answer cards, graph canvas/detail/list fallback, PDF shell, request form/process, citation output and verification card. Static component names are represented by scoped `.ab-*` CSS and reusable generator functions in `_tooling/ai-book/build_visual_shell.py`.

Desktop reader uses a sticky chapter TOC + 72ch article with related knowledge inline. Tablet and mobile are content-first with a native `<details>` chapter index; application navigation remains a separate accessible disclosure. Canonical Markdown is escaped first and converted only into the allowlisted semantic reading subset. Source translation uses blue, editorial synthesis lime and Iranian localization warm amber, each with a text label so meaning does not depend on color.

## Browser evidence

Production-shaped visual shell: `ai-book/`. Legacy revision-1 prototype remains only as design history at `docs/ai-book/prototype/`.

- Seven mandatory routes were tested at 375×812, 768×1024 and 1280×720: landing, reader, search, ask, graph, PDF and request PDF.
- All 21 route/viewport combinations reported `lang=fa`, `dir=rtl`, a present H1, loaded styles/main, no horizontal overflow and no console errors.
- Reference screenshots: `docs/ai-book/screenshots/home-desktop.png`, `read-desktop.png`, `graph-desktop.png`, `request-pdf-mobile.png`.
- All 13 generated HTML documents parse successfully. Generator Python compiles and shared JavaScript passes `node --check`.
- Revision 3 live WordPress evidence: desktop/tablet/mobile screenshots at 1280×720, 768×1024 and 375×812; zero console errors; the tablet disclosure opens 14px below the real header edge, contains nine links, and closes with Escape.
- Revision 4 live WordPress evidence: Reader checked at 1440×1000, default tablet 886px and 390×844; no horizontal overflow or console warnings/errors, desktop article width 760px, compact chapter index exposes all 20 links, and raw Markdown heading markers are absent.
- Revision 5 live WordPress evidence: all 20 chapter labels render through the curated presentation-title boundary; the 19 numbered sections expose a subject after their stable `AI Governance n.n` code, repeated GOVERN headings are absent, and the S02 deep link emits the descriptive document title and current-location label without overflow.
- Revision 6 live WordPress evidence: at 1440px the main reading surface spans the viewport while the article remains 760px and the Reader region 1120px; at 390px the theme control is a 44×44 target and no horizontal overflow occurs. Light Mode uses warm paper `#fbf9f3` with dark text, persists after reload, and stays synchronized with shared site chrome without conflicting body classes or console warnings/errors.

## Change control

Revision 8 supersedes revision 7 as local owner-review candidate: distraction-free Reader and accessibility-oriented personal reading controls, implemented using `uiux-pro-max` guidance. Not a new approved freeze; book data stays frozen. See latest handover for verification.

2026-09-25 owner-requested revision 7 is a LOCAL CANDIDATE, not a newly approved freeze. GitHub Docs-inspired Reader spacing and active green rail replace revision-6 Reader presentation. See ADR-AB-0021 and STATE.json. Live checks: 1440px dark/light, 390px compact-index interaction, 768px overflow check; no overflow or captured console errors. Screenshot views were inspected in-session; new saved screenshot artifacts have not been produced. Owner visual approval is still pending.

Implementation must match this freeze. Fix accessibility/usability defects directly. Any material change to layout, route family, origin semantics, or visual tokens requires an ADR in `DECISIONS.md`, updated screenshots, and a new freeze revision. Visual bug fixes do not reopen design discovery.
# Latest override — 2026-09-26

REVISION_9_OWNER_REVIEW: owner-requested covers, logo reuse, related-content layout and LTR/reference navigation fixes. Not a new freeze approval. Canonical book text unchanged. Earlier freeze declarations below are historical.
