#!/usr/bin/env python3
"""
build-cv.py — renders the résumés for all locales from cv-data.json.

Two variants, one data source, so they can never drift apart:

  ATS      (default)  Mohammad-Kohandezh-CV-ATS-XX.pdf   parser-friendly
  DESIGNED (--designed) Mohammad-Kohandezh-CV-XX.pdf     for human readers

WHY A SEPARATE "ATS" CV
The designed PDFs in assets/contact/ are two-column, styled documents. Applicant
tracking systems parse PDFs by reading the text layer in document order, so a
two-column layout interleaves the columns and scrambles the result — a job title
can end up glued to an unrelated certification. These outputs are the opposite:

  * ONE column, top to bottom, so reading order == visual order.
  * Real text, no images, no tables, no text boxes, no icons.
  * Standard section headings ("PROFESSIONAL EXPERIENCE", "EDUCATION",
    "CERTIFICATIONS") that parsers recognise.
  * Plain hyphen bullets rather than glyphs that decode as junk.
  * System fonts only, embedded by Chrome at print time.
  * Contact details as plain selectable text on their own line.

Proper nouns (certification names, vendors, products) stay in Latin script in
every locale — ATS keyword matching is done on the canonical string.

The DESIGNED variant is styled — accent rules, a tinted header band, two
columns for the reference lists — but it is still built so its text layer reads
top-to-bottom in a sane order, because a PDF that looks good and parses badly
is the problem this rewrite exists to avoid.

USAGE
    python3 _tooling/cv/build-cv.py                  # ATS, all locales
    python3 _tooling/cv/build-cv.py --designed       # designed, all locales
    python3 _tooling/cv/build-cv.py --designed en fa # designed, selected

Output: assets/contact/Mohammad-Kohandezh-CV[-ATS]-{XX}.pdf
Requires: Google Chrome (headless --print-to-pdf).
"""

import json
import html
import os
import shutil
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DATA = os.path.join(ROOT, "_tooling", "cv", "cv-data.json")
OUT = os.path.join(ROOT, "assets", "contact")

LOCALES = ["en", "fa", "ar", "de", "es", "fr", "tr", "zh", "ja"]
RTL = {"fa", "ar"}

CHROME_CANDIDATES = [
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
    "/Applications/Chromium.app/Contents/MacOS/Chromium",
    shutil.which("google-chrome") or "",
    shutil.which("chromium") or "",
]

# Latin first so proper nouns render properly even inside RTL/CJK runs.
FONTS = {
    "default": '"Helvetica Neue", Helvetica, Arial, sans-serif',
    "fa": 'Arial, "Times New Roman", "Geeza Pro", Tahoma, sans-serif',
    "ar": 'Arial, "Times New Roman", "Geeza Pro", Tahoma, sans-serif',
    "zh": 'Arial, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif',
    "ja": 'Arial, "Hiragino Kaku Gothic ProN", "Yu Gothic", "Meiryo", sans-serif',
}


BASE_CSS = """
  @page { size: A4; margin: %(margin)s; }
  * { box-sizing: border-box; }
  body {
    margin: 0; font-family: %(font)s; font-size: 10.2pt; line-height: 1.45;
    color: #111; -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }
  h1 { margin: 0 0 2pt; font-size: 19pt; line-height: 1.2; }
  .title { margin: 0 0 4pt; font-size: 11pt; font-weight: 600; }
  .contact { margin: 0 0 2pt; font-size: 9.6pt; }
  .rule { border-bottom: 1pt solid #333; margin: 9pt 0 7pt; }
  h2 {
    margin: 12pt 0 5pt; font-size: 10.6pt; font-weight: 700;
    letter-spacing: .06em; padding-bottom: 2pt;
    border-bottom: 0.6pt solid %(hrule)s;
  }
  h2:first-of-type { margin-top: 4pt; }
  p { margin: 0 0 5pt; }
  .job { margin: 0 0 8pt; page-break-inside: avoid; break-inside: avoid; }
  .job-head { font-weight: 700; font-size: 10.6pt; }
  .job-meta { font-size: 9.6pt; margin: 0 0 2pt; color: %(meta)s; }
  ul { margin: 2pt 0 0; padding-inline-start: 15pt; }
  li { margin: 0 0 2pt; }
  .cols { margin: 0; padding-inline-start: 15pt; }
  .kv { margin: 0 0 3pt; }
  .kv b { font-weight: 700; }
  /* Latin proper nouns inside an RTL line must not be reordered. */
  .ltr { direction: ltr; unicode-bidi: embed; display: inline-block; }
"""

