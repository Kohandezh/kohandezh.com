# URL Inventory — Kohandezh.com

> Every known page with attributes for migration/sync planning. Static URLs are the canonical source (`index.html`, `{lang}.html`, …); WordPress equivalents are the production pretty-permalink slugs produced by `sync-from-static.py` + `kdcv_required_pages()`. Locale codes: en, fa, ar, de, es, fr, tr, zh (zh-Hans in hreflang), ja.

## Static URLs (Layer A canonical)

| URL | Lang | Type | Canonical | Hreflang | JSON-LD | Internal links out | Static file | WP template | Migration risk |
|---|---|---|---|---|---|---|---|---|---|
| `/` | en | CV front | yes | group G1 (11) | Person, Organization, FAQPage | 8 lang pages, PSN, Certificates, 4 blog posts | `index.html` | `front-page.php` | Low |
| `/fa.html` | fa | CV | yes | G1 | Person, Organization, FAQPage | langs, PSN, Certificates | `fa.html` | `page-fa.php` (slug `/fa/`) | Low |
| `/ar.html` | ar | CV | yes | G1 | same | same | `ar.html` | `page-ar.php` (`/ar/`) | Low |
| `/de.html` | de | CV | yes | G1 | same | same | `de.html` | `page-de.php` (`/de/`) | Low |
| `/es.html` | es | CV | yes | G1 | same | same | `es.html` | `page-es.php` (`/es/`) | Low |
| `/fr.html` | fr | CV | yes | G1 | same | same | `fr.html` | `page-fr.php` (`/fr/`) | Low |
| `/tr.html` | tr | CV | yes | G1 | same | same | `tr.html` | `page-tr.php` (`/tr/`) | Low |
| `/zh.html` | zh-Hans | CV | yes | G1 | same | same | `zh.html` | `page-zh.php` (`/zh/`) | Low |
| `/ja.html` | ja | CV | yes | G1 | same | same | `ja.html` | `page-ja.php` (`/ja/`) | Low |
| `/PSN.html` | en | standalone | yes | none | none | back to `/` | `PSN.html` | `page-psn.php` (`/psn/`) | Low |
| `/Certificates.html` | en | standalone | yes | none | none | langs via `?lang=` | `Certificates.html` | `page-certificates.php` (`/certificates/`) | Low |
| `/videos.html` | en | standalone | yes | none | none | — | `videos.html` | (no dedicated template — served via static) | Med |
| `/portfolio/` | en | portfolio | yes | none | none | — | `portfolio/index.html` | `page-portfolio.php` (`/portfolio/`) | Low |
| `/blog/` | fa | blog index | yes | none | none | 8 posts, back to `/` | `blog/index.html` | `home.php` (WP posts page) | Med |
| `/blog/generative-ai-tools.html` | fa | blog post | yes | none | 1 | back to blog | `blog/generative-ai-tools.html` | (redirected from old WP `/2025/05/11/`) | Low |
| `/blog/ai-career-transformation.html` | fa | blog post | yes | none | 1 | — | `blog/ai-career-transformation.html` | (redirected from `/2025/05/10/`) | Low |
| `/blog/neighborhood-management-award.html` | fa | blog post | yes | none | 1 | — | `blog/neighborhood-management-award.html` | (redirected from `/2021/07/23/`) | Low |
| `/blog/farabi-innovation-festival.html` | fa | blog post | yes | none | 1 | — | `blog/farabi-innovation-festival.html` | (redirected from `/2017/03/08/`) | Low |
| `/blog/digital-economy-15-percent-elecomp.html` | fa | blog post | yes | none | 1 | — | `blog/digital-economy-15-percent-elecomp.html` | WP post (DB) | Med |
| `/blog/masire-21.html` | fa | blog post | yes | none | 1 | — | `blog/masire-21.html` | WP post (DB) | Med |
| `/blog/expand-north-star-2025.html` | fa | blog post | yes | none | 1 | — | `blog/expand-north-star-2025.html` | **NOT in sitemap (F1)** | Med |
| `/blog/gitex-2025.html` | fa | blog post | yes | none | 1 | — | `blog/gitex-2025.html` | **NOT in sitemap (F1)** | Med |
| `/404.html` | — | error | no | none | none | 4 game pages | `404.html` + `404-{breakout,invaders,packet,pulse}.html` | (served by `.htaccess` ErrorDocument) | Low |

**Hreflang group G1** (applies to all 9 CV pages): `en, fa, ar, de, es, fr, tr, zh-Hans, ja, x-default` — fully cross-referenced.

## Redirects (`.htaccess` 301)

