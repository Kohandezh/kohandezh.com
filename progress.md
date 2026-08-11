# Progress — Kohandezh.com Knowledge Platform

> **Resumable tracker.** Any AI session can resume from here. Read this file + `Agent.md` (esp. the "AUTONOMOUS EXECUTION DIRECTIVE" addendum) first, then continue from **Current Task**.
- **Last updated:** 2026-07-25 (Phases 0–13 complete; local scope verified; production gated)
- **Mode:** Autonomous execution authorized (see Agent.md addendum §1–§3). Do not pause to ask approval between phases.
- **Scope lock:** Local, non-destructive, deployment-ready. Never touch production WP. Never redesign homepage.

---

## ⚡ Current Task (resume here)

**All local phases (0–13) are complete and verified (30/30 tests green).** Production deployment is the only remaining boundary — gated behind G6 (DEPLOYMENT_GATES.md) + ADR-0006.

**To resume / continue:**
1. If pursuing production deploy → satisfy G6 (prod backup, verify plugins U1–U14 in PRODUCTION_UNKNOWNS.md, decide SEO/ML plugin ownership, staging verify) → then package the plugin zip + deploy.
2. If extending Layer B → the hub MVP is in `_tooling/wp-theme/kohandezh-knowledge/`; next iterations: install KBK seed fixtures (`Tools → KBK Seed`), review PILLAR_ROADMAP.md P1 pillars (add real evidence), implement hreflang emitter (I18N.md), build KBK_Related caching.
3. Re-run verification anytime: `bash _tooling/tests/run.sh`.

