# kohandezh.com

Personal site of Mohammad Ali Kohandezh: a static source site, the WordPress
theme and plugins generated or maintained from it, and the sources of the
books published on it.

## Structure

```
kohandezh.com/
├── index.html, fa.html … ru.html   the 10 CV pages (source of truth for content)
├── Certificates.html, PSN.html,    standalone pages
│   knowledge.html, privacy.html,
│   terms.html, videos.html, 404*.html
├── blog/, portfolio/               static blog posts and portfolio
├── assets/                         css/ js/ fonts/ images/ media/ data/ kohan/ contact/ icon/
├── llms.txt, *-llms.txt, robots.txt, sitemap.xml, feed.xml,
│   manifest.json, offline.html, sw.js, .htaccess    site-root files
├── Books/                          book sources, one folder per title; bundle contract: Books/BOOK-FORMAT.md
│   ├── ai-governance/              AI Governance (Persian edition) — pipeline, canonical bundle, tools
│   └── ics-cybersecurity/          Cybersecurity in Industrial Automation (IEC 62443) — see its README
├── _tooling/
│   ├── wp-theme/                   kohandezhcv theme (generated) + plugins:
│   │                               kohan-avatar, kohandezh-knowledge (books reader), kdcv-rightclick-guard …
│   │                               and the upload packages (*.zip, not in git)
│   ├── ai-book/                    book-page generator, design preview (preview/), data-zip builder
│   ├── waiting/                    the MK "Waiting" page loader (stamped into every page)
│   ├── cv/, llms/, tests/, wp-root/, wp-local/ …
│   └── release.py                  root-file package + manifest
├── CLAUDE.md                       project rules and known pitfalls (apply to every contributor)
├── AGENTS.md                       entry point for coding agents
└── README.md                       this file
```

## Naming

- New files and folders: English, lowercase, kebab-case (`chapter-06-revised.md`).
  Ordered folders take a numeric prefix (`01-Manuscript`). Persian goes inside files, not in names.
- Kept on purpose: `PSN.html` and `Certificates.html` (their URLs are indexed and redirected),
  vendor font and library names, the official NIST source PDFs and the Persian Word manuscripts
  (outside git; scripts read them by name).

## Build, test, package

```bash
./build.sh                                        # after any JS/CSS source change
python3 _tooling/wp-theme/sync-from-static.py     # static -> WordPress theme + theme zip
python3 _tooling/waiting/build.py                 # re-stamp the page loader
npm test && npm run test:ai-book                  # every suite; book tests read Books/ai-governance
python3 _tooling/ai-book/build_data_zip.py        # book data package for the server
python3 _tooling/release.py prepare               # root-file package
```

Upload packages land in `_tooling/wp-theme/`: `kohandezhcv.zip` (theme),
`kohandezh-knowledge.zip`, `kohan-avatar.zip` (plugins), `ai-book-data.zip`
(extract into the server's `Books/`), `root-files.zip` (site root; `sw.js` is
served by the theme and is not in it).

## Not in this repository

Session notes, handovers, status reports and work logs are kept outside the
repo, in `/Users/emperor/Documents/AI/kohandezh-archive/`. Secrets, raw
sources, binaries and environments under `Books/` stay on disk only
(`.gitignore`).
