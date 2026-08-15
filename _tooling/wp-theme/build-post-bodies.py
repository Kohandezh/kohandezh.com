#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""build-post-bodies.py — compose whole-article translations for WordPress.

WHY THIS EXISTS
The static blog posts translate in place: every leaf block carries
data-i18n="pNN" and page-i18n.js swaps the innerHTML. WordPress cannot do
that — single.php renders the post body with the_content(), straight out of
the database, and that HTML has none of those attributes. So on WP the article
would stay Persian no matter how complete the dictionary was.

The fix is to give each locale ONE key holding the entire translated body:
`__body`. This script produces it by taking the static file as the template
and applying the same dictionary the browser would apply — so the WordPress
body is byte-for-byte what the static page renders, and there is exactly one
source of truth for the translation.

Run from the repo root. Idempotent: rewrites __body/__h1/__eyebrow each time.
"""
import json, os, re, sys, glob

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.chdir(ROOT)

TAGGED = re.compile(r'<([a-zA-Z0-9]+)([^>]*?)\sdata-i18n="([^"]+)"([^>]*)>')

def render(html, dict_):
    """Replace the inner HTML of every data-i18n element with its translation.

    Walks the tagged elements one at a time and rescans from the start after
    each substitution, because a replacement changes every offset after it.
    Nested tagged elements do not occur — the tagger only marks leaves."""
    out = html
    done = set()
    while True:
        m = None
        for cand in TAGGED.finditer(out):
            if id(cand.group(3)) in done:
                continue
            if (cand.group(3), cand.start()) in done:
                continue
            m = cand
            break
        if not m:
            break
        key, tag = m.group(3), m.group(1)
        value = dict_.get(key)
        if value is None:
            done.add((key, m.start()))
            continue
        close = "</%s>" % tag
        end = out.find(close, m.end())
        if end == -1:
            done.add((key, m.start()))
            continue
        out = out[:m.end()] + value + out[end:]
        done.add((key, m.start()))
    return apply_attrs(out, dict_)

ATTR_KEYS = [("data-i18n-alt", "alt"), ("data-i18n-aria", "aria-label"),
             ("data-i18n-title", "title")]

def apply_attrs(html, dict_):
    """Translate alt/aria-label/title inside the composed body.

    page-i18n would also do this in the browser, but only as a second pass
    after the innerHTML swap. Baking it in here means the body WordPress
    serves is complete on its own — no ordering dependency, and the HTML in
    the page source (which is what crawlers read) is already in the right
    language."""
    for marker, attr in ATTR_KEYS:
        def swap(m, attr=attr):
            value = dict_.get(m.group(2))
            if value is None:
                return m.group(0)
            return '%s="%s"' % (attr, value.replace('"', "&quot;"))
        # The marker sits immediately after the attribute it translates —
        # that is how the tagger writes it.
        html = re.sub(r'%s="([^"]*)"\s+%s="([^"]+)"' % (re.escape(attr), re.escape(marker)),
                      lambda m: swap(m) + ' %s="%s"' % (marker, m.group(2)), html)
    return html

def section(html, cls):
    """innerHTML of the first element carrying `cls`, matched by depth."""
    m = re.search(r'<div class="%s"[^>]*>' % re.escape(cls), html)
    if not m:
        return None
    depth, i = 1, m.end()
    tag = re.compile(r'</?div\b')
    while depth and i < len(html):
        t = tag.search(html, i)
        if not t:
            return None
        depth += -1 if t.group(0).startswith('</') else 1
        i = t.end()
        if depth == 0:
            return html[m.end():t.start()]
    return None

LOCS = ["en", "ar", "de", "es", "fr", "tr", "zh", "ja", "ru"]
count = 0
for path in sorted(glob.glob('blog/*.html')):
    if path.endswith('index.html'):
        continue
    slug = 'post-' + os.path.basename(path)[:-5]
    jf = 'assets/data/i18n/%s.json' % slug
    if not os.path.exists(jf):
        continue
    html = open(path).read()
    d = json.load(open(jf))
    body = section(html, 'blog-article-body')
    if body is None:
        print('  !! no body found in', path); continue
    for loc in LOCS:
        if loc not in d:
            continue
        d[loc]['__body'] = render(body, d[loc]).strip()
        # The heading and category live outside .blog-article-body, so they get
        # their own keys rather than being carried by __body.
        h1 = re.search(r'<h1[^>]*data-i18n="([^"]+)"[^>]*>', html)
        eb = re.search(r'<span class="blog-eyebrow"[^>]*data-i18n="([^"]+)"[^>]*>', html)
        if h1 and h1.group(1) in d[loc]:
            d[loc]['__h1'] = d[loc][h1.group(1)]
        if eb and eb.group(1) in d[loc]:
            d[loc]['__eyebrow'] = d[loc][eb.group(1)]
    json.dump(d, open(jf, 'w'), ensure_ascii=False, indent=2)
    count += 1
    print('  %-46s __body x%d' % (slug, len([l for l in LOCS if l in d])))
print('composed whole-article bodies for %d posts' % count)
