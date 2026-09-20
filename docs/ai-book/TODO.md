# AI Book TODO

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
- [x] Add cited Ask/RAG (`/ai-book/ask/`): local bounded retrieval over the 2,420-chunk corpus with repository-resolvable citations, answer provider strictly configuration-gated under a `used ⊆ offered` contract, honest PROVIDER_REQUIRED/unavailable states (`ai-book-ask.test.php`, ADR-AB-0013).
- [x] Add PDF viewer (`/ai-book/pdf/`, manifest-verified local stream, no third-party embedding, ADR-AB-0014) and storage-free configuration-gated request intake (`/ai-book/request-pdf/`, ADR-AB-0015) (`ai-book-pdf.test.php`).
- [x] Security review gate over the whole `/ai-book/*` surface — PASS with SEC-1 (cache-control token) and SEC-2 (CSRF nonce) fixed (`docs/ai-book/SECURITY_REVIEW.md`).
- [x] Technical SEO review gate — PASS with SEO-1 fixed (Layer B isolation on the shared `kbk_entity` query var; `docs/ai-book/SEO_REVIEW.md`).
- [x] Performance review gate — PASS with PERF-1 fixed (sha256 ETag + conditional 304 on the 38MB stream; measured per-view cost map in `docs/ai-book/PERFORMANCE_REVIEW.md`).
- [x] Final browser/accessibility QA — PASS: 39/39 page-load checks (13 routes × 3 viewports, zero console errors / no overflow / RTL / skip link), keyboard skip-link→main verified, QA-1 fixed (graph canvas `role="img"` in the shell builder); report + 12 screenshots in `docs/ai-book/FINAL_QA.md` and `docs/ai-book/screenshots/`.
- [ ] **NEXT:** Production Build per sequencing.

## Deferred gates

- [ ] Publisher signing and production deployment (human-gated, out of scope here).
