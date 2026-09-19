# AI Book Design System Snapshot

Status: FROZEN for implementation baseline on 2026-09-19.

## Existing visual language to extend

The AI Book inherits the site's long-form language rather than creating a parallel brand:

| Token/Pattern | Frozen value or source |
| --- | --- |
| Dark background | `#080b0d` |
| Dark text | `#f1f8ef` |
| Primary lime | `#a8ff46` |
| Accessible accent text | `#b8ff78` or existing `--b-accent-ink`; do not use surface green blindly for text |
| Panel | approximately `#101618` / existing blog panel token |
| Border | `rgba(255,255,255,.12)` |
| Persian typography | local Estedad or IRANSansX, following the hosting surface |
| Latin/code fallback | Inter and system monospace |
| Shape | 12–28px cards; pill controls for compact actions |
| Touch target | minimum 44×44px |
| Motion | restrained; disable under `prefers-reduced-motion` |
| Focus | visible 3px lime outline with offset |

Authoritative sources are `assets/css/blog.css` and `assets/css/knowledge.css`. The prototype at `docs/ai-book/prototype/` records the frozen composition but is not production code.

## Frozen component vocabulary

- Site/book header with compact brand and Search, Sources, Verify utilities.
- Desktop three-column reader: book TOC, 52–70ch article, in-page rail.
- Tablet two-column reader: TOC plus article; in-page rail hidden.
- Mobile single column: horizontal chapter rail followed by article.
- Chapter hero: part/chapter label, title, dek, content ID, source and reading time.
- Semantic prose sections with stable heading anchors.
- Distinct source-translation, editorial-synthesis, and Iran-localization callouts.
- Horizontally scrollable responsive table wrapper with keyboard focus.
- Provenance card containing source, origin, edition, content ID/hash verification link.
- Previous/next navigation.
- Later: search results, RAG citation cards, glossary cards, KG explorer, PDF/request surfaces must reuse these tokens.

## RTL and content rules

- Use `lang="fa" dir="rtl"` and CSS logical properties by default.
- Keep identifiers, hashes, URLs, code, and mixed Latin strings isolated with appropriate directionality (`bdi`/`dir="ltr"`) in production.
- Never reverse semantic DOM order to achieve visual RTL.
- Tables scroll inside a labeled region; the page itself must not overflow horizontally.
- Long Persian titles wrap naturally; no forced truncation in the reader.

## Accessibility baseline

Semantic header/nav/main/article/aside/footer landmarks, one page H1, hierarchical headings, skip link, keyboard-visible focus, labeled regions, 44px targets, reduced motion, and WCAG 2.1 AA contrast are frozen requirements.