**Phases 6–13 done this session:**
- Phase 6 (Hub MVP): templates (`templates/layer-b.php` + partials), virtual routes (`/enterprise-ai/`, `/quantum/`, `/entity/{slug}`), conditional JSON-LD + BreadcrumbList (`KBK_Schema`, gated by `is_layer_b`), controlled/removable seed fixtures (`KBK_Seed` + `fixtures/seed.json` + Tools admin page).
- Phase 7: `PILLAR_ROADMAP.md`, `TOPIC_CLUSTERS.md`, `INTERNAL_LINKING.md`.
- Phase 8: `NEWS_ARCHITECTURE.md` + `fixtures/sources.json` (16 AI + 10 Quantum) + `KBK_News` (registry + SSRF-safe ingest, fetch OFF by default, drafts only).
- Phase 9: `I18N.md` (phased fa→en→…; translation-group model).
- Phase 10: `_tooling/tests/validate-jsonld.py`.
- Phase 11: `PERFORMANCE.md` + `_tooling/tests/check-security.sh`.
- Phase 12: `_tooling/tests/run.sh` (Tier 0–4 runner) → **30/30 pass**.
- Phase 13: `docs/VERIFICATION_REPORT.md` (this session's evidence).

**Status:** Local scope COMPLETE. Production deploy NOT done (gated). Homepage NOT modified. Sync in parity.

**Phase 5 done:** `kohandezh-knowledge` plugin built at `_tooling/wp-theme/kohandezh-knowledge/` — main bootstrap (feature flags + activation/deactivation + protected-meta guard) + `includes/class-kbk-post-types.php` (5 CPTs: knowledge/news/glossary/case/research; 8 taxonomies; registered meta with sanitize; canonical entity ID) + `includes/class-kbk-rest.php` (REST `kohandezh/v1/{entities,entities/{id},topics}`, pagination ≤50, hides unverified/disputed/deprecated) + readme.txt. **PHP lint 3/3 clean; no forbidden patterns; `kbk_*` slugs = zero collision.** Not yet activated/installed anywhere (local-only artifact; deploy is gated).

**Next:** Phase 6 — add hub landing templates (isolated, conditional) + seed fixtures (`_tooling/tests/fixtures/`) + JSON-LD/BreadcrumbList; then Phase 7 (pillar roadmap docs PILLAR_ROADMAP/TOPIC_CLUSTERS/INTERNAL_LINKING), Phase 8 (news design + source registry), Phase 9–13. Production deploy remains gated (DEPLOYMENT_GATES G6).

---

## ✅ Verified State (facts)

- Production platform: **WordPress**. Static files (repo root) are the **source of truth**; `_tooling/wp-theme/sync-from-static.py` generates theme `kohandezhcv`.
- Local dev server: **http://localhost:8735/** via `./run-dev.sh` (PHP). Port **8888 removed** (Docker WP stack + volumes deleted).
- Repo root is **NOT a git repo**. The theme `_tooling/wp-theme/kohandezhcv/` has its own `.git`.
- Sync guardrail **operational**: `--dry-run`, `--theme-root <path>`, path guardrails. Current state = **parity** (dry-run = 0 create/0 update/0 delete).
- Central entity: **Dr. Mohammad Ali Kohandezh** (CEO, Kohan System Farda / KSF). `Agent.md` is canonical; **do not create AGENTS.md** (naming conflict unresolved).

---

## 🗺️ Phase Roadmap

| Phase | Status | Notes |
|---|---|---|
| 0 — Sync guardrail repair | ✅ Done | Script fixed + guardrails + dry-run; verified non-destructive. |
| 1 — Complete Audit | ✅ Done | 5 docs in docs/: AUDIT, URL_INVENTORY, MIGRATION_RISK, PRODUCTION_UNKNOWNS, STATIC_WP_PARITY |
| 2 — Governance docs | ✅ Done | 9 docs in docs/ + Agent.md addendum + progress.md |
| 3 — Information Architecture | ✅ Done | docs/INFORMATION_ARCHITECTURE.md; no path conflicts confirmed |
| 4 — Knowledge Graph & Ontology | ✅ Design done | docs/ONTOLOGY.md; validation in Phase 12 |
| 5 — WordPress content model | ✅ Done | `kohandezh-knowledge` plugin: 5 CPTs + 8 taxonomies + meta + REST `kohandezh/v1` (lint clean, no collision) |
| 6 — Hub MVP | ✅ Done | templates + virtual routes + conditional JSON-LD/BreadcrumbList + removable seed fixtures (KBK_Seed) |
| 7 — Pillar roadmap | ✅ Done | PILLAR_ROADMAP / TOPIC_CLUSTERS / INTERNAL_LINKING (design, not bulk-published) |
| 8 — News system | ✅ Done | NEWS_ARCHITECTURE + sources.json + KBK_News (SSRF-safe, drafts only, fetch OFF) |
| 9 — Multilingual | ✅ Design done | I18N.md (phased fa→en→…; translation-group model) |
| 10 — Structured data | ✅ Done | KBK_Schema JSON-LD emitter + validate-jsonld.py (Layer A validated live) |
| 11 — Security & performance | ✅ Done | SECURITY.md + PERFORMANCE.md + check-security.sh |
| 12 — Testing | ✅ Done | run.sh (Tier 0–4) → 30/30 pass |
| 13 — Local verification | ✅ Done | VERIFICATION_REPORT.md (30/30 green; Layer A intact) |

**Gates honored:** G1–G5 met. G6 (production) **not** satisfied → no deploy. No homepage change. No hard-stops hit.

---

## 📁 File Map

```
kohandezh.com/                      ← repo root = static site (flat)
├── index.html, {en,fa,ar,de,es,fr,tr,zh,ja}.html, PSN.html, Certificates.html, videos.html, 404*.html
├── assets/   (css js fonts images icon data kohan media contact)  — 455 files
├── blog/     index.html + 8 posts
├── portfolio/index.html
├── .htaccess robots.txt sitemap.xml llms.txt
├── Agent.md  (master prompt + autonomous addendum)  ← canonical agent contract
├── DEPLOY.md  (deploy checklist, path-updated)
├── docs/     (← Phase 1 outputs go here)  [created, empty]
├── progress.md  (this file)
├── run-dev.sh / dev-server.php / build.sh   (dev tooling, flat docroot)
└── _tooling/
    ├── README.md
    ├── wp-theme/
    │   ├── sync-from-static.py            (repaired; has .bak-phase0 rollback)
    │   ├── sync-from-static.py.bak-phase0 (rollback copy — keep until final verify)
    │   ├── kohandezhcv.zip                (generated artifact)
    │   └── kohandezhcv/                   (WP theme, own .git)
    │       ├── functions.php  (KDCV const, hardening, page migrations, REST ask route)
    │       ├── front-page.php + page-{lang,psn,certificates,portfolio}.php  (GENERATED — do not hand-edit)
    │       ├── home.php / single.php / index.php  (HAND-MAINTAINED — sync never touches)
    │       └── style.css
    ├── wp-local/  (retired 8888 docker-compose — reference only)
    ├── upload/ avatar/  (staging/source — not referenced by live site)
```

---

## 🔧 Key Commands

```bash
# Dev server (8735):
./run-dev.sh

# Sync guardrail:
python3 _tooling/wp-theme/sync-from-static.py --dry-run                 # no writes
python3 _tooling/wp-theme/sync-from-static.py                            # apply + zip
python3 _tooling/wp-theme/sync-from-static.py --theme-root /tmp/test --no-zip   # controlled test

# Lint:
python3 -m py_compile _tooling/wp-theme/sync-from-static.py
find _tooling/wp-theme/kohandezhcv -name '*.php' -print0 | xargs -0 -n1 php -l

# NOTE: `rg` is NOT on PATH in this shell — use `grep`/`grep -r` in bash. (The Grep *tool* works fine.)
```

---

## 🔬 Phase 1 audit findings (so far)

**Static site — pages (27 HTML):**
- Root: index.html (en) + 8 lang variants (fa/ar/de/es/fr/tr/zh/ja) + PSN, Certificates, videos, 404 (+4 404 game pages).
- blog/: index + 8 posts (ai-career-transformation, digital-economy-15-percent-elecomp, expand-north-star-2025, farabi-innovation-festival, generative-ai-tools, gitex-2025, masire-21, neighborhood-management-award). **Note:** sitemap lists 6 posts but 8 exist on disk → sitemap stale (audit finding).
- portfolio/: index.html.

**SEO/meta per page:**
- All 9 CV pages: canonical=1, hreflang=11 (en+8 lang+x-default), og=19, twitter=4, jsonld=2 ✓
- PSN/Certificates: canonical=1, **hreflang=0, jsonld=0** (standalone, no hreflang/JSON-LD)
- videos.html: canonical=1, og=6, **twitter=0, jsonld=0**
- 404.html: no canonical/hreflang/og/jsonld (expected for 404)

**JSON-LD types on CV pages:** Person, Organization, PostalAddress, FAQPage (5 Question / 5 Answer). Strong entity markup present.

**Sitemap (sitemap.xml):** 9 CV + PSN + Certificates + videos + portfolio + blog index + 6 posts. Hreflang alternates only on CV pages. `zh` listed as `zh-Hans`.

**llms.txt:** well-formed; declares personal portfolio, lists pages, notes Person/Organization schema present.

**assets/data:** `home-blog.json` (home blog feed + pinned), `psn-kohandezh.json`.

**WP theme — functions.php (first 90 lines):**
- `KDCV` = template dir URI; `KDCV_CONTENT_SCHEMA_VERSION = 1.3.0`.
- Hardening: `DISALLOW_FILE_EDIT`, xmlrpc off, generator/rsd/wlwmanifest removed, REST `/wp/v2/users` blocked for anon, `?author=` blocked, security headers (HSTS/X-Content-Type/X-Frame/Referrer/Permissions).
- `kdcv_required_pages()`: home + 8 lang + psn + certificates + blog + portfolio (auto-created pages).
- REST endpoint `kdcv/v1/ask` exists (AI pet). More in lines 91–480 (TODO read).

---

## 📝 Assumptions Log (labeled, per directive §6.4)

- **A1** Production runs the `kohandezhcv` theme from this repo. *(not directly verified — no prod access)*
- **A2** Deleting local 8888 volumes lost only local test data; prod DB untouched. *(user-confirmed earlier)*
- **A3** Prod multilingual plugin unknown (Polylang/WPML/manual?). Static uses file-based langs (fa.html).
- **A4** Prod SEO plugin unknown (Yoast/RankMath/none). Sitemap on prod may be plugin-generated.
- **A5** No AI/Quantum knowledge content exists yet on Layer A (to confirm in full audit).

---

## 🛑 Hard Stops (directive §20) — none currently hit

Resume unless one of these occurs: prod data loss risk · irreversible prod migration · missing credentials · ambiguous prod target · secret exposed · critical CVE · legal/copyright block · unverifiable required fact · repo corruption · resource exhaustion · ambiguous uncommitted-change ownership · destructive-only path.

---

## Changelog (brief)

- **2026-07-25 Phase 0:** Repaired `sync-from-static.py` (SCRIPT_DIR/PROJECT_ROOT/STATIC_ROOT/THEME_ROOT via `parents[1]`), added `--dry-run` + path guardrails + `--theme-root`, verified via temp-theme corruption test + PHP lint (16/16) + guarded real run (0 changes = parity). Updated `_tooling/README.md`, `DEPLOY.md`. Rollback: `.bak-phase0`.
- **2026-07-25 (earlier):** Flattened nested `kohandezh.com/kohandezh.com/` → repo root; removed 8888 Docker (containers + volumes); grouped tooling under `_tooling/`; deleted 3 zips + `_archive/` + `_remote-archive/` (1.8GB→244MB).