# The designed variant adds colour and a two-column reference list. It stays a
# SINGLE content flow — CSS multicol does not reorder the text layer — so it
# still parses top-to-bottom even though it reads as a designed document.
DESIGNED_CSS = """
  body { color: #16211a; }
  .band {
    margin: -6mm -8mm 8pt;
    padding: 9mm 8mm 6mm;
    background: #0f1c15;
    color: #f2fbee;
    border-bottom: 2.4pt solid #6ee36f;
  }
  .band h1 { color: #ffffff; letter-spacing: -.01em; }
  .band .title { color: #b9f0a6; font-weight: 600; }
  .band .contact { color: rgba(238, 251, 236, .82); }
  h2 { color: #17422b; border-bottom-color: #9fd9a4; text-transform: uppercase; }
  .job-head { color: #10251a; }
  li::marker { color: #3f8f52; }
  .cols.two { columns: 2; column-gap: 14pt; }
  .cols.two li { break-inside: avoid; }
  .kv b { color: #17422b; }
"""


def css_for(variant, font):
    """One stylesheet per variant, sharing the same structural base."""
    designed = variant == "designed"
    base = BASE_CSS % {
        "font": font,
        "margin": "0 15mm 14mm" if designed else "14mm 15mm",
        "hrule": "#9fd9a4" if designed else "#999",
        "meta": "#4a5a50" if designed else "#333",
    }
    extra = DESIGNED_CSS if designed else ""
    return "<style>%s%s</style></head><body>" % (base, extra)


def pick(value, loc):
    """Values are either a plain string or a {locale: string} map."""
    if isinstance(value, dict):
        return value.get(loc) or value.get("en") or ""
    return value or ""


def e(text):
    return html.escape(str(text), quote=False)


