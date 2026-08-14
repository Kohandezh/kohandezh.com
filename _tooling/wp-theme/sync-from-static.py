#!/usr/bin/env python3
"""
Single source of truth: the static site at the project root is canonical.
This script regenerates the entire wp-theme/kohandezhcv/ tree from it —
assets AND page templates — so the two can never silently drift again
(that's exactly how the 2026-07-11 stale-library bug happened: one tree
got patched, the other didn't, because nothing enforced parity).

Run this after ANY edit to the static site (project root) that should also reach the WP
theme: content changes, CSS/JS/font/image updates, new language pages,
security or performance fixes.

Usage (from project root):
    python3 _tooling/wp-theme/sync-from-static.py              # sync + rebuild zip
    python3 _tooling/wp-theme/sync-from-static.py --no-zip     # sync only
    python3 _tooling/wp-theme/sync-from-static.py --dry-run    # report planned changes, no writes
    python3 _tooling/wp-theme/sync-from-static.py --theme-root /tmp/kohandezhcv-test --no-zip  # redirect dest (controlled tests)

What this does NOT touch (WP-native, no static equivalent, hand-maintained):
    functions.php, home.php (blog index), single.php (blog post), index.php
"""
import argparse
import html as html_lib
import re
import shutil
import subprocess
import sys
from pathlib import Path

# All paths derive from the script's own location (cwd-independent).
# Script lives at <project_root>/_tooling/wp-theme/sync-from-static.py, so:
#   SCRIPT_DIR.parents[0] = <project_root>/_tooling
#   SCRIPT_DIR.parents[1] = <project_root>   (= the static site, now flat)
SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parents[1]
STATIC_ROOT = PROJECT_ROOT
THEME_ROOT = SCRIPT_DIR / "kohandezhcv"

KDCV = "<?php echo KDCV; ?>"
HOME = "<?php echo esc_url( home_url('/') ); ?>"

LANGS = ["fa", "ar", "de", "es", "fr", "tr", "zh", "ja", "ru"]

# static filename -> (wp template filename, url slug or None for front page)
PAGE_MAP = {
    "index.html": ("front-page.php", None),
    **{f"{lang}.html": (f"page-{lang}.php", lang) for lang in LANGS},
    "PSN.html": ("page-psn.php", "psn"),
    "Certificates.html": ("page-certificates.php", "certificates"),
    # Photography page removed 2026-07-19; the old
    # rewrite rules below are kept harmless no-ops for any stale content.
    "portfolio/index.html": ("page-portfolio.php", "portfolio"),
    # The legal pages had no WP template at all — they existed only on the
    # static build, so the footer's Privacy/Terms links 404'd on production.
    "knowledge.html": ("page-knowledge.php", "knowledge"),
    "privacy.html": ("page-privacy.php", "privacy"),
    "terms.html": ("page-terms.php", "terms"),
    # WP auto-loads the theme's 404.php for every not-found URL (no page slug
    # needed, no registration in functions.php). The transform rewrites the
    # static 404.html asset paths to the theme base and injects the WP hooks,
    # so production 404s get the same random-game arcade as the static site.
    # Slug "__404__" is a sentinel: it is neither None nor in LANGS, so
    # has_home_blog resolves to False (404.html has no blog feed / locale
    # router, and those assertions are correctly skipped).
    "404.html": ("404.php", "__404__"),
}

# Assets that exist ONLY in the WP theme (no static-site source, so the
# assets rsync mirror must not delete them even though they're absent from
# kohandezh.com/assets/). Paths are relative to the assets/ dir itself
# (rsync runs with assets/ as both source and dest root — NOT "assets/js/…").
# Add here if a future WP-only script/style appears.
WP_ONLY_ASSETS = [
    "js/home-blog-scroll.js",
]

