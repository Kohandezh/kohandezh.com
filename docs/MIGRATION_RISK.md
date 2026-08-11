# Migration Risk Report — Kohandezh.com

> Risk assessment for preserving Layer A during the additive Layer B expansion. No migration is currently planned; this documents risk **if** changes happen. Aligned with Agent.md §1 non-destructive rules.

## Risk legend
**Low** = additive or fully reversible, no indexed-URL impact.
**Medium** = touches indexed URLs or requires care; reversible with effort.
**High** = could affect production rankings/data if mishandled; needs explicit gate.

## Top risks

| # | Risk | Area | Severity | Mitigation |
|---|---|---|---|---|
| R1 | Drift between static source and WP theme (the 2026-07-11 stale-library bug class) | Sync | **High** | Already mitigated by `sync-from-static.py` (Phase 0 verified operational). Run `--dry-run` before every deploy. |
| R2 | Layer B code loaded globally, degrading Layer A performance/Core Web Vitals | Perf | **High** | All Layer B in a separate plugin; conditional loading only on Layer B routes (Phase 5.2 / 11). |
| R3 | `kohandezh-ai-hub` plugin pre-exists — new CPTs/taxonomies could collide | Architecture | **High** | Inspect plugin fully before Phase 5; record decision in `DECISIONS.md`. |
| R4 | Stale sitemap (`expand-north-star-2025`, `gitex-2025` missing) | SEO | Medium | Fix in `sitemap.xml`; low-risk, reversible. (Audit finding F1) |
| R5 | `single.php` emits no Article JSON-LD on WP posts | SEO | Medium | Add `BlogPosting`/`Article` JSON-LD in Phase 10 (additive). |
| R6 | Static `.html` URLs vs WP pretty slugs — canonical divergence | SEO | Low | Handled by sync URL rewrite + `.htaccess` 301s. Verify canonicals after any permalink change. |
| R7 | Multilingual expansion creates thin/duplicate translations | SEO | Medium | Phased i18n (Phase 9); Persian+English first; never publish raw MT; hreflang discipline. |
| R8 | Local WP DB was deleted (Phase 0 cleanup) — prod state is inferred | Ops | Medium | All prod specifics tagged `[UNKNOWN]`; production verification checklist in PRODUCTION_UNKNOWNS.md. |
| R9 | Bulk pillar-page generation → thin content / fact fabrication | Content integrity | **High** | Phase 7 is a **roadmap only**; controlled seed batch in Phase 6; evidence policy enforced. |
| R10 | Unattended production deploy before rollback tested | Ops | **High** | Agent.md addendum §19 — production deployment is gated; stop at production boundary. |

## Per-component migration risk

- **CV pages (9 locales):** Low. Untouched by Layer B. Sync regenerates deterministically; dry-run proven no-op at parity.
- **Blog:** Medium. Static posts + WP DB posts coexist; old WP permalinks 301-redirected. Layer B must not alter `/blog/` routes.
- **`PSN/Certificates/videos`:** Low. No hreflang/JSON-LD today (gap, not a migration risk).
- **`assets/`:** Low. Exact-mirrored by sync; `WP_ONLY_ASSETS`/`STATIC_ONLY_ASSETS` excludes protect divergence.
- **Plugins (`kohan-avatar`, `kohandezh-ai-hub`):** Medium→High. Activation state on prod unknown; `kohandezh-ai-hub` scope unknown (R3).
- **`.htaccess`:** Medium. Contains the 301 redirect map + security headers. Any change must preserve redirects or SEO breaks.

## Recommendation
Proceed with **additive-only** Layer B in a separate plugin. No Layer A URL changes. Run `--dry-run` + parity test before every sync. Gate production deploy behind §19.
