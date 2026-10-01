#!/usr/bin/env python3
"""Prepare the two existing Elecomp articles for WordPress publication.

Creates a reviewable payload only. Existing drafts must be checked before
publishing, and sitemap/feed URLs verified after WordPress assigns permalinks.
"""
import html
import json
import re
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SLUGS = ('ayno-tv-elecomp-1404', 'elecomp-talks-1404-ai-for-children')

def extract(source, cls):
    match = re.search(r'<div class="' + re.escape(cls) + r'"[^>]*>', source)
    assert match, cls
    depth = 1
    for tag in re.finditer(r'</?div\b[^>]*>', source[match.end():]):
        depth += -1 if tag.group().startswith('</') else 1
        if not depth:
            return source[match.end():match.end() + tag.start()].strip()
    raise ValueError('Unclosed ' + cls)

def main():
    posts = []
    for slug in SLUGS:
        source = (ROOT / 'blog' / (slug + '.html')).read_text()
        body = extract(source, 'blog-article-body')
        embed = extract(source, 'blog-embed')
        embed = embed.replace('<iframe ', '<iframe style="width:100%;aspect-ratio:16/9;border:0" ')
        def link(match):
            target = match[1]
            article = ROOT / 'blog' / target
            if not article.exists():
                raise ValueError('Unknown internal article: ' + target)
            date = re.search(r'"datePublished":\s*"([0-9-]+)"', article.read_text())[1]
            return 'href="https://kohandezh.com/' + date.replace('-', '/') + '/' + article.stem + '/"'
        body = re.sub(r'href="([a-z0-9-]+\.html)"', link, body)
        posts.append({
            'slug': slug,
            'title': html.unescape(re.search(r'<h1[^>]*>(.*?)</h1>', source, re.S)[1]),
            'excerpt': html.unescape(re.search(r'<meta name="description" content="([^"]+)"', source)[1]),
            'original_date': re.search(r'"datePublished":\s*"([0-9-]+)"', source)[1],
            'content': '<!-- wp:html -->\n<div class="blog-embed">' + embed + '</div>\n' + body + '\n<!-- /wp:html -->',
        })
    output = ROOT / '_tooling/wp-theme/elecomp-publication.json'
    output.write_text(json.dumps(posts, ensure_ascii=False, indent=2) + '\n')
    print(f'Prepared {len(posts)} articles: {output}')
    ns = {'wp': 'http://wordpress.org/export/1.2/', 'content': 'http://purl.org/rss/1.0/modules/content/', 'excerpt': 'http://wordpress.org/export/1.2/excerpt/', 'dc': 'http://purl.org/dc/elements/1.1/'}
    for prefix, uri in ns.items():
        ET.register_namespace(prefix, uri)
    root = ET.Element('rss', version='2.0')
    channel = ET.SubElement(root, 'channel')
    def add(parent, tag, text):
        if ':' in tag:
            prefix, local = tag.split(':', 1)
            tag = '{' + ns[prefix] + '}' + local
        ET.SubElement(parent, tag).text = text
    for tag, value in [('title', 'Kohandezh Elecomp articles'), ('link', 'https://kohandezh.com'), ('language', 'fa'), ('wp:wxr_version', '1.2'), ('wp:base_site_url', 'https://kohandezh.com'), ('wp:base_blog_url', 'https://kohandezh.com')]:
        add(channel, tag, value)
    for index, post in enumerate(posts):
        item = ET.SubElement(channel, 'item')
        url = 'https://kohandezh.com/' + post['original_date'].replace('-', '/') + '/' + post['slug'] + '/'
        values = [('title', post['title']), ('link', url), ('dc:creator', 'kohandezh'), ('content:encoded', post['content']), ('excerpt:encoded', post['excerpt']), ('wp:post_id', str(900001 + index)), ('wp:post_date', post['original_date'] + ' 12:00:00'), ('wp:post_date_gmt', post['original_date'] + ' 08:30:00'), ('wp:post_name', post['slug']), ('wp:status', 'publish'), ('wp:post_type', 'post'), ('wp:post_parent', '0'), ('wp:is_sticky', '0'), ('wp:comment_status', 'closed'), ('wp:ping_status', 'closed')]
        for tag, value in values:
            add(item, tag, value)
        ET.SubElement(item, 'category', domain='category', nicename='رویدادها-و-نوآوری').text = 'رویدادها و نوآوری'
    wxr = output.with_suffix('.wxr.xml')
    ET.ElementTree(root).write(wxr, encoding='utf-8', xml_declaration=True)
    assert len(ET.parse(wxr).findall('./channel/item')) == 2
    print(f'WordPress import: {wxr}')

if __name__ == '__main__':
    main()
