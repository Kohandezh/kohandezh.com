# _tooling/

Non-site folders grouped here so the project root stays clean (= the website itself).
The live dev server (http://localhost:8735/) serves the project root; none of these
folders are referenced by any page on the live site.

## Layout after the Phase 0 restructure

- **Project root** = the static website itself (flat): `index.html`, `*.html`, `assets/`, `blog/`, `portfolio/`, `.htaccess`, etc.
- **Static source of truth** = the project root.
- **WordPress theme** = `_tooling/wp-theme/kohandezhcv/` (generated, do not hand-edit the page templates).

| Folder    | What it is                                                                                                                              |
|-----------|-----------------------------------------------------------------------------------------------------------------------------------------|
| wp-theme/ | WordPress theme generator + the `kohandezhcv/` theme. `sync-from-static.py` regenerates the theme from the static site at the project root. |
| wp-local/ | `docker-compose.yml` for the **retired** local WordPress stack on port 8888 (`kohandezh_wp` + `kohandezh_db`). Containers and volumes were removed — kept for reference only. |
| upload/   | Staging folder (`upload/assets/`). Not referenced by the live site.                                                                     |
| avatar/   | Source Kohan artwork (PNGs + zip) and a README. The live site uses the pre-baked `assets/kohan/` copy.                                  |

## Sync commands (static → WordPress theme guardrail)

Run from the project root after ANY edit to the static site that must reach the WP theme:

```bash
# Preview planned changes — no writes, no deletions:
python3 _tooling/wp-theme/sync-from-static.py --dry-run

# Apply (assets mirror + regenerate page templates + rebuild kohandezhcv.zip):
python3 _tooling/wp-theme/sync-from-static.py

# Apply without rebuilding the zip:
python3 _tooling/wp-theme/sync-from-static.py --no-zip

# Controlled test against a temp copy of the theme (never touches the real theme):
cp -r _tooling/wp-theme/kohandezhcv /tmp/kohandezhcv-test
python3 _tooling/wp-theme/sync-from-static.py --theme-root /tmp/kohandezhcv-test --no-zip
```

The script resolves all paths from its own location, so it works from any working directory.
Asset sync uses `rsync --delete` (exact mirror of `assets/`); the `--dry-run` report lists any
pending deletions so nothing is ever a surprise.

## Rollback

- The pre-repair script is backed up at `_tooling/wp-theme/sync-from-static.py.bak-phase0`.
- Generated page templates are reproducible from the static source — re-running sync restores them.
- Hand-maintained theme files (`functions.php`, `home.php`, `single.php`, `index.php`) are never touched by sync.
- The theme directory has its own git history (`_tooling/wp-theme/kohandezhcv/.git`).

## Obsolete environment: port 8888

The local Docker WordPress stack on port **8888** has been removed (containers `kohandezh_wp`,
`kohandezh_db` and their volumes `wp-local_kohandezh_db_data` / `wp-local_kohandezh_wp_data`).
The canonical local preview is the PHP dev server on **http://localhost:8735/** (`./run-dev.sh`).

## Run the site

```bash
./run-dev.sh     # → http://localhost:8735/
```