# The reverse case: assets that exist only for the STATIC blog (blog/*.html
# posts embed these directly by path) and aren't referenced by any WP
# template — WP posts carry their own images/video via the Media Library
# instead. Skipping them keeps kohandezhcv.zip from carrying a multi-megabyte video
# and blog header images no PHP file ever points at.
STATIC_ONLY_ASSETS = [
    # whole media dir: videos/audio for the static videos.html page; WP uses
    # its Media Library and no theme template references assets/media/
    "media/",
    "media-library/",
    "docs/",
    # Source art for the Kohan avatar sprite frames (~8 MB of layered PNGs).
    # Runtime only ever loads the generated kohan/supplemental/*.webp frames —
    # nothing references supplemental-source/. Shipping it pushed
    # kohandezhcv.zip over the 32 MB upload_max_filesize most shared hosts
    # enforce, which made the theme impossible to install from wp-admin.
    "kohan/supplemental-source/",
    # Source original for the sako portfolio art. The pages reference only the
    # 168 KB .webp beside it; this 2.2 MB lossless PNG is the master kept in
    # the repo and must not ship — it alone grew the theme zip by 9%.
    "images/portfolio/sako-platform-concept.png",
]

# The static site's home-page blog preview is a client-side fetch of
# blog/index.html (assets/js/home-blog-feed.js). WordPress has no such file —
# posts live in the DB — so on WP the same "Blog and News" section is instead
# powered by a real WP_Query render (see kdcv_render_home_blog_feed() in
# functions.php) plus a REST-API "load 6 more" scroll script. This has no
# static-HTML equivalent, so it's patched in after the generic transform
# rather than sourced from kohandezh.com/*.html. Applies to every CV-type
# page (front page + all language pages), not PSN/Certificates/Photography.
STATIC_HOME_BLOG_SCRIPT = re.compile(
    # matches both home-blog-feed.js and home-blog-feed.min.js (a background
    # minification pipeline rewrites the static pages to .min.js references)
    r'\n\s*<script src="[^"]*assets/js/home-blog-feed(?:\.min)?\.js\?v=\d+" defer></script>'
)
WP_HOME_BLOG_SCRIPTS = (
    '\n    <script>window.KDCV_CONFIG = {{ assetBase: "{kdcv}/", '
    "certificatesUrl: \"<?php echo esc_url( home_url('/certificates/') ); ?>\", "
    "restPostsUrl: \"<?php echo esc_url( rest_url('wp/v2/posts') ); ?>\", "
    "askUrl: \"<?php echo esc_url( rest_url('kdcv/v1/ask') ); ?>\" "
    "}};</script>"
    '\n    <script src="{kdcv}/assets/js/home-blog-scroll.js?v=2" defer></script>'
).format(kdcv=KDCV)


def page_url(slug: str) -> str:
    return f"<?php echo esc_url( home_url('/{slug}/') ); ?>"


def fail(message: str) -> None:
    raise RuntimeError(message)


def replace_home_blog_list(source: str, note: str) -> str:
    """Replace one nested static card list with the server-rendered WP feed."""
    openings = list(re.finditer(
        r'<div\b[^>]*class="[^"]*\bblog-local-list\b[^"]*"[^>]*>',
        source,
        flags=re.IGNORECASE,
    ))
    if len(openings) != 1:
        fail(f"[{note}] expected exactly 1 .blog-local-list, found {len(openings)}")

    opening = openings[0]
    depth = 1
    closing_start = closing_end = None
    for token in re.finditer(r'<div\b[^>]*>|</div\s*>', source[opening.end():], flags=re.IGNORECASE):
        if token.group(0).lower().startswith("</div"):
            depth -= 1
        else:
            depth += 1
        if depth == 0:
            closing_start = opening.end() + token.start()
            closing_end = opening.end() + token.end()
            break
    if closing_start is None or closing_end is None:
        fail(f"[{note}] could not find the closing tag for .blog-local-list")

    inner = source[opening.end():closing_start]
    link_matches = re.findall(
        r'<a\b[^>]*class="[^"]*\bblog-local-link\b[^"]*"[^>]*>(.*?)</a>',
        inner,
        flags=re.IGNORECASE | re.DOTALL,
    )
    if not link_matches:
        fail(f"[{note}] could not derive the localized blog read label")
    read_label = html_lib.unescape(re.sub(r'<[^>]+>', ' ', link_matches[0]))
    read_label = re.sub(r'\s+', ' ', read_label).strip()
    if not read_label:
        fail(f"[{note}] localized blog read label is empty")

    attr_label = html_lib.escape(read_label, quote=True)
    php_label = read_label.replace('\\', '\\\\').replace("'", "\\'")
    open_tag = opening.group(0)[:-1]
    replacement = (
        f'{open_tag} data-kdcv-blog-feed '
        'data-loaded="<?php echo esc_attr( min( 6, (int) wp_count_posts( \'post\' )->publish ) ); ?>" '
        f'data-read-label="{attr_label}">\n'
        f"                                    <?php echo kdcv_render_home_blog_feed( '{php_label}', 6 ); ?>\n"
        '                                </div>'
    )
    return source[:opening.start()] + replacement + source[closing_end:]


