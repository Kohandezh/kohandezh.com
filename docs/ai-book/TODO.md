# AI Book TODO

## Audit remediation checkpoint — 2026-09-29

- [x] Restore existing local Docker preview without resetting data.
- [x] Add search pagination preserving every result, including glossary promotion and ID-prefix searches.
- [x] Verify all-page uniqueness/count and clamping; run full AI Book/release tests.
- [ ] Reconnect browser and visually verify pagination on desktop/tablet/mobile.
- [ ] Implement real WordPress cite/verify routes (static shells do not suffice).
- [ ] Reuse meaningful Reader titles in Search; clean Markdown snippets and Ask passage labels/duplicates.
- [ ] Resolve figure placeholders from actual canonical figure assets, not invented illustrations.
- [ ] Correct glossary missing-definition handling and contradictory approval labels.
- [ ] Review small-screen Reader toolbar, whole-book navigation and semantic heading hierarchy.
- [ ] Finish Persian/Tehran display-date consistency without inventing date precision.
- [ ] Review PDF viewing/performance, SEO metadata and accessibility with fresh evidence.
- [ ] Obtain owner approval before writing a new Opus prompt or considering release.

## Latest owner review — revision 9

- [x] Add generated front/back presentation covers with Mohammad Kohandezh.
- [x] Reuse existing 512px logo in book header and shared generated footer.
- [x] Generate and round-trip decode public-book QR.
- [x] Fix current Reader links, allowlisted legacy redirects, related-reading layout and English reference direction.
- [x] Add a real local editorial blog article, linked from the book.
- [ ] Owner approves presentation revision 9.
- [ ] At separately authorized release, migrate the editorial post and verify public routes/QR destination. Currently public book URL is 404.

## Design-first

- [x] Discover repository and canonical book roots.
- [x] Bootstrap Phase-A Skills/manual policies.
- [x] Extract design system and RTL conventions.
- [x] Produce responsive representative prototype.
- [x] Implement all 13 required visual route shells.
- [x] Implement reusable scoped component system and generator.
- [x] Use representative real Persian data, IDs, sources and Merkle metadata.
- [x] Run 21 Chromium route/viewport responsive/RTL/console/overflow checks.
- [x] Freeze design revision 2 and handover state.

## Functional integration

