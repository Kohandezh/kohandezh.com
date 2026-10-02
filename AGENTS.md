# AGENTS.md

Read `README.md` for the folder structure and `CLAUDE.md` for the project rules
and known pitfalls — those rules apply to every agent, not only Claude.

## Who works where

- `Books/ics-cybersecurity/` — **Codex**. Start from `Books/ics-cybersecurity/README.md`.
- Everything else (site, theme, plugins, `Books/ai-governance/`, tooling) — Claude.
  Do not change it unless the owner asks.
- Every book folder follows `Books/BOOK-FORMAT.md` (check: `python3 _tooling/ai-book/validate_book.py --root Books/<slug>`).

## Rules that matter most

- No handovers, status files, progress logs or reports in the repository. Put
  them in `/Users/emperor/Documents/AI/kohandezh-archive/`.
- Names: English, lowercase, kebab-case; numeric prefixes for ordered folders.
- Never commit `.env`, keys, `state/`, raw sources, binaries or virtualenvs —
  the `Books/**` rules in `.gitignore` enforce it; do not override them.
- Before committing: `npm test` and `npm run test:ai-book` must pass.