def sync_assets(static_root: Path, theme_root: Path):
    print("== syncing assets/ (static -> WP theme, exact mirror) ==")
    excludes = [f"--exclude={rel}" for rel in (*WP_ONLY_ASSETS, *STATIC_ONLY_ASSETS)]
    subprocess.run(
        ["rsync", "-a", "--delete", *excludes, f"{static_root}/assets/", f"{theme_root}/assets/"],
        check=True,
    )
    for rel in WP_ONLY_ASSETS:
        if not (theme_root / "assets" / rel).exists():
            fail(f"WP-only asset missing after sync: {rel}")
    copy_import_media(static_root, theme_root)
    rewrite_post_body_urls(theme_root)
    copy_llms_files(static_root, theme_root)


# functions.php serves /{locale}-llms.txt with a rewrite rule, falling back to
# a copy bundled in the theme when the site root has no static upload. That
# fallback only works if the copies are actually IN the theme — they were not,
# so on a WordPress-only deploy every one of these 404'd. They ride along now.
LLMS_LOCALES = ["", "fa-", "ar-", "de-", "es-", "fr-", "tr-", "zh-", "ja-", "ru-"]


def copy_llms_files(static_root: Path, theme_root: Path):
    copied = 0
    for prefix in LLMS_LOCALES:
        name = f"{prefix}llms.txt"
        src = static_root / name
        if not src.is_file():
            fail(f"llms file missing from static site: {name}")
        shutil.copy2(src, theme_root / name)
        copied += 1
    print(f"  + {copied} llms.txt files bundled into the theme")


# The whole-article translations (`__body`) are composed from the STATIC blog
# files, so every asset URL inside them is written relative to /blog/ — e.g.
# "../assets/images/blog/x.webp". On WordPress a post lives at a pretty slug,
# so that path resolves to nowhere and every in-article image 404s. The theme
# copy is rewritten to the theme-relative path the rest of the WP templates
# use. Only the theme's copy is touched; the static dictionaries keep the
# relative paths their own pages need.
POST_BODY_PREFIX = "/wp-content/themes/kohandezhcv/assets/"


def rewrite_post_body_urls(theme_root: Path):
    import json as _json
    i18n = theme_root / "assets" / "data" / "i18n"
    if not i18n.is_dir():
        return
    touched = 0
    for path in sorted(i18n.glob("post-*.json")):
        data = _json.loads(path.read_text())
        changed = False
        for loc, entries in data.items():
            body = entries.get("__body")
            if not body or "../assets/" not in body:
                continue
            entries["__body"] = body.replace("../assets/", POST_BODY_PREFIX)
            changed = True
        if changed:
            path.write_text(_json.dumps(data, ensure_ascii=False, indent=2))
            touched += 1
    if touched:
        print(f"  ~ rewrote in-article asset URLs in {touched} post dictionaries")


