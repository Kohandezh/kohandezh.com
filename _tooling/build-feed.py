#!/usr/bin/env python3
"""
Generate feed.xml (RSS 2.0) from the blog posts themselves.

The posts are the source of truth, not blog/index.html: each post already
carries a canonical URL, title, description and publish date in its meta tags
and JSON-LD, so a post can never appear in the feed with stale metadata or be
silently missed because someone forgot to add a card to the archive page.

Run:  python3 _tooling/build-feed.py
"""

import html
import re
import sys
from datetime import datetime, timezone, timedelta
from pathlib import Path
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parent.parent
BLOG = ROOT / "blog"
SITE = "https://kohandezh.com"
FEED_PATH = ROOT / "feed.xml"

TITLE = "Mohammad Ali Kohandezh — نوشته‌های منتخب"
DESCRIPTION = (
    "یادداشت‌ها، مصاحبه‌ها و تحلیل‌های محمدعلی کهن‌دژ دربارهٔ هوش مصنوعی، "
    "امنیت سایبری، زیرساخت فناوری اطلاعات و کارآفرینی."
)
TEHRAN = timezone(timedelta(hours=3, minutes=30))


def meta(pattern, source):
    m = re.search(pattern, source, re.S | re.I)
    return html.unescape(m.group(1)).strip() if m else None


def read_post(path):
    s = path.read_text(encoding="utf-8")

    # Skip anything that isn't actually an article.
    if 'og:type" content="article"' not in s:
        return None

    url = meta(r'<link rel="canonical" href="([^"]+)"', s)
    title = meta(r'<meta property="og:title" content="([^"]+)"', s)
    desc = meta(r'<meta name="description" content="([^"]+)"', s)
    # Prefer the OG timestamp, but fall back to the JSON-LD datePublished:
    # the older posts carry the date only in their structured data, and that is
    # just as authoritative. Requiring the meta tag would silently drop them.
    published = (meta(r'<meta property="article:published_time" content="([^"]+)"', s)
                 or meta(r'"datePublished"\s*:\s*"([^"]+)"', s))
    section = meta(r'<meta property="article:section" content="([^"]+)"', s)

    if not (url and title and published):
        print(f"  ! skipped {path.name}: missing canonical/title/published", file=sys.stderr)
        return None

    try:
        dt = datetime.fromisoformat(published)
    except ValueError:
        dt = datetime.strptime(published[:10], "%Y-%m-%d")
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=TEHRAN)

    return {
        "url": url,
        "title": title,
        "desc": desc or "",
        "dt": dt,
        "section": section,
    }


def rfc822(dt):
    # RSS requires RFC-822. Format in English regardless of content language,
    # since feed readers parse this field rather than display it.
    days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
              "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
    off = dt.strftime("%z") or "+0000"
    return (f"{days[dt.weekday()]}, {dt.day:02d} {months[dt.month - 1]} "
            f"{dt.year} {dt:%H:%M:%S} {off}")


def main():
    posts = []
    for path in sorted(BLOG.glob("*.html")):
        if path.name == "index.html":
            continue
        post = read_post(path)
        if post:
            posts.append(post)

    if not posts:
        print("no posts found — refusing to write an empty feed", file=sys.stderr)
        return 1

    posts.sort(key=lambda p: p["dt"], reverse=True)
    built = datetime.now(TEHRAN)

    items = []
    for p in posts:
        cat = f"\n      <category>{escape(p['section'])}</category>" if p["section"] else ""
        items.append(f"""    <item>
      <title>{escape(p['title'])}</title>
      <link>{escape(p['url'])}</link>
      <guid isPermaLink="true">{escape(p['url'])}</guid>
      <pubDate>{rfc822(p['dt'])}</pubDate>
      <description>{escape(p['desc'])}</description>{cat}
    </item>""")

    xml = f"""<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>{escape(TITLE)}</title>
    <link>{SITE}/blog/</link>
    <description>{escape(DESCRIPTION)}</description>
    <language>fa-IR</language>
    <copyright>© {built.year} Mohammad Ali Kohandezh</copyright>
    <lastBuildDate>{rfc822(built)}</lastBuildDate>
    <atom:link href="{SITE}/feed.xml" rel="self" type="application/rss+xml"/>
{chr(10).join(items)}
  </channel>
</rss>
"""
    FEED_PATH.write_text(xml, encoding="utf-8")
    print(f"wrote {FEED_PATH.relative_to(ROOT)} — {len(posts)} items")
    for p in posts:
        print(f"  {p['dt']:%Y-%m-%d}  {p['title'][:60]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
