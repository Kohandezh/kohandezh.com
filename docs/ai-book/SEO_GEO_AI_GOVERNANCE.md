# SEO / GEO — AI Governance

Status: `IMPLEMENTED_LOCAL`  
Preferred Persian public namespace: `/fa/books/ai-governance/`  
Legacy application namespace: `/ai-book/*` (preserved; no broken saved links)  
Language: `fa-IR`

## Search intent and terminology

The primary bilingual entity name is **AI Governance — حاکمیت هوش مصنوعی**. The source control IDs (`GOVERN 1.1` … `GOVERN 6.2`) remain provenance identifiers, while every visible section title now carries the complete bilingual subject and its number.

Primary topic cluster:

- حاکمیت هوش مصنوعی / AI Governance
- چارچوب حاکمیت هوش مصنوعی
- سیاست هوش مصنوعی سازمانی
- مدیریت ریسک هوش مصنوعی / NIST AI RMF
- کمیته راهبری هوش مصنوعی، نقش‌ها و پاسخگویی
- فهرست دارایی‌های هوش مصنوعی و کنترل تأمین‌کننده
- نظارت انسانی، پایش مدل و شواهد ممیزی

Audience-intent cluster:

- حاکمیت هوش مصنوعی در دستگاه‌های دولتی و نهادهای عمومی
- حاکمیت هوش مصنوعی در بانک، بیمه و خدمات مالی
- حاکمیت هوش مصنوعی در صنعت، سلامت و زیرساخت

No search-volume or ranking claim is made without Search Console or an SEO data provider. Search visibility and ranking cannot be guaranteed.

## Implemented technical signals

- Dedicated, crawlable Persian landing page with one descriptive H1 and source-grounded definition.
- Preferred canonical URLs under `/fa/books/ai-governance/`; `/books/ai-governance/*` and old `/ai-book/*` rewrites remain available for continuity.
- The URL contract for a future real translation is `/{locale}/books/ai-governance/`. Only `fa` is routed and advertised today; untranslated locales have no fabricated pages or `hreflang` entries.
- Unique title and meta description per application surface.
- Open Graph title, description, locale and URL.
- JSON-LD graph using `Book` plus `WebPage`, `Chapter`, or `CollectionPage` as appropriate.
- WordPress sitemap provider for the indexable governance landing, chapter, news, glossary, sources, templates, concepts, graph and PDF viewer pages.
- Search, Ask, request forms and the raw PDF stream stay `noindex`; these are utility/result surfaces, not canonical landing pages.
- News page lists only real published `kbk_news` posts matching the topic. It explicitly renders an empty state instead of inventing news.
- Existing `/fa/ai-book/*` citation URLs, structural IDs and source provenance remain unchanged.

## Content architecture

- `/fa/books/ai-governance/` — pillar page and section index.
- `/fa/books/ai-governance/read/?kbk_part=P02&kbk_chapter=C09` — full NIST-based governance chapter.
- `/fa/books/ai-governance/news/` — real published news and analysis collection.
- `/fa/books/ai-governance/glossary/` — terminology support.
- `/fa/books/ai-governance/sources/` — source transparency.
- `/fa/books/ai-governance/templates/` — implementation intent.
- `/fa/books/ai-governance/concepts/` and `/graph/` — entity and relationship discovery for answer engines.

## Editorial rules for future news

Every news or analysis item must have a real source URL, author, publication/update date, a clear distinction between reported fact and Kohandezh analysis, and internal links to the relevant book section. Do not manufacture agency-specific pages or repeat the same text with organization names swapped. Build an organization page only when it contains genuinely distinct requirements, controls, examples or evidence.

## Production prerequisites

1. Keep `KBK_AI_BOOK_INDEXABLE` false during local/staging QA.
2. On approved production release, set it true only after canonical, sitemap and duplicate-schema checks pass.
3. Flush WordPress rewrites and page cache.
4. Verify the provider appears in `wp-sitemap.xml` and submit the sitemap in Search Console.
5. Inspect representative URLs in Google URL Inspection; validate visible content against JSON-LD.
6. Use Search Console queries to prioritize real agency/industry pages. Do not create doorway pages.

## Reference basis

Implementation follows Google Search guidance that canonical signals are strongest when redirects/canonical annotations and sitemap inclusion agree, and that structured data must represent visible page content. See Google Search Central documentation for canonicalization, sitemaps, breadcrumbs and structured-data policies.
