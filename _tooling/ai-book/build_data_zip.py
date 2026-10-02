#!/usr/bin/env python3
"""
Build a book's data zip: exactly the bundle files the kohandezh-knowledge
plugin reads (Books/BOOK-FORMAT.md), for upload to the server's Books/ folder.

    python3 _tooling/ai-book/build_data_zip.py [--slug ai-governance] [--root Books/<slug>]
        [--folder AiBook] [--out _tooling/wp-theme/ai-book-data.zip] [--no-validate]

Defaults per slug: root Books/<slug>; folder AiBook for ai-governance, else the
slug in CamelCase (ics-cybersecurity -> IcsCybersecurity); out
_tooling/wp-theme/ai-book-data.zip for ai-governance, else
_tooling/wp-theme/<slug>-data.zip. The archive holds one top-level folder, so
extracting it into the server's Books/ yields Books/<folder>/ — the root named
by KBK_AI_BOOK_ROOT (ai-governance) or by the book's registry row.

The bundle is validated first (validate_book.py, build outputs required) and
nothing is written on a FAIL; --no-validate skips that. The file list mirrors
every path the plugin's classes open; run `npm run test:ai-book` with
KBK_AI_BOOK_ROOT pointed at an extracted copy to prove nothing is missing.
"""
import argparse
import re
import sys
import zipfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import validate_book  # noqa: E402  (stdlib-only sibling)

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'master/book.json', 'master/bibliography.json', 'master/source_map.json',
    'provenance/content_ids.json', 'provenance/citation-registry.json', 'provenance/release-manifest.json',
    'release/09_glossary.json', 'release/14_rag_corpus_fa.jsonl', 'release/06_book_fa.pdf',
    'knowledge/graph.json', 'knowledge/entities.jsonl',
    'manifests/sources.json', 'html/search/index.json',
]
# Shipped when present; the plugin reads it only if its book_sha256 matches.
OPTIONAL_FILES = ['master/reader-overlay.json', 'taxonomy/doc_titles_fa.json']
LEGACY_SLUG, LEGACY_FOLDER, LEGACY_ZIP = 'ai-governance', 'AiBook', 'ai-book-data.zip'
FOLDER_RE = re.compile(r'^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$')

# The contract and this list must not drift apart.
assert set(FILES) == set(validate_book.BUNDLE_FILES), 'FILES disagrees with validate_book.BUNDLE_FILES'
assert OPTIONAL_FILES == validate_book.OPTIONAL_FILES, 'OPTIONAL_FILES disagrees with validate_book'


def camel(slug: str) -> str:
    return ''.join(word.capitalize() for word in slug.split('-'))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('--slug', default=LEGACY_SLUG, help='registry slug of the book (default: ai-governance)')
    ap.add_argument('--root', help='book root (default: Books/<slug>)')
    ap.add_argument('--folder', help='top-level folder in the zip (default: AiBook, or the slug in CamelCase)')
    ap.add_argument('--out', help='zip path (default: _tooling/wp-theme/ai-book-data.zip or <slug>-data.zip)')
    ap.add_argument('--no-validate', action='store_true', help='skip validate_book.py (not for uploads)')
    args = ap.parse_args()
    if not validate_book.BOOK_SLUG_RE.match(args.slug) or len(args.slug) > 40:
        ap.error('--slug must match ^[a-z0-9]+(-[a-z0-9]+)*$ and be at most 40 characters')
    legacy = args.slug == LEGACY_SLUG
    folder = args.folder or (LEGACY_FOLDER if legacy else camel(args.slug))
    if not FOLDER_RE.match(folder):
        ap.error('--folder must be a plain folder name')
    book = Path(args.root) if args.root else ROOT / 'Books' / args.slug
    out = Path(args.out) if args.out else ROOT / '_tooling' / 'wp-theme' / (LEGACY_ZIP if legacy else args.slug + '-data.zip')

    files = FILES + sorted(str(p.relative_to(book)) for p in (book / 'templates').glob('TPL-*.json'))
    missing = [f for f in files if not (book / f).is_file()]
    files += [f for f in OPTIONAL_FILES if (book / f).is_file()]
    if missing:
        print('missing from the book root: ' + ', '.join(missing), file=sys.stderr)
        return 1
    if not args.no_validate:
        report = validate_book.validate(book, require_build=True, slug=args.slug)
        if report['exit']:
            validate_book.print_report(report, out=sys.stderr)
            print('not built: the bundle fails Books/BOOK-FORMAT.md', file=sys.stderr)
            return 1
        s = report['summary']
        print('validated %s: %d PASS, %d WARN, %d SKIP' % (args.slug, s['PASS'], s['WARN'], s['SKIP']))
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as archive:
        for f in files:
            archive.write(book / f, folder + '/' + f)
    print(f'{out}: {len(files)} files, {out.stat().st_size // (1024 * 1024)} MB')
    return 0


if __name__ == '__main__':
    sys.exit(main())