# Media embedded inside blog posts. The WordPress importer fetches attachments
# over HTTP, so these must resolve to a real URL on the live site or every
# attachment fails with "Failed to import Media". Nothing serves /assets/ at the
# WP domain root, so they ride along inside the theme and the WXR points at
# .../wp-content/themes/kohandezhcv/assets/... . Only the files actually
# referenced by a post are copied — assets/media/ as a whole is ~40 MB.
IMPORT_MEDIA = [
    "media/phase11-award.mp4",
]


def copy_import_media(static_root: Path, theme_root: Path):
    for rel in IMPORT_MEDIA:
        src = static_root / "assets" / rel
        if not src.is_file():
            fail(f"import media missing from static site: assets/{rel}")
        dest = theme_root / "assets" / rel
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dest)
        print(f"  + import media: assets/{rel}")


def transform(html: str, note: str, has_home_blog: bool) -> str:
    s = html
    s = s.replace(
        'data-kdcv-router-mode="static"',
        'data-kdcv-router-mode="wordpress" data-kdcv-site-base="<?php echo esc_url(home_url(\'/\')); ?>"',
    )

    if has_home_blog:
        s, n = STATIC_HOME_BLOG_SCRIPT.subn(WP_HOME_BLOG_SCRIPTS, s)
        if n != 1:
            fail(f"[{note}] expected 1 home-blog-feed.js script tag, found {n}")
        s = replace_home_blog_list(s, note)
        # The current static build defers AI Pet through lazy-bundle. Older
        # builds included ai-pet directly. Accept exactly one supported loader,
        # but never both, so the widget cannot bootstrap twice.
        direct_pet_count = (
            s.count('assets/js/ai-pet.js') +
            s.count('assets/js/ai-pet.min.js')
        )
        lazy_pet_count = (
            s.count('assets/js/lazy-bundle.js') +
            s.count('assets/js/lazy-bundle.min.js')
        )
        pet_loader_count = direct_pet_count + lazy_pet_count
        if pet_loader_count != 1:
            fail(
                f"[{note}] expected exactly 1 AI Pet loader, "
                f"found direct={direct_pet_count}, lazy={lazy_pet_count}"
            )
        router_count = s.count('assets/js/locale-router.js') + s.count('assets/js/locale-router.min.js')
        if router_count != 1:
            fail(f"[{note}] expected exactly 1 locale router, found {router_count}")
        if s.count('data-kdcv-router-mode="wordpress"') != 1:
            fail(f"[{note}] WordPress locale-router mode assertion failed")

    # cross-language + standalone page URLs -> pretty WP slugs
    for lang in LANGS:
        s = s.replace(f"https://kohandezh.com/{lang}.html", f"https://kohandezh.com/{lang}/")
    s = s.replace("https://kohandezh.com/PSN.html", "https://kohandezh.com/psn/")
    s = s.replace("https://kohandezh.com/Certificates.html", "https://kohandezh.com/certificates/")
    s = s.replace("https://kohandezh.com/Photography.html", "https://kohandezh.com/photography/")
    s = s.replace("https://kohandezh.com/portfolio/index.html", "https://kohandezh.com/portfolio/")
    s = s.replace("https://kohandezh.com/privacy.html", "https://kohandezh.com/privacy/")
    s = s.replace("https://kohandezh.com/terms.html", "https://kohandezh.com/terms/")
    s = s.replace("https://kohandezh.com/knowledge.html", "https://kohandezh.com/knowledge/")

    # The legal pages now have real WP templates and slugs. Their relative
    # links ("privacy.html") resolve against the current pretty permalink, so
    # from /psn/ they became /psn/privacy.html and 404'd.
    for name, slug in (("privacy", "privacy"), ("terms", "terms")):
        s = s.replace(f'href="{name}.html"', f'href="{page_url(slug)}"')
        s = s.replace(f'href="../{name}.html"', f'href="{page_url(slug)}"')

    # WordPress replaces the static preview cards with a native WP_Query feed.
    s = s.replace('href="blog/"', f'href="{page_url("blog")}"')

    # same-site page links -> WP URLs
    for locale in ["en", *LANGS]:
        s = s.replace(
            f'"Certificates.html?lang={locale}"',
            f'"{page_url("certificates")}?lang={locale}"',
        )
        s = s.replace(
            f'"Photography.html?lang={locale}"',
            f'"{page_url("photography")}?lang={locale}"',
        )
        s = s.replace(
            f'"knowledge.html?lang={locale}"',
            f'"{page_url("knowledge")}?lang={locale}"',
        )
    # videos.html has no WP template: its ~40 MB of media lives in
    # assets/media/, which STATIC_ONLY_ASSETS deliberately keeps out of the
    # theme. The page therefore stays a real static file uploaded next to
    # wp-config.php; WordPress serves existing files directly without routing
    # them, so an absolute site URL resolves. Left unrewritten these stayed
    # relative ("videos.html?lang=fa") and 404'd from every pretty permalink.
    for locale in ["en", *LANGS]:
        s = s.replace(
            f'"videos.html?lang={locale}"',
            f'"{HOME}videos.html?lang={locale}"',
        )
    s = s.replace('"videos.html"', f'"{HOME}videos.html"')

    for lang in LANGS:
        s = s.replace(f'"{lang}.html"', f'"{page_url(lang)}"')
    s = s.replace('"PSN.html"', f'"{page_url("psn")}"')
    s = s.replace('"Certificates.html"', f'"{page_url("certificates")}"')
    s = s.replace('"Photography.html"', f'"{page_url("photography")}"')
    s = s.replace('"knowledge.html"', f'"{page_url("knowledge")}"')
    s = s.replace('"portfolio/index.html"', f'"{page_url("portfolio")}"')
    s = s.replace('"portfolio/?lang=', f'"{page_url("portfolio")}?lang=')
    s = s.replace('"index.html"', f'"{HOME}"')

    # theme asset base (relative + absolute forms)
    s = s.replace('"assets/', f'"{KDCV}/assets/')
    s = s.replace('"/assets/', f'"{KDCV}/assets/')
    s = s.replace("https://kohandezh.com/assets/", f"{KDCV}/assets/")

    # ---- strip the Web3Forms access key from the generated templates --------
    # The static build has no server, so the key has to stay in those files for
    # the forms to deliver at all. WordPress does have one: functions.php holds
    # the key in the `kdcv_contact_access_key` option and contact-forms.js posts
    # to /wp-json/kohandezh/v1/contact instead. Leaving the hidden input in the
    # template would put the secret back into public page source for nothing.
    s = re.sub(r'[ \t]*<input type="hidden" name="access_key"[^>]*>\n?', "", s)
    if 'name="access_key"' in s:
        fail(f"[{note}] an access_key input survived the strip")


    # WP hooks — every generated document must have exactly one insertion point.
    if s.count("</head>") != 1 or s.count("</body>") != 1:
        fail(f"[{note}] expected one </head> and one </body>")
    if len(re.findall(r"<body[^>]*>", s)) != 1:
        fail(f"[{note}] expected exactly one <body> element")
    s = s.replace("</head>", f"    <?php wp_head(); ?>\n</head>", 1)
    s = re.sub(r"(<body[^>]*>)", r"\1\n<?php wp_body_open(); ?>", s, count=1)
    s = s.replace("</body>", "    <?php wp_footer(); ?>\n</body>", 1)

    if has_home_blog:
        if s.count("data-kdcv-blog-feed") != 1 or s.count("kdcv_render_home_blog_feed") != 1:
            fail(f"[{note}] generated WordPress blog feed assertion failed")
        if s.count("window.KDCV_CONFIG") != 1 or s.count("home-blog-scroll.js") != 1:
            fail(f"[{note}] generated WordPress blog loader assertion failed")

    return f"<?php /* KohandezhCV — {note} (generated by sync-from-static.py, do not hand-edit) */ ?>\n" + s