- [x] Implement and verify a read-only PHP artifact-schema boundary for book, content IDs and citations.
- [x] Implement and verify the read-only canonical repository, summaries and ID/citation lookups.
- [x] Add fail-closed, feature-flagged WordPress landing/read routes and frozen template port.
- [x] Add allowlisted dynamic part/chapter/section routing and route tests (`ai-book-reader-routing.test.php`).
- [x] Resolve `/knowledge/` ownership and P06 route collision (ADR-AB-0009).
- [x] Complete part/chapter selection and stable deep-link behavior in the WordPress reader (breadcrumb, prev/next chapter nav, section anchor/query-var deep link).
- [x] Add Search (`/ai-book/search/`) over titles/body/English terms/acronyms/glossary/source IDs/content IDs with highlighted bounded snippets, stable deep links and the validator boundary (`ai-book-search.test.php`, ADR-AB-0010).
- [x] Add full `/ai-book/glossary/` term index + per-term deep-link view (safe fields only, no reviewer secrets).
- [x] Add Sources (18 audited docs, bibliography merge, 660 section counts), Templates (20 validated cards + details) and Concepts (3,671 entities, type filter, pagination, bounded mention resolution) via `KBK_AI_Book_Catalog` (`ai-book-catalog.test.php`, ADR-AB-0011).
- [x] Add bounded accessible KG explorer (`/ai-book/graph/` over `knowledge/graph.json`, depth-1, capped) and wire Related Concepts/Related Sections into the Reader (`ai-book-graph.test.php`, ADR-AB-0012).
- [x] Add cited Ask/RAG (`/ai-book/ask/`): local bounded retrieval over the current 2,427-chunk corpus with repository-resolvable citations, answer provider strictly configuration-gated under a `used ⊆ offered` contract, honest PROVIDER_REQUIRED/unavailable states (`ai-book-ask.test.php`, ADR-AB-0013).
- [x] Add PDF viewer (`/ai-book/pdf/`, manifest-verified local stream, no third-party embedding, ADR-AB-0014) and storage-free configuration-gated request intake (`/ai-book/request-pdf/`, ADR-AB-0015) (`ai-book-pdf.test.php`).
- [x] Security review gate over the whole `/ai-book/*` surface — PASS with SEC-1 (cache-control token) and SEC-2 (CSRF nonce) fixed (`docs/ai-book/SECURITY_REVIEW.md`).
- [x] Technical SEO review gate — PASS with SEO-1 fixed (Layer B isolation on the shared `kbk_entity` query var; `docs/ai-book/SEO_REVIEW.md`).
- [x] Performance review gate — PASS with PERF-1 fixed (sha256 ETag + conditional 304 on the 38MB stream; measured per-view cost map in `docs/ai-book/PERFORMANCE_REVIEW.md`).
- [x] Final browser/accessibility QA — PASS: 39/39 page-load checks (13 routes × 3 viewports, zero console errors / no overflow / RTL / skip link), keyboard skip-link→main verified, QA-1 fixed (graph canvas `role="img"` in the shell builder); report + 12 screenshots in `docs/ai-book/FINAL_QA.md` and `docs/ai-book/screenshots/`.
- [x] Production Build — VERIFIED: theme asset copies byte-identical to sources, loader order intact (artifacts → repository → search → catalog → graph → ask → pdf → request → ai-book), `php -l` clean on all touched PHP, both suites green, shell regeneration deterministic (no diff after rebuild), JS/PHP hygiene clean (no debug artifacts, no whitespace errors).
- [x] Publish the Persian discovery surface under the locale-first `/fa/books/ai-governance/*` contract; retain compatibility aliases, add one Persian `hreflang`, update canonical/OpenGraph/JSON-LD/sitemap links, and verify the live local WordPress routes.
- [x] Replace the short NIST footer notice with the owner-approved Persian authorship/education notice in the WordPress template and deterministic visual-shell generator.
- [x] Integrate the frozen AI Book with the Kohandezh theme chrome: shared site navigation/footer on book routes, plus a Persian-only AI Governance entry in the `/fa/` desktop/mobile navigation and footer; verify desktop/tablet/mobile locally on port 8890.
- [x] Owner-directed navigation usability revision 3: move the site utility menu above the book header, remove redundant/legal header links, simplify mobile site chrome, expand the book disclosure to nine destinations, and verify keyboard/open/close positioning across all target viewports.
- [x] Owner-directed Reader usability revision 4: remove the empty desktop rail, constrain long-form measure, add a collapsible chapter index for tablet/mobile, compact the chapter hero, and safely render canonical Markdown as semantic reading HTML.
- [x] Owner-directed title clarity revision 5: retain stable `AI Governance n.n` identifiers, add concise Persian subjects to all 19 numbered Governance sections, and suppress duplicate code headings only in the reading presentation.
- [x] Owner-directed reading-surface revision 6: make Reader full-bleed without widening the readable measure, remove nonessential card frames, and add a persistent system-aware Light/Dark control shared with Kohandezh site chrome.
- [ ] **NEXT (human-gated):** Publisher signing with the real publisher key (current edition is development-signed), then production deployment.

## Deferred gates

- [x] Revision 8: distraction-free reading workspace, text-size and spacing controls, width toggle, paper theme, persistent preferences and collapsible chapter index.
- [ ] Owner visual approval of revision 8 (supersedes revision-7 review).

- [x] Implement owner-requested GitHub Docs-style Reader candidate: unboxed content, tighter typographic rhythm, green active rail, scroll-position tracking; verify local desktop/tablet/mobile and both themes.
- [ ] **NEXT UI:** Owner review of revision 7 before treating it as a new design freeze. Save updated reference screenshots when freezing.

- [ ] Personalized PDF issuance (deferred by scope decision).
- [ ] Publisher signing and production deployment (human-gated, requires real credentials — do not invent).
