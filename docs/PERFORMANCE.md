# Performance — Kohandezh.com Knowledge Platform

> Layer A Core Web Vitals must not regress. Layer B loads only on Layer B routes. Aligned with Agent.md addendum §16/§20.

## 1. Invariants (verified by tests)
- **No global enqueue.** The `kohandezh-knowledge` plugin enqueues nothing site-wide; its tiny CSS is inline-scoped inside Layer B templates only (Phase 6). `wp_head` JSON-LD emission is gated by `KBK_Routes::is_layer_b()` (Phase 12 test: homepage HTML contains zero Layer B markers).
- **No front-end full-site scans.** REST reads are bounded (`per_page` ≤ 50) and `WP_Query` uses `no_found_rows` where pagination isn't needed.
- **No new autoloaded options.** Only `kbk_schema_version` (single small option).

## 2. Layer A budget (preserve)
- Homepage payload baseline (current): index.html ~166 KB + assets. Layer B adds **0 bytes** to Layer A pages (isolation test in `run.sh`).
- The homepage already loads `styles.min.css`, GSAP/Swiper, AI pet (lazy). Layer B must not inject any of its own assets there.

## 3. Layer B budget
- Hub/archive pages: single HTML doc + ~3 KB inline scoped CSS. No external JS in MVP.
- REST: target ≤ 200 ms p95 (local, cached). Bounded result sets.
- Entity pages: O(1) DB lookups by slug; terms eager via `get_the_terms`.

## 4. Caching strategy (Phase 11 build, behind flags)
- REST responses: `Cache-Control: public, max-age=300` for list endpoints; short TTL.
- Topic/related queries: cached via transients with bounded keys (cleared on term/post save).
- No caching of unverified content.

## 5. Performance smoke (run.sh — Tier 4)
- Homepage byte size vs baseline (must be equal pre/post Layer B activation).
- Homepage HTML grep for Layer B asset strings (must be absent).
- REST list endpoint timing (local).

## 6. Anti-patterns
- No blocking remote calls during page render (news fetch is background + flag-gated).
- No large `autoload` options.
- No N+1 term queries in loops (use `get_the_terms` per post or prime caches).