| From (old WP permalink) | To |
|---|---|
| `/2025/05/11/…` | `/blog/generative-ai-tools.html` |
| `/2025/05/10/…` | `/blog/ai-career-transformation.html` |
| `/2021/07/23/…` | `/blog/neighborhood-management-award.html` |
| `/2017/03/08/…` | `/blog/farabi-innovation-festival.html` |
| `/20YY/MM/DD/…` (catch-all) | `/blog/` |

## Reserved namespace for Layer B (Phase 3 — no current conflicts)

Planned additive sections (confirmed absent on disk): `/enterprise-ai/`, `/quantum/`, `/knowledge/`, `/research/`, `/case-studies/`, `/insights/`, `/news/`, `/glossary/`, `/faq/`. REST namespace: `/wp-json/kohandezh/v1/*` (new; the existing `kdcv/v1/ask` stays under the `kdcv` namespace owned by the `kohan-avatar` plugin).

## Machine-readable inventory

See `url_inventory.json` (generated alongside this doc) for the same data in a parseable form (consumed by link-check and sitemap tests in Phase 12).

---

### `url_inventory.json`

```json
{
  "generated": "2026-07-25",
  "hreflang_group_G1": ["en","fa","ar","de","es","fr","tr","zh-Hans","ja","x-default"],
  "urls": [
    {"url":"/","lang":"en","type":"cv-front","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"index.html","wp":"front-page.php","risk":"low"},
    {"url":"/fa.html","lang":"fa","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"fa.html","wp_slug":"/fa/","risk":"low"},
    {"url":"/ar.html","lang":"ar","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"ar.html","wp_slug":"/ar/","risk":"low"},
    {"url":"/de.html","lang":"de","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"de.html","wp_slug":"/de/","risk":"low"},
    {"url":"/es.html","lang":"es","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"es.html","wp_slug":"/es/","risk":"low"},
    {"url":"/fr.html","lang":"fr","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"fr.html","wp_slug":"/fr/","risk":"low"},
    {"url":"/tr.html","lang":"tr","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"tr.html","wp_slug":"/tr/","risk":"low"},
    {"url":"/zh.html","lang":"zh-Hans","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"zh.html","wp_slug":"/zh/","risk":"low"},
    {"url":"/ja.html","lang":"ja","type":"cv","canonical":true,"hreflang":"G1","schema":["Person","Organization","FAQPage"],"static":"ja.html","wp_slug":"/ja/","risk":"low"},
    {"url":"/PSN.html","lang":"en","type":"standalone","canonical":true,"hreflang":null,"schema":[],"static":"PSN.html","wp_slug":"/psn/","risk":"low"},
    {"url":"/Certificates.html","lang":"en","type":"standalone","canonical":true,"hreflang":null,"schema":[],"static":"Certificates.html","wp_slug":"/certificates/","risk":"low"},
    {"url":"/videos.html","lang":"en","type":"standalone","canonical":true,"hreflang":null,"schema":[],"static":"videos.html","wp_slug":null,"risk":"medium"},
    {"url":"/portfolio/","lang":"en","type":"portfolio","canonical":true,"hreflang":null,"schema":[],"static":"portfolio/index.html","wp_slug":"/portfolio/","risk":"low"},
    {"url":"/blog/","lang":"fa","type":"blog-index","canonical":true,"hreflang":null,"schema":[],"static":"blog/index.html","wp":"home.php","risk":"medium"},
    {"url":"/blog/generative-ai-tools.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"redirect_from":"/2025/05/11/","risk":"low"},
    {"url":"/blog/ai-career-transformation.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"redirect_from":"/2025/05/10/","risk":"low"},
    {"url":"/blog/neighborhood-management-award.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"redirect_from":"/2021/07/23/","risk":"low"},
    {"url":"/blog/farabi-innovation-festival.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"redirect_from":"/2017/03/08/","risk":"low"},
    {"url":"/blog/digital-economy-15-percent-elecomp.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"risk":"medium"},
    {"url":"/blog/masire-21.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"risk":"medium"},
    {"url":"/blog/expand-north-star-2025.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"in_sitemap":false,"risk":"medium"},
    {"url":"/blog/gitex-2025.html","lang":"fa","type":"blog-post","canonical":true,"schema":[1],"in_sitemap":false,"risk":"medium"},
    {"url":"/404.html","lang":null,"type":"error","canonical":false,"hreflang":null,"schema":[],"static":"404.html","risk":"low"}
  ],
  "layer_b_reserved": ["/enterprise-ai/","/quantum/","/knowledge/","/research/","/case-studies/","/insights/","/news/","/glossary/","/faq/"],
  "rest_namespaces": {"existing":"kdcv/v1 (kohan-avatar plugin: /ask)","planned":"kohandezh/v1 (Phase 5/6)"}
}
```
