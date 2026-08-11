# Testing Policy — Kohandezh.com Knowledge Platform

> Tests are evidence, not ceremony. A passing build is necessary but not sufficient. Layer A parity is re-verified every change; Layer B is feature-flagged and isolated. Aligned with Agent.md base §22 and addendum §12/§17.

## 1. Test tiers

### Tier 0 — Static checks (every change, fast)
- `python3 -m py_compile _tooling/wp-theme/sync-from-static.py`
- `find _tooling/wp-theme/kohandezhcv _tooling/wp-theme/kohandezh-knowledge -name '*.php' -print0 | xargs -0 -n1 php -l`
- JS syntax: `node --check` on custom JS (where node available).
- Forbidden-pattern grep: `eval(`, `exec(`, `file_get_contents(<http`, raw API keys.

### Tier 1 — Sync parity (every change touching static or theme)
- `python3 _tooling/wp-theme/sync-from-static.py --dry-run` → must report 0 errors; deletion count reviewed.
- Temp-theme controlled test: copy theme → `/tmp`, corrupt, sync into temp, diff vs real → 0 unexpected diffs; PHP lint temp.

### Tier 2 — Layer A integrity (regression)
- Homepage returns 200; title contains "Mohammad Ali Kohandezh".
- All 9 CV locales return 200; each has exactly 1 canonical + 11 hreflang + 2 JSON-LD.
- `/blog/`, `/portfolio/`, `/PSN.html`, `/Certificates.html` return 200.
- Intentional 404 returns 404.
- JSON-LD parses (`json_decode` valid) on CV pages; `@type` set includes Person+Organization.
- `.htaccess` 301 redirects intact (curl old permalinks → 301 to expected targets).
- Assets: no broken internal links (href → existing file).

### Tier 3 — Layer B tests (as built)
- Each new CPT registers without error; activate/deactivate is idempotent.
- REST `kohandezh/v1/*` routes resolve; pagination respected; permission callbacks fire.
- Public reads never return `evidence_status=unverified` entities.
- Conditional loading: homepage HTML does NOT contain Layer B asset strings (isolation).
- Schema: Layer B JSON-LD parses; `@id` stable; no duplicate conflicting Person/Org.
- Multilingual: translation-group links are symmetric; hreflang consistent within a group.

### Tier 4 — Performance smoke
- Homepage payload size unchanged (±X%) vs baseline after Layer B (no global assets).
- REST responses ≤ 200ms p95 for cached reads (local).
- No new autoloaded options; no full-site scans on front-end requests.

## 2. Test fixtures & seed content

- Controlled seed corpus in `_tooling/tests/fixtures/` (Phase 6): verified / draft / placeholder / test — clearly labeled.
- Placeholders are never published (workflow status gate).
- Temp themes always under `/tmp` (never the real theme dir during a test).

## 3. Tools

- `grep`/`grep -r` for link/meta/schema assertions (`rg` is not on PATH in this shell — use `grep`).
- `curl` for HTTP/route/redirect tests against `http://127.0.0.1:8735/`.
- `php -l` for syntax; a small PHP harness (`_tooling/tests/php-jsonld-check.php`) for JSON-LD shape validation.
- `python3` for sitemap/URL-inventory cross-checks against `docs/url_inventory.json`.

## 4. CI / local runner

- A single runner script `_tooling/tests/run.sh` (Phase 12) executes Tier 0–2 + available Tier 3, exits non-zero on any failure, prints a summary. Safe to run anytime; writes nothing to the real theme.

## 5. Definition of "verified" (per phase)

A phase is verified only when its applicable tiers pass AND evidence (logs/diffs/counts) is recorded in the phase report + `progress.md`.
