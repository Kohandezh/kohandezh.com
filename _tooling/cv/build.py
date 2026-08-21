#!/usr/bin/env python3
"""
Render the canonical CV dataset into the ten static locale files.

WHY THIS EXISTS
---------------
Before Phase 7 the site had three competing factual sources for one career:

  1. static HTML          11 entries in en/ru, 5-6 elsewhere
  2. resume-timeline.js   16 entries, all locales, DIFFERENT job titles
  3. kdcv-resume-entry-fix.js  one more role, injected at runtime

They disagreed. A crawler read (1); a visitor read (2) after the script
deleted (1) and rebuilt it; and (3) patched the result. Four job titles
contradicted between the versions a machine and a human saw.

Now `assets/data/cv.json` is the single truth and this script renders it. The
runtime script enhances what is already in the HTML instead of replacing it.

Idempotent: rewrites only the block between the marker comments, so hand edits
elsewhere in the page survive.

    python3 _tooling/cv/build.py [--check]

--check exits non-zero if any file is out of date, without writing.
"""

import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DATA = os.path.join(ROOT, 'assets', 'data', 'cv.json')

FILES = {
    'index.html': 'en', 'fa.html': 'fa', 'ar.html': 'ar', 'de.html': 'de',
    'es.html': 'es', 'fr.html': 'fr', 'tr.html': 'tr', 'zh.html': 'zh',
    'ja.html': 'ja', 'ru.html': 'ru',
}

BEGIN = '<!-- CV:BEGIN generated from assets/data/cv.json by _tooling/cv/build.py -- do not edit by hand -->'
END = '<!-- CV:END -->'

ICON = {'work': 'briefcase', 'education': 'edu-2'}


def render(entries, locale, indent='                                    '):
    """One timeline item per canonical entry, in canonical order."""
    out = [BEGIN]
    for e in entries:
        loc = e['locales'][locale]
        icon = ICON.get(e['type'], 'briefcase')
        # data-cv-id is the join key: the runtime enhancer matches on it rather
        # than on array position, so reordering the dataset cannot silently
        # attach an employer logo to the wrong job.
        out.append(
            f'{indent}<div class="timeline-item effectFade fadeUp no-div" '
            f'data-cv-id="{html.escape(e["id"])}" data-cv-type="{e["type"]}">'
        )
        out.append(f'{indent}    <p class="timeline-date text-black-56">{html.escape(loc["date"])}</p>')
        out.append(f'{indent}    <div class="timeline-dot"></div>')
        out.append(f'{indent}    <div class="timeline-content">')
        out.append(f'{indent}        <div class="icon">')
        out.append(
            f'{indent}            <img class="image-switch" data-dark="assets/images/item/{icon}_dark.svg?v=2" '
            f'width="29" height="32" src="assets/images/item/{icon}.svg?v=2" alt="" loading="lazy" decoding="async">'
        )
        out.append(f'{indent}        </div>')
        out.append(f'{indent}        <div class="content">')
        out.append(f'{indent}            <p class="timeline-role fw-6">{html.escape(loc["title"])}</p>')
        if loc.get('desc'):
            out.append(f'{indent}            <p class="timeline-desc text-black-56">{html.escape(loc["desc"])}</p>')
        out.append(f'{indent}        </div>')
        out.append(f'{indent}    </div>')
        out.append(f'{indent}</div>')
    out.append(indent + END)
    return '\n'.join(out)


def apply(path, locale, entries, check=False):
    with open(path, encoding='utf-8') as fh:
        src = fh.read()

    block = render(entries, locale)

    if BEGIN in src:
        pattern = re.compile(re.escape(BEGIN) + r'.*?' + re.escape(END), re.S)
        new = pattern.sub(lambda _m: block.strip(), src, count=1)
    else:
        # First run: replace the hand-written run of .timeline-item divs.
        items = list(re.finditer(r'[ \t]*<div class="timeline-item\b', src))
        if not items:
            return None
        start = items[0].start()
        # Walk to the end of the last item by brace-free div balancing.
        end = start
        for m in items:
            depth, i = 0, m.start()
            while i < len(src):
                if src.startswith('<div', i):
                    depth += 1
                    i += 4
                elif src.startswith('</div>', i):
                    depth -= 1
                    i += 6
                    if depth == 0:
                        break
                else:
                    i += 1
            end = max(end, i)
        new = src[:start] + block + src[end:]

    if new == src:
        return False
    if not check:
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write(new)
    return True


def main():
    check = '--check' in sys.argv
    doc = json.load(open(DATA, encoding='utf-8'))
    entries = doc['entries']

    stale = []
    for name, locale in FILES.items():
        path = os.path.join(ROOT, name)
        if not os.path.exists(path):
            continue
        changed = apply(path, locale, entries, check)
        if changed is None:
            print(f'  !! {name}: no timeline block found')
            continue
        if changed:
            stale.append(name)
        print(f'  {"stale" if (changed and check) else "ok   "} {name} ({locale}) -> {len(entries)} entries')

    if check and stale:
        print(f'\nOut of date: {", ".join(stale)}\nRun: python3 _tooling/cv/build.py')
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