def page_note(slug):
    if slug == "__404__":
        return "404 error page (auto-loaded by WordPress for any not-found URL)"
    return (
        "front page (English CV)" if slug is None else
        f"CV page, auto-applied to page slug '{slug}'" if slug in LANGS else
        f"{slug} page"
    )


def sync_pages(static_root: Path, theme_root: Path):
    print("== regenerating WP page templates from static HTML ==")
    missing = [name for name in PAGE_MAP if not (static_root / name).is_file()]
    if missing:
        fail("required static source files missing: " + ", ".join(missing))

    for static_name, (php_name, slug) in PAGE_MAP.items():
        src = static_root / static_name
        note = page_note(slug)
        has_home_blog = slug is None or slug in LANGS  # every CV-type page has the "Blog and News" section
        out = transform(src.read_text(encoding="utf-8"), note, has_home_blog)
        (theme_root / php_name).write_text(out, encoding="utf-8")
        print(f"  {static_name:24} -> {php_name}")


def rebuild_zip(theme_root: Path):
    print("== rebuilding kohandezhcv.zip ==")
    zip_path = theme_root.parent / "kohandezhcv.zip"
    zip_path.unlink(missing_ok=True)
    # Everything excluded here was checked against a corpus of every .php/.css/
    # .js/.json/.xml/.txt/.html in the theme AND against the blog import .wxr —
    # nothing in the installed site resolves to any of these paths. Files that
    # LOOKED orphaned but are not were deliberately left in:
    #   assets/media/phase11-award.mp4  — the imported blog post links to it at
    #       /wp-content/themes/kohandezhcv/assets/media/, so removing it would
    #       break the video on that post;
    #   assets/contact/*.pdf            — the designed CVs are deliverables even
    #       though only the ATS set is currently linked from a button;
    #   assets/kohan/spritesheet.webp   — the avatar's only runtime sheet.
    subprocess.run(
        ["zip", "-qr", str(zip_path), theme_root.name, "-x",
         "*.DS_Store", "*/.git/*", "*/.git", "*/tmp-blog-import/*",
         # Never ship the avatar source art. rsync --exclude also protects an
         # already-synced copy from --delete, so a stale supplemental-source/
         # can linger in the theme dir; excluding it here keeps it out of the
         # installable zip regardless of what is on disk.
         "*/kohan/supplemental-source/*",
         # Per-mood sheets belong to the kohan-avatar PLUGIN, which ships its
         # own copy. The theme's avatar loads only spritesheet.webp.
         "*/kohan/avatar-sheets/*",
         # Superseded by gsap-bundle.min.js (three requests collapsed to one).
         "*/assets/js/gsap.min.js",
         # Authoring notes and a reference implementation — not runtime code.
         "*/kohan/MOODS.md", "*/kohan/*.reference.js",
         ],
        cwd=theme_root.parent, check=True,
    )
    size_mb = zip_path.stat().st_size / 1024 / 1024
    print(f"  {zip_path} ({size_mb:.1f} MB)")


