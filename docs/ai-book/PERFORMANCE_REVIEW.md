# AI Book Performance Review Gate

- **Date:** 2026-09-20
- **Scope:** per-view artifact loading map, measured server-side costs on the canonical root, payload budget, per-request scan bounds, caching posture of views and the PDF stream.
- **Verdict:** **PASS** with one finding fixed in-gate (`PERF-1`) and documented bounds.
- **Method:** direct measurement with PHP (microtime + memory peaks) against `/Users/emperor/Documents/AI/AiBook`, static audit of load paths per view, verified by the full test chain (`npm run test:ai-book` 10 suites + `npm test` 18 suites, all PASS after the fix).

## Measured server-side costs (canonical root, single PHP process)

| Operation | Measured | Notes |
| --- | --- | --- |
| Repository bundle (book + content IDs + citations) | 29.8 ms | ~1MB JSON; loads on every view (needed) |
| Catalog full load (glossary + sources + templates + entities) | 30.5 ms | 1.24MB entities + manifests; catalog views only |
| Graph load + stats | 82.8 ms | 4.8MB graph.json; lazy — graph views only (ADR-AB-0012) |
| Search query «مدیریت ریسک» (20 results) | 42.3 ms | 1.3MB index + glossary; search views only |
| Ask retrieval-only (6 cited hits) | ~207 ms | one streamed 2.6MB corpus line-scan per query; the heaviest view, still sub-second |
| PDF meta (manifest + header/size validation) | 0.4 ms | the 38MB file is never read for the viewer page |
| Reader `related_map` (5-section chapter) | 1.0 ms | single bounded scan over validated mentions |

Peak memory with **all** subsystems loaded simultaneously (worst case, not a real view): 62 MB. Real views load only what they need (map below).

## Per-view artifact loading map (enforced by design)

| View | Artifacts touched | Heavy artifact avoided |
| --- | --- | --- |
| home | bundle | graph.json, corpus, catalog |
| read | bundle + catalog mentions (related) | graph.json, corpus |
| search | bundle + search index + glossary | graph.json, entities |
| glossary/sources/templates/concepts | catalog | graph.json, corpus |
| graph | catalog + graph.json | corpus |
| ask | bundle + corpus (streamed scan) | graph.json |
| pdf | bundle + manifest + 5 header bytes | the 38MB PDF (never read server-side for the page) |
| request-pdf | bundle (edition string) | everything else |
| stream | validated plan (path from manifest) | nothing beyond the file itself |

## Checklist and findings

| Area | Check | Result |
| --- | --- | --- |
| Stream re-downloads | **PERF-1 (fixed):** the 38MB stream shipped `Cache-Control: no-store`, forcing a full re-download on every open. Now the stream carries a strong `ETag` derived from the manifest sha256 (integrity-preserving) with `Cache-Control: private, max-age=0, must-revalidate`, and the executor answers matching `If-None-Match` with a bodyless 304 — repeat opens cost one conditional request, zero file reads. No public/proxy caching (still privacy-correct); pure matcher `etag_matches()` unit-tested for exact/weak/list/`*`/mismatch/absent cases. | FIXED |
| Payload budget | `ai-book.css` 19.6KB (single file, system/frozen fonts, no webfont downloads beyond the theme's existing pair), `ai-book.js` 600B (existing enqueue contract, no libraries). No images in the feature; SVG graph is inline and capped at 12 nodes. | PASS |
| Per-request algorithmics | All scans are linear and bounded: corpus line-scan with capped accumulation (≤6), entity/mention single-scan for related wiring, graph depth-1 capped at 24 edges, search index decoded once per request, pagination clamped (60), snippets/excerpts/quotes/answers length-capped. No N+1: mention resolution is a capped (≤12) in-memory map lookup. | PASS |
| View caching | HTML views send no special cache headers (default WP behavior — upstream page caching may apply; pages are noindex so crawler caching is moot). Browser reuse of CSS/JS is versioned via `KBK_VERSION`. | PASS |
| JSON decode cost | The four large decodes (bundle ~1MB, entities 1.24MB, search 1.3MB, graph 4.8MB) are measured above and each is confined to its own view family; nothing decodes the 38MB PDF or the DOCX/HTML deliverables. | PASS |
| Ask view latency | ~240 ms total (bundle + one corpus scan) is honest local retrieval cost; generation (when configured) is an external call with a 30s timeout and cannot be blocked by local work. | PASS |
| No regressions | `npm run test:ai-book` (10 suites) and `npm test` (18 suites) PASS after PERF-1. | PASS |

## Residual risks (accepted, documented)

1. **Per-request JSON re-decoding** — WordPress has no object cache assumed; every request re-decodes its view's artifacts (worst: graph views ≈ 83 ms + bundle). If production ever feels this, a persistent object cache (Redis/Memcached) can hold the validated read models without any code change to the fail-closed boundary.
2. **Ask corpus scan** — one streamed scan per query (~207 ms). A prebuilt inverted index is the natural optimization if ask traffic grows; deliberately not built now (local-only surface, honest bounds).
3. **PDF 304 support** — clients that skip conditional requests still transfer 38MB once per session; acceptable for a non-indexable, provenance-tracked edition.
