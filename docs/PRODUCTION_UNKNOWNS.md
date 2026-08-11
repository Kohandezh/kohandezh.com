# Production Unknowns — Kohandezh.com

> The local WordPress database was removed during the Phase 0 cleanup, so production-specific facts are **not directly verifiable locally**. Each unknown is labeled, paired with a safe default, and a verification step. Per Agent.md addendum §6.4: do not invent answers; label, abstract, continue locally, and verify before production.

## Unknowns & safe defaults

| # | Unknown | Safe default (local) | Production verification |
|---|---|---|---|
| U1 | Active plugins on production | Assume only `kohan-avatar` + `kohandezh-ai-hub` + `kohandezhcv` theme | `wp plugin list` on prod; compare CPT/taxonomy registrations |
| U2 | Active SEO plugin (Yoast/RankMath/none) | Assume none; static `sitemap.xml` is canonical | Check prod admin; if present, decide sitemap ownership (plugin vs static) |
| U3 | Active multilingual plugin (Polylang/WPML/manual) | Assume manual (static file-per-locale: `fa.html`) | Check prod admin; confirm hreflang source of truth |
| U4 | Production permalink structure | Assume pretty slugs (`/%postname%/`) consistent with theme page slugs | `get_option('permalink_structure')` |
| U5 | Production CPTs & taxonomies already registered | Assume none beyond WP defaults (theme registers none) | `wp cpt list` / `wp taxonomy list` |
| U6 | DB-managed content (posts/pages) | Static blog posts are the canonical reference set | Export prod posts; diff against `blog/*.html` |
| U7 | Production sitemap provider | Assume the static `sitemap.xml` (served from root) | Curl `https://kohandezh.com/sitemap.xml`; check if plugin-generated |
| U8 | Production caching stack / CDN | Assume none/LiteSpeed default | Check prod (LiteSpeed Cache? Cloudflare?) |
| U9 | Production analytics | Assume none embedded by theme | Inspect prod page source for GA/GTM/etc. |
| U10 | Production `.htaccess` redirects | Assume the static `.htaccess` 301 map applies | Fetch prod `.htaccess`; verify the 5 redirect rules present |
| U11 | Production Media Library contents | Theme uses `assets/` (not Media Library) for CV; blog uses Media Library | Export/inspect for blog featured images |
| U12 | `kohandezh-ai-hub` plugin scope & activation | Treat as **adversarial duplicate risk** for Layer B | Read plugin source (local copy exists); confirm prod active state |
| U13 | ZAI_API_KEY configured in prod `wp-config.php` | Assume yes (per DEPLOY.md) | Check `wp-config.php` (above `stop editing`) |
| U14 | `kdcv_content_schema_version` on prod | Assume 1.3.0 (latest); migration is idempotent if lower | `wp option get kdcv_content_schema_version` |

## How Layer B stays safe despite unknowns

1. **Separate plugin.** All Layer B code in a new plugin (`kohandezh-knowledge`, Phase 5). Disabled = zero impact on prod.
2. **Conditional loading.** No global hooks; only act on Layer B routes/slugs.
3. **No permalink changes.** New routes use fresh slugs (`/enterprise-ai/`, etc.) confirmed absent (URL_INVENTORY.md).
4. **No DB writes on activation** beyond own CPT/taxonomy registration + version option.
5. **Feature-flaggable.** Each Layer B subsystem behind an option/constant for instant disable.

## Production verification checklist (run before any deploy)

- [ ] Backup prod DB + files confirmed
- [ ] Plugin list captured (U1)
- [ ] SEO plugin identified + sitemap ownership decided (U2/U7)
- [ ] Multilingual plugin identified (U3)
- [ ] Permalink structure recorded (U4)
- [ ] Existing CPT/taxonomy list recorded (U5)
- [ ] `kohandezh-ai-hub` scope confirmed non-conflicting (U12)
- [ ] Staging deploy + smoke test (if staging exists)
- [ ] `--dry-run` + parity test pass locally
- [ ] Rollback procedure tested (docs/ROLLBACK.md)
- [ ] No destructive DB migration; no permalink change; no homepage change
