# Phase 1 Audit — Kohandezh.com

> Status: Phase 1 audit. Repository-grounded. Production-specific items marked **[UNKNOWN — see PRODUCTION_UNKNOWNS.md]**. Generated 2026-07-25.
> Scope: static site (repo root) + WordPress theme (`_tooling/wp-theme/kohandezhcv/`) + local plugins. Non-destructive; no content changed.

## 1. Executive summary

The site is a mature, SEO-strong **personal-brand CV site** (Layer A): a single-page multilingual CV in 9 locales + a Persian blog + PSN/Certificates/videos/portfolio, with a playful 404 (4 random games). Production runs **WordPress** with the `KohandezhCV` theme (v1.3.0); the **static files at the repo root are the canonical source of truth** and `_tooling/wp-theme/sync-from-static.py` regenerates the theme's generated templates + mirrors `assets/`.

The theme is intentionally thin (no CPTs, no taxonomies, no REST routes of its own) — CV pages are self-contained HTML, the only dynamic parts are the native blog (`home.php`/`single.php`) and two **plugins**: `kohan-avatar` (the AI pet + `kdcv/v1/ask` REST route) and `kohandezh-ai-hub` (a pre-existing AI-hub plugin that must be inspected before Phase 5 to avoid duplicate CPTs).

Layer B (Knowledge Hub) will be **additive**: new routes, new CPT/taxonomy (only if no existing equivalent), new REST namespace, new schema — all conditionally loaded so Layer A is untouched.

## 2. Static site inventory (Layer A)

### 2.1 Pages (27 HTML)

| Group | Files |
|---|---|
| CV (9 locales) | `index.html` (en), `fa.html`, `ar.html`, `de.html`, `es.html`, `fr.html`, `tr.html`, `zh.html`, `ja.html` |
| Standalone | `PSN.html`, `Certificates.html`, `videos.html` |
| Blog | `blog/index.html` + 8 posts: `ai-career-transformation`, `digital-economy-15-percent-elecomp`, `expand-north-star-2025`, `farabi-innovation-festival`, `generative-ai-tools`, `gitex-2025`, `masire-21`, `neighborhood-management-award` |
| Portfolio | `portfolio/index.html` |
| 404 | `404.html` + 4 game pages (`404-breakout`, `404-invaders`, `404-packet`, `404-pulse`) |

### 2.2 SEO / metadata matrix (per page)

| Page group | canonical | hreflang | og | twitter | JSON-LD |
|---|---|---|---|---|---|
| 9 CV pages | 1 | 11 (en+8 lang+x-default) | 19 | 4 | 2 |
| `PSN.html`, `Certificates.html` | 1 | **0** | 5 | 4 | **0** |
| `videos.html` | 1 | 0 | 6 | **0** | **0** |
| Blog posts (sampled) | 1 | 0 (Persian-only) | — | — | 1 |
| `404.html` | 0 | 0 | 0 | 0 | 0 |

### 2.3 Structured data (CV pages)

JSON-LD `@type` present: `Person`, `Organization`, `PostalAddress`, `FAQPage` (5 × `Question` / 5 × `Answer`). Strong, valid entity markup already on every CV page.

### 2.4 Root files

- `.htaccess` — 301 redirects for old WP permalinks (`/2025/05/11/` → `/blog/generative-ai-tools.html`, etc.), security headers (CSP **Report-Only**), `ErrorDocument 404 /404.html`, browser caching, brotli/deflate.
- `robots.txt`, `sitemap.xml`, `llms.txt` (well-formed; declares personal portfolio + entity schema present).

### 2.5 Assets

`assets/` — 455 files across `css, js, fonts, images, icon, data, kohan, media, contact`.
- `assets/data/home-blog.json` (home blog feed + `pinned`), `assets/data/psn-kohandezh.json`.
- Build pipeline: `build.sh` minifies hand-written JS/CSS to `.min.{js,css}` siblings (terser + cleancss).

### 2.6 Internal link graph

- CV pages cross-link to all 8 other locales + `PSN.html` + `Certificates.html?lang=…`.
- Homepage `index.html` links to 3 blog posts (ai-career-transformation, farabi-innovation-festival, generative-ai-tools, neighborhood-management-award) via the "Blog and News" preview.
- `blog/index.html` lists all 8 posts; links back to `../index.html`.

## 3. WordPress theme audit (`kohandezhcv/`)

`style.css` header: **KohandezhCV 1.3.0**, WP ≥ 6.0, PHP ≥ 7.4, text domain `kohandezhcv`.

### 3.1 `functions.php` (480 lines) — what it does

- `define('KDCV', get_template_directory_uri())` — asset base used by every generated template.
- `KDCV_CONTENT_SCHEMA_VERSION = 1.3.0` — drives idempotent page-creation migrations.
- `kdcv_required_pages()` + `kdcv_ensure_required_pages()` — auto-creates the 13 pages (home, 8 langs, psn, certificates, blog, portfolio) on activation and on version bump; sets front page + posts page.
- Hardening: `DISALLOW_FILE_EDIT`; xmlrpc off; `wp_generator`/rsd/wlwmanifest removed; REST `/wp/v2/users` blocked for anon; `?author=` blocked; security headers via `send_headers`; `.htaccess` hardening (`kdcv_harden_htaccess`) blocks `readme.html`, `license.txt`, `wp-config-sample.php`, `wp-content/debug.log`.
- Post view counter (`kdcv_views` meta): admin column + dashboard widget (Persian labels).
- `kdcv_reading_minutes()` (word-count, Persian-friendly), `kdcv_card_image()` (featured → rotating fallbacks).
- Branded `wp-login`: dark palette + animated SVG avatar that tracks input / covers eyes on password focus.
- `kdcv_render_home_blog_feed()` — `WP_Query` latest 6 posts into the homepage "Blog and News" slot; remaining loaded by `home-blog-scroll.js` via REST.

