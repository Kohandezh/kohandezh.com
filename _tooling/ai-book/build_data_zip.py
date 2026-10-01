#!/usr/bin/env python3
"""
Build ai-book-data.zip: exactly the canonical bundle files the
kohandezh-knowledge plugin reads, for upload to the server's book root
(the folder named by KBK_AI_BOOK_ROOT in wp-config.php).

    python3 _tooling/ai-book/build_data_zip.py [--root Books/ai-governance] [--out _tooling/wp-theme/ai-book-data.zip]

The archive holds one top-level folder, AiBook/, so extracting it into the
server's Books/ folder yields Books/AiBook/. The list mirrors every path the
plugin's classes open; run `npm run test:ai-book` with KBK_AI_BOOK_ROOT
pointed at an extracted copy to prove nothing is missing.
"""
import argparse
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'master/book.json', 'master/bibliography.json', 'master/source_map.json',
    'provenance/content_ids.json', 'provenance/citation-registry.json', 'provenance/release-manifest.json',
    'release/09_glossary.json', 'release/14_rag_corpus_fa.jsonl', 'release/06_book_fa.pdf',
    'knowledge/graph.json', 'knowledge/entities.jsonl',
    'manifests/sources.json', 'html/search/index.json',
]


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', default=str(ROOT / 'Books' / 'ai-governance'))
    ap.add_argument('--out', default=str(ROOT / '_tooling' / 'wp-theme' / 'ai-book-data.zip'))
    args = ap.parse_args()
    book = Path(args.root)
    files = FILES + sorted(str(p.relative_to(book)) for p in (book / 'templates').glob('TPL-*.json'))
    missing = [f for f in files if not (book / f).is_file()]
    if missing:
        print('missing from the book root: ' + ', '.join(missing), file=sys.stderr)
        return 1
    out = Path(args.out)
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as archive:
        for f in files:
            archive.write(book / f, 'AiBook/' + f)
    print(f'{out}: {len(files)} files, {out.stat().st_size // (1024 * 1024)} MB')
    return 0


if __name__ == '__main__':
    sys.exit(main())
