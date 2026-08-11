# Rollback Plan — Kohandezh.com Knowledge Platform

> Every change is reversible. Layer B is feature-flagged + plugin-isolated, so rollback is typically "disable the plugin". Layer A rollback re-runs sync from the static source of truth. Aligned with Agent.md addendum §22.

## 1. Layer B rollback (the common case)

Layer B lives entirely in the `kohandezh-knowledge` plugin. To roll back:

1. **Deactivate the plugin** (wp-admin → Plugins, or `wp plugin deactivate kohandezh-knowledge`). All Layer B CPTs/taxonomies/REST routes/templates disappear; Layer A is untouched.
2. If a single subsystem is at fault, disable just its flag (`wp option update kdcv_kb_news_enabled 0`) instead of the whole plugin.
3. Layer B DB tables (CPT posts, `kdcv_claims`, `kdcv_graph`) remain in place for re-enablement — no data loss.

**Zero-Layer-A-impact guarantee** is verifiable: after disable, re-run Tier 2 tests (TESTING.md) — all must pass identical to pre-Layer-B baseline.

## 2. Layer A rollback (sync-related)

The static source is canonical; a bad theme state is fixed by regenerating:

```bash
# 1. dry-run to see the delta
python3 _tooling/wp-theme/sync-from-static.py --dry-run

# 2. regenerate the theme from static (restores parity)
python3 _tooling/wp-theme/sync-from-static.py
```

- Generated templates are reproducible — re-sync always restores them.
- Hand-maintained files (`functions.php`, `home.php`, `single.php`, `index.php`) are never touched by sync, so a sync cannot corrupt them.

## 3. Script rollback

`_tooling/wp-theme/sync-from-static.py.bak-phase0` is the pre-Phase-0 copy.
```bash
cp _tooling/wp-theme/sync-from-static.py.bak-phase0 _tooling/wp-theme/sync-from-static.py
```
(The repaired version is strongly preferred; the backup is a last resort.)

## 4. Theme content rollback

The theme has its own git history at `_tooling/wp-theme/kohandezhcv/.git`:
```bash
git -C _tooling/wp-theme/kohandezhcv log --oneline
git -C _tooling/wp-theme/kohandezhcv checkout <commit> -- <file>
```

## 5. Production rollback

- **Backup first** (G6). If a deploy misbehaves: deactivate `kohandezh-knowledge` plugin on prod → Layer B vanishes → Layer A continues serving.
- Restore previous theme zip (`kohandezhcv.zip`) if a Layer A regression slipped through.
- Restore DB from backup only if a destructive migration occurred (should not happen — destructive migrations are gated out).

## 6. Rollback testing

Before any production deploy, prove rollback works:
- Locally: activate plugin → add a Layer B entity → deactivate → confirm entity hidden, Layer A Tier 2 tests still pass.
- Confirm re-activate restores visibility (data persisted).

## 7. Things that are NOT easily reversible (avoid)
- Changing an indexed Layer A permalink.
- A destructive DB migration.
- Replacing the homepage.
These are **forbidden** by policy (Agent.md addendum §4) and gated out (DEPLOYMENT_GATES G6).