def print_resolved_paths(project_root: Path, static_root: Path, theme_root: Path):
    print("─── resolved paths ───")
    print(f"  project root : {project_root}")
    print(f"  static root  : {static_root}")
    print(f"  theme root   : {theme_root}")
    print()


def validate_paths(static_root: Path, theme_root: Path, allow_override: bool):
    home = Path.home()
    errors = []
    if not (static_root / "index.html").is_file():
        errors.append(f"missing source: {static_root / 'index.html'}")
    if not (static_root / "assets").is_dir():
        errors.append(f"missing source dir: {static_root / 'assets'}")
    if not theme_root.is_dir():
        errors.append(f"theme destination does not exist: {theme_root}")
    if static_root.resolve() == theme_root.resolve():
        errors.append("STATIC_ROOT == THEME_ROOT (refusing to mirror onto the source tree)")
    for label, p in (("STATIC_ROOT", static_root), ("THEME_ROOT", theme_root)):
        rp = p.resolve()
        if rp == Path("/"):
            errors.append(f"{label} resolves to /")
        if rp == home:
            errors.append(f"{label} resolves to home directory {home}")
    if not allow_override:
        try:
            theme_root.resolve().relative_to(SCRIPT_DIR)
        except ValueError:
            errors.append(f"THEME_ROOT not inside {SCRIPT_DIR} (use --theme-root to override)")
    if theme_root.is_dir() and not (theme_root / "style.css").is_file() \
            and not (theme_root / "functions.php").is_file():
        errors.append(f"{theme_root} has neither style.css nor functions.php (not a WP theme?)")
    if errors:
        print("PATH VALIDATION FAILED:", file=sys.stderr)
        for e in errors:
            print(f"  - {e}", file=sys.stderr)
        sys.exit(1)


