#!/usr/bin/env python3
"""
Render the canonical identity into the ten *-llms.txt files.

WHY THIS EXISTS
---------------
The ten locale files were maintained as ten independent copies, and they did
what independent copies always do:

  * All ten still described an infrastructure-and-security expert months after
    index.html's JSON-LD had moved to "AI & Quantum-Readiness Advisor". These
    files are precisely what AI crawlers read, so the machine-readable identity
    contradicted the canonical page.
  * Five of ten asserted a "second doctorate in progress". Nothing in the
    canonical credential record supports it -- and the other five did not say
    it, so even the error was inconsistent.
  * Every one omitted Russian from its own language list while claiming the
    blog was available in nine languages. There are ten CV locales and the blog
    resolves in ten.
  * ru-llms.txt had drifted furthest: `.html` URLs where every other locale used
    directory URLs, and both phone numbers missing.

None of that is a translation problem. It is what happens when one fact lives
in ten places. So the fact now lives in ONE place -- assets/data/identity.json,
whose values are copied from the Person JSON-LD in index.html -- and this
renders it.

    python3 _tooling/llms/build.py [--check]

--check exits non-zero if any file is out of date, and writes nothing.

Deliberately not a templating framework. One JSON file, one renderer, plain
string formatting: the whole point is that a future positioning change is a
one-line edit that cannot drift.
"""

import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DATA = os.path.join(ROOT, 'assets', 'data', 'identity.json')

GENERATED_NOTE = {
    'en': 'Generated from assets/data/identity.json — do not edit by hand.',
    'fa': 'تولیدشده از assets/data/identity.json — دستی ویرایش نکنید.',
    'ar': 'مُولَّد من assets/data/identity.json — لا تحرّره يدويًا.',
    'de': 'Generiert aus assets/data/identity.json — nicht von Hand bearbeiten.',
    'es': 'Generado a partir de assets/data/identity.json — no editar a mano.',
    'fr': 'Généré à partir de assets/data/identity.json — ne pas modifier à la main.',
    'tr': 'assets/data/identity.json dosyasından üretildi — elle düzenlemeyin.',
    'zh': '由 assets/data/identity.json 生成 — 请勿手动编辑。',
    'ja': 'assets/data/identity.json から生成 — 手動で編集しないでください。',
    'ru': 'Сгенерировано из assets/data/identity.json — не редактировать вручную.',
}


def render(code, doc):
    """One locale's llms.txt, as a string."""
    c = doc['canonical']
    s = doc['locales'][code]
    site = c['site']

    def url(path):
        # `site` carries no trailing slash and every path in the canonical set
        # begins with one, so this is a plain concatenation. Stripping the
        # leading slash "to be safe" is what produced kohandezh.comcertificates/
        # on the first run.
        return site + path

    out = []
    out.append('# ' + s['heading'])
    out.append('')
    out.append('> ' + s['summary'])
    out.append('')
    out.append(s['certs'].format(certs=c['certifications']))
    out.append('')

    # ── Identity ──────────────────────────────────────────────────────────
    out.append('## ' + s['h_identity'])
    out.append('')

    # The Persian name rides alongside the Latin one everywhere EXCEPT the
    # Persian file, where repeating it would read as a stutter.
    name = c['name']['latin']
    if code == 'fa':
        name = c['name']['fa']
    elif code == 'ar':
        name = '%s (%s)' % (c['name']['latin'], c['name']['fa'])
    else:
        name = '%s (%s)' % (c['name']['latin'], c['name']['fa'])

    out.append('- %s: %s' % (s['l_name'], name))
    out.append('- %s: %s' % (s['l_title'], s['title']))
    out.append('- %s: %s (%s) — %s' % (s['l_company'], c['company']['name'], c['company']['short'], c['company']['url']))
    out.append('- %s: %s' % (s['l_education'], s['education']))
    out.append('- %s: %s | %s' % (s['l_contact'], c['contact']['email'], ' | '.join(c['contact']['phones'])))
    out.append('- %s: %s' % (s['l_linkedin'], c['profiles']['linkedin']))
    out.append('- %s: %s' % (s['l_x'], c['profiles']['x']))
    out.append('')

    # ── Advisory ──────────────────────────────────────────────────────────
    # Rendered from the same offers index.html advertises in makesOffer. This
    # section is the reason the drift mattered: the canonical page has been
    # offering post-quantum work that no locale file mentioned.
    home = url(next(l['path'] for l in c['locales'] if l['code'] == code))
    out.append('## ' + s['h_advisory'])
    out.append('')
    for title, desc in s['advisory']:
        out.append('- [%s](%s#service): %s' % (title, home, desc))
    out.append('')

    # ── Services ──────────────────────────────────────────────────────────
    out.append('## ' + s['h_services'])
    out.append('')
    for title, desc in s['services']:
        out.append('- [%s](%s#service): %s' % (title, home, desc))
    out.append('')

    # ── Pages ─────────────────────────────────────────────────────────────
    out.append('## ' + s['h_pages'])
    out.append('')
    out.append('- [%s](%s): %s' % (s['p_home'].format(lang=s['langname']), home, s['p_home_d']))
    out.append('- [%s](%s): %s' % (s['p_certs'], url(c['resources']['certificates']), s['p_certs_d']))
    out.append('- [%s](%s): %s' % (s['p_blog'], url(c['resources']['blog']),
                                   s['p_blog_d'].format(langcount=s['count'])))
    out.append('- [%s](%s): %s' % (s['p_portfolio'], url(c['resources']['portfolio']), s['p_portfolio_d']))

    # Every locale lists every OTHER locale. Russian was missing from all ten
    # lists; generating the list from the canonical set makes that impossible.
    others = []
    for loc in c['locales']:
        if loc['code'] == code:
            continue
        others.append('[%s](%s)' % (s['langs'][loc['code']], url(loc['path'])))
    out.append('- %s: %s' % (s['p_other'], ' · '.join(others)))
    out.append('')

    # ── Notes ─────────────────────────────────────────────────────────────
    out.append('## ' + s['h_notes'])
    out.append('')
    out.append(s['notes'])
    out.append('')
    out.append('<!-- %s -->' % GENERATED_NOTE[code])

    return '\n'.join(out) + '\n'


def main():
    check = '--check' in sys.argv
    with open(DATA, encoding='utf-8') as fh:
        doc = json.load(fh)

    stale = []
    written = 0

    for loc in doc['canonical']['locales']:
        code = loc['code']
        path = os.path.join(ROOT, loc['file'])
        body = render(code, doc)

        current = None
        if os.path.exists(path):
            with open(path, encoding='utf-8') as fh:
                current = fh.read()

        if current == body:
            continue

        if check:
            stale.append(loc['file'])
        else:
            with open(path, 'w', encoding='utf-8') as fh:
                fh.write(body)
            written += 1

    if check:
        if stale:
            sys.stderr.write(
                'llms: %d file(s) out of date with assets/data/identity.json:\n  %s\n'
                'Run: python3 _tooling/llms/build.py\n' % (len(stale), '\n  '.join(stale))
            )
            return 1
        print('llms: all %d locale files match the canonical identity' % len(doc['canonical']['locales']))
        return 0

    print('llms: wrote %d of %d locale files' % (written, len(doc['canonical']['locales'])))
    return 0


if __name__ == '__main__':
    sys.exit(main())
