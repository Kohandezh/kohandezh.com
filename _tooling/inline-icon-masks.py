#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""inline-icon-masks.py — rebuild simple-line.css with the masks inlined.

The 16 icon SVGs in assets/icon/simple-line/ are referenced as CSS
mask-images. Fetched individually they were 16 requests per page load for
16.8 KB total — on a host without a CDN, the request count is the cost, not
the bytes. This bakes each one into the stylesheet as a url-encoded data: URI.

Run after editing any .svg in that directory. Idempotent: it reads the .svg
files, never the already-inlined CSS, so re-running cannot double-encode.
Requires assets/icon/simple-line/simple-line.src.css as the template.
"""
import re, os, sys, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
D = os.path.join(ROOT, 'assets', 'icon', 'simple-line')
SRC = os.path.join(D, 'simple-line.src.css')
OUT = os.path.join(D, 'simple-line.css')

if not os.path.exists(SRC):
    sys.exit('missing template: %s' % SRC)

def datauri(name):
    raw = open(os.path.join(D, name)).read()
    raw = re.sub(r'>\s+<', '><', raw).strip()
    raw = re.sub(r'\s+', ' ', raw)
    enc = urllib.parse.quote(raw, safe="~()*!.'-_= :/><;,")
    return 'data:image/svg+xml,' + enc.replace('"', "'").replace('#', '%23')

s = open(SRC).read()
names = sorted(set(re.findall(r'url\("([^"]+\.svg)"\)', s)))
for n in names:
    s = s.replace('url("%s")' % n, 'url("%s")' % datauri(n))
open(OUT, 'w').write(s)
print('inlined %d masks -> %s (%.1f KB)' % (len(names), OUT, os.path.getsize(OUT) / 1024))
