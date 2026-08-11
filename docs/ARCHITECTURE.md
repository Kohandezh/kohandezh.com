# Architecture — Kohandezh.com Knowledge Platform

> Reference architecture for Layer A (existing personal brand) + Layer B (additive Enterprise AI & Quantum Knowledge Platform). Aligned with Agent.md (base + autonomous addendum). All work is additive, reversible, and isolated from Layer A.

## 1. Two-layer model

```
                ┌─────────────────────────────────────────────┐
                │           PRODUCTION (WordPress)             │
                │                                             │
   Layer A  ──▶ │  Theme: KohandezhCV (generated from static) │
   (preserve)   │   + Plugin: kohan-avatar (AI pet, /ask)     │
                │   + Plugin: kohandezh-ai-hub (pre-existing) │
                │                                             │
   Layer B  ──▶ │  Plugin: kohandezh-knowledge (NEW, Phase 5)  │  ← all hub logic lives here
   (additive)   │   CPTs, taxonomies, REST kohandezh/v1,      │
                │   schema, conditional templates              │
                └─────────────────────────────────────────────┘
                           ▲
                           │ sync-from-static.py (guardrail)
                           │
                ┌─────────────────────────────────────────────┐
                │  STATIC SITE (repo root) = SOURCE OF TRUTH   │
                │  index.html, *.html, assets/, blog/ …        │
                └─────────────────────────────────────────────┘
```

**Layer A** = personal brand (homepage, 9 CV locales, blog, PSN/Certificates/videos/portfolio). Frozen, sync-regenerated. **Layer B** = knowledge ecosystem (Enterprise AI hub, Quantum hub, news, glossary, FAQ, KG). All in a new plugin.

## 2. Source of truth & sync boundary

- **Static repo root** is canonical for Layer A. `_tooling/wp-theme/sync-from-static.py` regenerates 12 theme templates + mirrors `assets/` (Phase 0: verified parity, `--dry-run`, guardrails).
- **Layer B does NOT flow through this sync.** Hub content lives in the WP DB (CPTs) + a plugin; it has no static-source equivalent. This keeps the Layer-A parity invariant clean and re-checkable.
- Hand-maintained theme files (`functions.php`, `home.php`, `single.php`, `index.php`) are never touched by sync or Layer B.

## 3. Component ownership

| Concern | Owner | Notes |
|---|---|---|
| Layer A CV pages | static → theme (generated) | sync boundary |
| Layer A assets | static → theme (mirror) | `rsync --delete` exact |
| Layer A blog | WP DB (`home.php`/`single.php`) | posts not synced from static |
| AI pet (`kdcv/v1/ask`) | `kohan-avatar` plugin | existing; leave in place |
| Pre-existing AI hub | `kohandezh-ai-hub` plugin | **inspect before Phase 5** — may conflict |
| Layer B (hubs, KG, news, glossary, FAQ) | **`kohandezh-knowledge` plugin (NEW)** | all new CPT/taxonomy/REST/schema here |
| Theme hardening | `kohandezhcv/functions.php` | untouched by Layer B |
| Redirects + security headers | `.htaccess` | preserve 301 map |

## 4. Layer B plugin strategy (`kohandezh-knowledge`)

- **Self-contained.** Owns CPTs (`kdcv_knowledge`, `kdcv_news`, `kdcv_glossary`, … only if no existing equivalent), taxonomies (`topic`, `industry`, `technology`, `evidence_status`, …), REST namespace `kohandezh/v1`, and schema generation.
- **Conditionally loaded.** Registers no global hooks that run on Layer A. Hub templates load only on Layer B routes (`/enterprise-ai/`, `/quantum/`, etc.). Zero new CSS/JS/queries on the homepage.
- **Feature-flagged.** Each subsystem behind a constant/option (`KDCV_KB_ENABLE_NEWS`, etc.) → instant disable = zero prod impact.
- **DB-safe activation.** On activate: register CPTs/taxonomies + set a version option. No destructive migration. Idempotent.
- **Separate asset root.** Plugin serves its own `assets/` via `plugins_url()` — never pollutes theme `assets/` (preserves the sync mirror invariant).

## 5. Data & content ownership

| Data | Lives in | Editable via |
|---|---|---|
| Layer A page content | static HTML (repo) | code (sync to theme) |
| Layer A blog posts | WP `wp_posts` | wp-admin editor |
| Layer B knowledge/news/glossary | WP CPT tables | wp-admin (custom) |
| Knowledge Graph (entities, claims) | CPTs + post meta + a dedicated `kdcv_claims` table (Phase 4) | wp-admin + REST |
| Entity IDs | stable URIs (`https://kohandezh.com/entity/{slug}`) | deterministic |

## 6. REST architecture

- **Existing:** `kdcv/v1/ask` (kohan-avatar) — AI pet. **Do not modify.**
- **New (Layer B):** `kohandezh/v1/{entities,topics,articles,graph}` — read-only, paginated, permission-checked (`__return_true` for public reads but no private meta exposed), output-escaped, versioned. Detailed in Phase 5.3.

## 7. Multilingual model

- Layer A: file-per-locale (manual) — `fa.html`, etc. No ML plugin assumed.
- Layer B: **translation-group** model. Each entity has a canonical source + translation-group ID; hreflang generated from the group. Phase 9 priority: fa → en → strategic → rest. No thin MT translations.

## 8. Editorial & news workflow

- News pipeline (Phase 8): collect → normalize → dedupe → rank → verify → extract entities → connect to KG → draft → analyze → cite → review → translate → schedule → publish → monitor.
- **Default state: draft.** Nothing auto-publishes to production. Editorial approval required. Source registry is configurable + allowlisted.

## 9. Deployment & rollback model

- **Local-first.** All Layer B built + tested locally (temp-theme tests, lint, link/schema checks).
- **Production gated** (Agent.md addendum §19): deploy only after backup, dry-run, parity test, rollback tested, no destructive migration, no permalink change, no homepage change.
- **Rollback:** disable the `kohandezh-knowledge` plugin → Layer B vanishes, Layer A untouched. Per-feature rollback via flags. See `ROLLBACK.md`.

## 10. Observability

- Phase 11/12: logging without secrets, view counters (existing pattern), queue idempotency, duplicate-job prevention, rate limits, error capture. No PII leakage.