def count_source_asset_files(static_root: Path) -> int:
    assets = static_root / "assets"
    if not assets.is_dir():
        return 0
    excluded = [*WP_ONLY_ASSETS, *STATIC_ONLY_ASSETS]
    n = 0
    for p in assets.rglob("*"):
        if not p.is_file():
            continue
        rel = p.relative_to(assets).as_posix()
        if any(rel == ex or rel.startswith(ex.rstrip("/") + "/") for ex in excluded):
            continue
        n += 1
    return n


def rsync_dry_run(static_root: Path, theme_root: Path):
    excludes = [f"--exclude={rel}" for rel in (*WP_ONLY_ASSETS, *STATIC_ONLY_ASSETS)]
    cmd = ["rsync", "-a", "--dry-run", "--delete", "-i", "--out-format=%i|%n",
           *excludes, f"{static_root}/assets/", f"{theme_root}/assets/"]
    res = subprocess.run(cmd, capture_output=True, text=True)
    creates, updates, deletes = [], [], []
    for line in res.stdout.splitlines():
        line = line.strip()
        if "|" not in line:
            continue
        code, name = line.split("|", 1)
        if code.startswith("*deleting"):
            deletes.append(name)
        elif code.startswith(">f") or code.startswith("<f"):
            if (theme_root / "assets" / name).exists():
                # WordPress post dictionaries intentionally rewrite relative
                # article asset URLs after rsync. Compare against that expected
                # transformed form so a completed sync reports true parity.
                src_path = static_root / "assets" / name
                dst_path = theme_root / "assets" / name
                expected_transform = name.startswith("data/i18n/post-") and name.endswith(".json")
                if expected_transform:
                    try:
                        import json as _json
                        data = _json.loads(src_path.read_text())
                        for entries in data.values():
                            if isinstance(entries, dict) and entries.get("__body"):
                                entries["__body"] = entries["__body"].replace("../assets/", POST_BODY_PREFIX)
                        expected = _json.dumps(data, ensure_ascii=False, indent=2)
                        if dst_path.read_text() == expected:
                            continue
                    except (OSError, ValueError, TypeError):
                        pass
                updates.append(name)
            else:
                creates.append(name)
    return creates, updates, deletes


def compute_dry_run(static_root: Path, theme_root: Path):
    rep = {
        "page_creates": [], "page_updates": [], "page_unchanged": [],
        "page_missing": [], "page_errors": [],
        "asset_creates": [], "asset_updates": [], "asset_deletes": [], "asset_total": 0,
    }
    for static_name, (php_name, slug) in PAGE_MAP.items():
        src = static_root / static_name
        if not src.is_file():
            rep["page_missing"].append(static_name)
            continue
        has_home_blog = slug is None or slug in LANGS
        try:
            new = transform(src.read_text(encoding="utf-8"), page_note(slug), has_home_blog)
        except Exception as e:
            rep["page_errors"].append(f"{php_name}: {e}")
            continue
        dest = theme_root / php_name
        if not dest.exists():
            rep["page_creates"].append(php_name)
        elif dest.read_text(encoding="utf-8") != new:
            rep["page_updates"].append(php_name)
        else:
            rep["page_unchanged"].append(php_name)
    rep["asset_creates"], rep["asset_updates"], rep["asset_deletes"] = rsync_dry_run(static_root, theme_root)
    rep["asset_total"] = count_source_asset_files(static_root)
    return rep