### 3.2 What the theme does NOT do (important for Layer B)

- **No `register_post_type`, no `register_taxonomy`, no `register_rest_route`.** Confirmed by grep across all theme PHP.
- No `wp_enqueue_script`/`wp_enqueue_style` — generated CV templates carry their own `<link>`/`<script>` using the `KDCV` base (this is why assets are inlined in the templates, not enqueued).
- No header/footer partials — each generated template is a full HTML document.

### 3.3 Template files

| File | Type | Sync behavior |
|---|---|---|
| `front-page.php`, `page-{fa,ar,de,es,fr,tr,zh,ja,psn,certificates,portfolio}.php` | **Generated** | Regenerated by `sync-from-static.py` from static HTML — **do not hand-edit** |
| `home.php` (blog index, 129 ln), `single.php` (blog post, 73 ln), `index.php` (3 ln fallback) | **Hand-maintained** | Sync never touches (no static equivalent) |
| `style.css` (theme header), `screenshot.jpg` | Hand-maintained | Sync never touches |

### 3.4 `single.php` (blog post) observations

Dark editorial layout, `lang="fa" dir="rtl"`, uses `KDCV` asset base, `wp_head/wp_body_open/wp_footer` hooks, shows category + modified date + reading time + view count. **Gap:** emits no `Article`/`BlogPosting` JSON-LD (static blog posts carry their own 1 JSON-LD block, but the WP-rendered post adds none).

## 4. Plugins (local, under `_tooling/wp-theme/`)

| Plugin | Path | Role |
|---|---|---|
| **kohan-avatar** | `kohan-avatar/` (+ `kohan-avatar.zip`) | Owns the **`kdcv/v1/ask` REST route** (`includes/class-kohan-avatar-rest.php`) and the login avatar. This is the AI pet widget backend. **[UNKNOWN: is it active on production?]** |
| **kohandezh-ai-hub** | `kohandezh-ai-hub/` (+ zip) — has `admin/`, `includes/`, `kohandezh-ai-hub.php` | A **pre-existing AI-hub plugin**. **Must be fully inspected before Phase 5** to avoid creating duplicate/competing CPTs or REST routes. **[UNKNOWN: scope, activation state, whether it registers CPTs/taxonomies — to inspect in Phase 5 prep]** |

> Naming collision risk: the new Layer B "Enterprise AI Hub" (Phase 6) vs the existing `kohandezh-ai-hub` plugin. Resolve in `docs/DECISIONS.md` before implementing.

## 5. Static ⇄ WordPress mapping

| Static | WordPress |
|---|---|
| `index.html` | `front-page.php` (front page) |
| `{lang}.html` | `page-{lang}.php` (page slug `/{lang}/`) |
| `PSN.html` / `Certificates.html` | `page-psn.php` / `page-certificates.php` (`/psn/`, `/certificates/`) |
| `portfolio/index.html` | `page-portfolio.php` (`/portfolio/`) |
| `blog/*.html` (static, Persian) | **`home.php` + `single.php`** — posts live in the WP DB, not synced from static |
| `assets/` | mirrored into theme `assets/` by sync (exact, `--delete`) |

The sync transform rewrites: `data-kdcv-router-mode` static→wordpress, asset paths → `<?php echo KDCV; ?>/assets/`, page links → `home_url()` slugs, injects `wp_head/wp_body_open/wp_footer`, replaces the static blog preview list with the server-rendered `kdcv_render_home_blog_feed()`.

## 6. Findings & gaps (for later phases)

| # | Finding | Severity | Phase |
|---|---|---|---|
| F1 | `sitemap.xml` lists **6 blog posts but 8 exist** on disk (`expand-north-star-2025`, `gitex-2025` missing). | Med | fix anytime |
| F2 | `single.php` emits **no Article JSON-LD** on WP-rendered posts. | Med | Phase 10 |
| F3 | `PSN/Certificates/videos` have **no JSON-LD**. | Low | Phase 10 |
| F4 | Static lang URLs are `.html`; WP uses pretty slugs — divergence handled by sync rewrite + `.htaccess` 301s. | Info | — |
| F5 | `kohandezh-ai-hub` plugin pre-exists — **must inspect before Phase 5** to avoid duplicate CPTs. | High | Phase 5 prep |
| F6 | No existing `/enterprise-ai/`, `/quantum/`, `/knowledge/`, `/news/`, `/glossary/` paths on disk — **no IA conflicts** for Layer B. | Good | Phase 3 |
| F7 | Blog is Persian-only (no hreflang) — multilingual Layer B needs a deliberate i18n plan. | Info | Phase 9 |
| F8 | Theme loads no global assets via `wp_enqueue` — Layer B must keep this discipline (conditional loading). | Good | Phase 5/11 |

## 7. Recommendations carried forward

- Treat `kohandezh-ai-hub` plugin as a **first-class inspection target** before any new CPT/taxonomy work (Phase 5).
- Keep all Layer B code in a **separate plugin** (e.g. `kohandezh-knowledge`), conditionally loaded, never in the theme — preserves the sync boundary and Layer A isolation.
- Fix F1 (sitemap) opportunistically; it is low-risk and reversible.

---
*See also: URL_INVENTORY.md, MIGRATION_RISK.md, PRODUCTION_UNKNOWNS.md, STATIC_WP_PARITY.md.*
