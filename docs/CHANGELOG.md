# Changelog — Kohandezh.com Knowledge Platform

> Meaningful changes only. Newest first. Aligned with Agent.md addendum §23.

## [Phase 6–13] — 2026-07-25 — Hub MVP → Verified
- **Phase 6 (Hub MVP):** `_tooling/wp-theme/kohandezh-knowledge/templates/` (layer-b.php + partial-hub/archive/entity) — standalone full-HTML render; `KBK_Routes` (virtual routes `/enterprise-ai/`, `/quantum/`, `/entity/{slug}` + CPT archive interception + `is_layer_b()` isolation gate); `KBK_Schema` (JSON-LD + BreadcrumbList, emitted ONLY on Layer B); `KBK_Seed` + `fixtures/seed.json` (5 glossary terms published + 2 drafts; `_kbk_fixture` marker; Tools admin install/remove).
- **Phase 7:** `docs/PILLAR_ROADMAP.md`, `TOPIC_CLUSTERS.md`, `INTERNAL_LINKING.md` (design, not bulk-publish).
- **Phase 8:** `docs/NEWS_ARCHITECTURE.md`; `fixtures/sources.json` (16 AI + 10 Quantum allowlisted sources); `KBK_News` (seeds `kbk_source`, REST `/sources`, SSRF guard `is_safe_remote_url`, `ingest()` creates draft+unverified, gated behind `KBK_FEATURE_NEWS_FETCH`=OFF).
- **Phase 9:** `docs/I18N.md` (phased fa→en→strategic→rest; translation-group model; `?lang=` strategy).
- **Phase 10:** `_tooling/tests/validate-jsonld.py` (parses+validates JSON-LD; Layer A validated live).
- **Phase 11:** `docs/PERFORMANCE.md` + `_tooling/tests/check-security.sh`.
- **Phase 12:** `_tooling/tests/run.sh` (Tier 0–4) → **30/30 green**.
- **Phase 13:** `docs/VERIFICATION_REPORT.md`. Layer A byte-intact (sync parity 0/0/0; home+9 locales+blog+portfolio+PSN+Certificates 200; JSON-LD valid; homepage has zero Layer B markers). Production deploy NOT performed (G6 unmet).

## [Phase 3–5] — 2026-07-25 — IA, Ontology, Content Model
- **Phase 3 (IA):** `docs/INFORMATION_ARCHITECTURE.md` — chose Layer B routes (`/enterprise-ai/`, `/quantum/`, `/knowledge/`, `/research/`, `/case-studies/`, `/insights/`, `/news/`, `/glossary/`, `/faq/`, `/entity/{slug}`); conflict-checked against static site + theme rewrites (none). Topic clusters (EA + Quantum + cross-cutting industry/tech). News channel design.
- **Phase 4 (Ontology):** `docs/ONTOLOGY.md` — entity types, central entity IDs (Dr. Kohandezh, KSF, Padyar), relationship predicates, claim/evidence model + vocabulary, machine-readable outputs. Validation deferred to Phase 12.
- **Phase 5 (Content Model):** built `_tooling/wp-theme/kohandezh-knowledge/` plugin — bootstrap (feature flags, activation/deactivation flush, `_kbk_*` protected-meta guard) + `class-kbk-post-types.php` (5 CPTs `kbk_{knowledge,news,glossary,case,research}`; 8 taxonomies `kbk_{topic,industry,tech,ai_domain,quantum_domain,evidence,content_type,source}`; registered meta with sanitize callbacks; canonical entity URI `KBK_ENTITY_BASE.{slug}`) + `class-kbk-rest.php` (REST `kohandezh/v1/{entities,entities/{id},topics}`; pagination ≤50; hides `unverified/disputed/deprecated`). PHP lint 3/3 clean; no forbidden patterns; `kbk_*` prefix = zero collision (ADR-0004 cleared: `kohandezh-ai-hub` registers only `kdcv/v1/ask`). Not activated anywhere (local artifact; production gated).

## [Phase 2] — 2026-07-25 — Governance documentation
- Created `docs/`: `AUDIT.md`, `URL_INVENTORY.md` (+ `url_inventory.json`), `MIGRATION_RISK.md`, `PRODUCTION_UNKNOWNS.md`, `STATIC_WP_PARITY.md`, `ARCHITECTURE.md`, `ONTOLOGY.md`, `CONTENT_POLICY.md`, `SECURITY.md`, `TESTING.md`, `DEPLOYMENT_GATES.md`, `ROLLBACK.md`, `DECISIONS.md`.
- Appended "AUTONOMOUS EXECUTION DIRECTIVE" addendum to `Agent.md` (supersedes base §28 approval gate).
- Created `progress.md` (resumable tracker for session handoff).
- **Key decision:** Layer B will live in a new `kohandezh-knowledge` plugin (isolated, feature-flagged), not in the theme — preserves the Layer-A sync invariant.

## [Phase 1] — 2026-07-25 — Audit
- Audited 27 static HTML pages, SEO/meta matrix, JSON-LD (Person/Org/FAQPage), sitemap, llms.txt, internal-link graph, `.htaccess` redirect map.
- Audited `kohandezhcv` theme (v1.3.0): `functions.php` registers **no CPT/taxonomy/REST**; thin engine (page migrations, hardening, view counter, branded login, home blog feed).
- Identified 2 plugins: `kohan-avatar` (owns `kdcv/v1/ask`), `kohandezh-ai-hub` (pre-existing — collision risk for Phase 5).
- Findings: F1 sitemap stale (6 vs 8 blog posts); F2 `single.php` lacks Article JSON-LD; F6 no Layer B path conflicts on disk.

## [Phase 0] — 2026-07-25 — Sync guardrail repair
- Repaired `_tooling/wp-theme/sync-from-static.py` path resolution (`SCRIPT_DIR.parents[1]` = project root; `STATIC_ROOT = PROJECT_ROOT`; `THEME_ROOT = SCRIPT_DIR/kohandezhcv`) after the repo flatten + `_tooling/` relocation.
- Added `--dry-run` (no writes; reports create/update/unchanged/delete counts) and `--theme-root PATH` (controlled tests).
- Added path-validation guardrails (8 conditions) + resolved-path printing.
- Verified: dry-run parity (12/12 templates, 438/438 assets, 0 deletes); temp-theme corruption test (byte-identical repair); PHP lint 16/16; guarded real run = 0 changes.
- Updated `_tooling/README.md`, `DEPLOY.md` (path fixes + 8735/8888 notes). Rollback: `.bak-phase0`.

## [Pre-Phase 0] — 2026-07-25 — Repository restructure
- Flattened nested `kohandezh.com/kohandezh.com/` → repo root (static site now flat).
- Removed retired local WP stack (port 8888): containers `kohandezh_wp`/`kohandezh_db` + volumes.
- Grouped non-site folders under `_tooling/` (`wp-theme`, `wp-local`, `upload`, `avatar`).
- Deleted obsolete backups (3 zips + `_archive/` + `_remote-archive/`); 1.8 GB → 244 MB.
- Updated `run-dev.sh` (`-t .`), `dev-server.php` (`$STATIC_ROOT = __DIR__`), `build.sh` (`assets/…`) for flat docroot.
