# AI Book Technical SEO Review Gate

- **Date:** 2026-09-20
- **Scope:** every `/ai-book/*` view, the raw PDF stream, the shared query-var surface with Layer B, structured data, sitemaps, titles/canonicals, locale signals.
- **Verdict:** **PASS** with one finding fixed in-gate (`SEO-1`) and platform-level notes.
- **Method:** manual audit of the template head, robots hooks, schema isolation, sitemap surfaces and query-var routing, verified by the full test chain (`npm run test:ai-book` 10 suites + `npm test` 18 suites, all PASS after the fix).

## Checklist and findings

| Area | Check | Result |
| --- | --- | --- |
| Indexing posture | All ai-book views are `noindex, nofollow` by default (`wp_robots` filter, tested; `KBK_AI_BOOK_INDEXABLE` opt-in per ADR-AB-0008). The raw PDF stream carries `X-Robots-Tag: noindex, nofollow` so the file stays unindexed even when hot-linked. | PASS |
| Layer B isolation | **SEO-1 (fixed):** AI Book graph/concept views reused the shared `kbk_entity` query var, so `KBK_Routes::is_entity_request()` classified them as Layer B — on such URLs Layer B JSON-LD (BreadcrumbList with an ai-book `@id`) would print and the Layer B template filter would force a 404 path. Fixed at the single choke point: `is_entity_request()` now returns false whenever an ai-book view owns the request; regression-tested both ways (ai-book graph URL never enters Layer B; `/entity/…` still does). | FIXED |
| Structured data | The ai-book template emits no JSON-LD (honest for a non-indexable surface); with SEO-1 fixed, no Layer B graph can leak onto it either. Existing Layer B pages unchanged (site suite green). | PASS |
| Canonical URLs | No `rel=canonical` on ai-book pages — correct while non-indexable (no competing signals against the canonical `/fa/ai-book/` namespace). Reader sections expose their canonical content URLs as visible links; citation URLs stay in the `/fa/ai-book/` namespace (validator-enforced). The PDF stream URL is a private, noindexed namespace, never presented as canonical. | PASS |
| Titles | One hardcoded, escaped `<title>` per view; the theme registers no `title-tag` support (matching the frozen Layer B template convention) → no duplicate title tags. | PASS |
| Locale signals | Single honest locale: `<html lang="fa" dir="rtl">`; no `hreflang`, no fake locale or translation pages anywhere in the feature. | PASS |
| Sitemaps | The knowledge theme ships no sitemap of its own; the CV/publication sitemap belongs to a different site/theme and queries only its own published posts — ai-book content is not stored as WP posts at all, so it is structurally absent from every sitemap. No crawlable duplicate of book content exists on the site. | PASS |
| Duplicates/thin pages | Every view is backed by validated canonical artifacts; malformed input degrades to honest empty/not-found states instead of generating pages. Query-var allowlists make arbitrary URL crawling non-canonical (unknown values → honest empty state, still noindex). | PASS |
| Internal linking | Hub → views → sections/citations/entities/PDF/request all resolve through repository/validator-approved URLs; no dead ends from validated data; bounded mentions/graph edges are repository-resolved (tested). | PASS |
| Non-endorsement | The NIST non-endorsement disclaimer renders in the footer of every ai-book page (frozen shell), preserving the provenance/honesty contract required by the master prompt. | PASS |
| robots.txt / meta-crawlers | Platform-level concerns (site robots.txt, llms.txt) live outside this feature; the feature guarantees per-response noindex regardless. | NOTE |
| Redirects/cloaking | No redirects, no user-agent sniffing, no cloaking — crawlers and readers receive identical honest states. | PASS |

## Evidence

- `php _tooling/tests/ai-book-wordpress-route.test.php` → PASS (12 rewrites + the new Layer B isolation regression checks)
- `npm run test:ai-book` → PASS (10 suites + visual shell build)
- `npm test` → PASS (18 suites, including the Layer B regression suite — the SEO-1 guard changes no Layer B behavior)
- Grep audits: no `hreflang`, no `rel=canonical` in the ai-book template, no `title-tag` support, no sitemap surface in the knowledge theme.

## Residual risks (accepted, documented)

1. **robots.txt / llms.txt** — platform files are outside the feature; if AI-book URLs must be disallowed pre-launch, add them there. The per-response noindex already keeps them out of indexes either way.
2. **Future indexability flip** — if `KBK_AI_BOOK_INDEXABLE=true` is ever set for production, a rel=canonical strategy, sitemap inclusion and an SEO content review become mandatory prerequisites; this gate explicitly does NOT approve that flip.