def print_dry_run_report(rep):
    asset_unchanged = max(0, rep["asset_total"] - len(rep["asset_creates"]) - len(rep["asset_updates"]))
    page_total = sum(len(rep[k]) for k in ("page_creates", "page_updates", "page_unchanged"))
    print("─── dry-run change report (no writes performed) ───")
    print(f"  page templates : {len(rep['page_creates']):>3} create | {len(rep['page_updates']):>3} update | {len(rep['page_unchanged']):>3} unchanged  (of {page_total} mapped)")
    if rep["page_creates"]:
        print(f"    create: {', '.join(rep['page_creates'])}")
    if rep["page_updates"]:
        print(f"    update: {', '.join(rep['page_updates'])}")
    print(f"  assets         : {len(rep['asset_creates']):>3} create | {len(rep['asset_updates']):>3} update | {asset_unchanged:>3} unchanged | {len(rep['asset_deletes']):>3} DELETE  (of {rep['asset_total']} source files)")
    if rep["asset_deletes"]:
        shown = rep["asset_deletes"][:20]
        more = "" if len(rep["asset_deletes"]) <= 20 else f"  (+{len(rep['asset_deletes']) - 20} more)"
        print(f"    DELETE: {', '.join(shown)}{more}")
    total_create = len(rep["page_creates"]) + len(rep["asset_creates"])
    total_update = len(rep["page_updates"]) + len(rep["asset_updates"])
    print(f"  totals         : {total_create:>3} create | {total_update:>3} update | {len(rep['asset_deletes']):>3} delete")
    if rep["page_missing"]:
        print(f"  MISSING source files: {', '.join(rep['page_missing'])}")
    if rep["page_errors"]:
        print("  TRANSFORM ERRORS:")
        for e in rep["page_errors"]:
            print(f"    - {e}")
    warns = []
    if rep["asset_deletes"]:
        warns.append(f"{len(rep['asset_deletes'])} asset deletion(s) pending (rsync --delete exact-mirror)")
    if rep["page_missing"] or rep["page_errors"]:
        warns.append("source/transform problems detected")
    print("  WARNINGS:")
    for w in warns:
        print(f"    - {w}")
    if not warns and total_create == 0 and total_update == 0 and not rep["asset_deletes"]:
        print("    - (none) pipeline is in parity; real run would be a no-op")


def main(argv=None):
    ap = argparse.ArgumentParser(
        description="Regenerate WP theme (kohandezhcv) from the static site (source of truth).")
    ap.add_argument("--dry-run", action="store_true",
                    help="resolve, validate, and report planned changes without writing")
    ap.add_argument("--no-zip", action="store_true", help="skip rebuilding kohandezhcv.zip")
    ap.add_argument("--theme-root", dest="theme_root", metavar="PATH",
                    help="override theme destination (for controlled tests; skips the _tooling/wp-theme membership check)")
    args = ap.parse_args(argv)

    static_root = STATIC_ROOT
    theme_root = THEME_ROOT
    if args.theme_root:
        theme_root = Path(args.theme_root).expanduser().resolve()

    print_resolved_paths(PROJECT_ROOT, static_root, theme_root)
    validate_paths(static_root, theme_root, allow_override=bool(args.theme_root))

    if args.dry_run:
        rep = compute_dry_run(static_root, theme_root)
        print_dry_run_report(rep)
        sys.exit(1 if (rep["page_missing"] or rep["page_errors"]) else 0)

    try:
        sync_assets(static_root, theme_root)
        sync_pages(static_root, theme_root)
        if not args.no_zip:
            rebuild_zip(theme_root)
    except RuntimeError as e:
        print(f"SYNC ABORTED: {e}", file=sys.stderr)
        sys.exit(1)
    print("\ndone — static site is the source of truth, WP theme regenerated to match.")


if __name__ == "__main__":
    main()