def render(data, loc, variant="ats"):
    p = data["profile"]
    h = data["headings"]
    dir_attr = "rtl" if loc in RTL else "ltr"
    font = FONTS.get(loc, FONTS["default"])

    out = []
    add = out.append

    add("<!doctype html>")
    add('<html lang="%s" dir="%s"><head><meta charset="utf-8">' % (loc, dir_attr))
    add("<title>%s — CV</title>" % e(pick(p["name"], loc)))
    add(css_for(variant, font))

    # ---- header --------------------------------------------------------
    if variant == "designed":
        add('<div class="band">')
    add("<h1>%s</h1>" % e(pick(p["name"], loc)))
    add('<p class="title">%s</p>' % e(pick(p["title"], loc)))
    contact = " | ".join([
        p["email"], p["phone"], pick(p["location"], loc), p["website"], p["linkedin"]
    ])
    add('<p class="contact"><span class="ltr">%s</span></p>' % e(contact))
    if variant == "designed":
        add("</div>")
    else:
        add('<div class="rule"></div>')

    # ---- summary -------------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["summary"], loc)))
    add("<p>%s</p>" % e(pick(data["summary"], loc)))

    # ---- experience ----------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["experience"], loc)))
    for job in data["experience"]:
        add('<div class="job">')
        add('<div class="job-head">%s</div>' % e(pick(job["role"], loc)))
        add('<p class="job-meta"><span class="ltr">%s</span> — %s</p>'
            % (e(job["org"]), e(pick(job["period"], loc))))
        bullets = pick(job["bullets"], loc)
        if bullets:
            add("<ul>")
            for b in bullets:
                add("<li>%s</li>" % e(b))
            add("</ul>")
        add("</div>")

    # ---- education -----------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["education"], loc)))
    for ed in data["education"]:
        add('<div class="job">')
        add('<div class="job-head">%s</div>' % e(pick(ed["degree"], loc)))
        bits = []
        if ed.get("school"):
            bits.append('<span class="ltr">%s</span>' % e(ed["school"]))
        period = pick(ed.get("period"), loc)
        if period:
            bits.append(e(period))
        note = pick(ed.get("note"), loc)
        if note:
            bits.append(e(note))
        if bits:
            add('<p class="job-meta">%s</p>' % " — ".join(bits))
        add("</div>")

    # ---- certifications ------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["certifications"], loc)))
    add('<ul class="cols%s">' % (" two" if variant == "designed" else ""))
    for c in data["certifications"]:
        add('<li><span class="ltr">%s</span></li>' % e(c))
    add("</ul>")

    # ---- recognition ---------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["recognition"], loc)))
    add('<ul class="cols%s">' % (" two" if variant == "designed" else ""))
    for r in data["recognition"]:
        add("<li>%s</li>" % e(pick(r, loc)))
    add("</ul>")

    # ---- skills --------------------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["skills"], loc)))
    for key in ("ai", "security", "infra", "products"):
        grp = data["skills"][key]
        add('<p class="kv"><b>%s:</b> <span class="ltr">%s</span></p>'
            % (e(pick(grp["label"], loc)), e(grp["items"])))

    # ---- languages + sectors -------------------------------------------
    add("<h2>%s</h2>" % e(pick(h["languages"], loc)))
    add("<p>%s</p>" % e(pick(data["languages"], loc)))

    add("<h2>%s</h2>" % e(pick(h["sectors"], loc)))
    add("<p>%s</p>" % e(pick(data["sectors"], loc)))

    add("</body></html>")
    return "\n".join(out)


def chrome():
    for c in CHROME_CANDIDATES:
        if c and os.path.exists(c):
            return c
    sys.exit("Google Chrome not found — needed to render the PDFs.")


def main():
    args = [a.lower() for a in sys.argv[1:]]
    designed = "--designed" in args
    variant = "designed" if designed else "ats"
    wanted = [a for a in args if not a.startswith("--")] or LOCALES
    data = json.load(open(DATA, encoding="utf-8"))

    # Guard: the whole point of this rebuild is that one product name is gone.
    blob = json.dumps(data, ensure_ascii=False)
    body = blob.replace(json.dumps(data.get("_readme", []), ensure_ascii=False), "")
    if "gatemate" in body.lower():
        sys.exit("REFUSING: 'GateMate' present in cv-data.json content.")

    binary = chrome()
    os.makedirs(OUT, exist_ok=True)
    tmp = tempfile.mkdtemp(prefix="kdcv-cv-")

    for loc in wanted:
        if loc not in LOCALES:
            print("  skip unknown locale: %s" % loc)
            continue
        src = os.path.join(tmp, "cv-%s-%s.html" % (variant, loc))
        with open(src, "w", encoding="utf-8") as fh:
            fh.write(render(data, loc, variant))

        name = ("Mohammad-Kohandezh-CV-%s.pdf" if designed
                else "Mohammad-Kohandezh-CV-ATS-%s.pdf") % loc.upper()
        dest = os.path.join(OUT, name)
        subprocess.run([
            binary, "--headless", "--disable-gpu", "--no-pdf-header-footer",
            "--run-all-compositor-stages-before-draw", "--virtual-time-budget=4000",
            "--print-to-pdf=%s" % dest, "file://%s" % src,
        ], check=True, capture_output=True)
        size = os.path.getsize(dest)
        print("  %s  %-52s %6.1f KB" % (loc.upper(), os.path.basename(dest), size / 1024))

    shutil.rmtree(tmp, ignore_errors=True)
    print("done — %d %s CV(s) in assets/contact/" % (len(wanted), variant.upper()))


if __name__ == "__main__":
    main()
