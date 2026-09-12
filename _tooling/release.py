#!/usr/bin/env python3
"""Prepare one coherent WordPress release; verify public hashes without writes.

Run AFTER build.sh and sync-from-static.py. Never deploys, purges caches, reads
credentials or claims a crawl/index happened. Outputs are disposable artifacts.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
from pathlib import Path
import subprocess
import urllib.error
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
THEME = ROOT / '_tooling/wp-theme/kohandezhcv'
WP_ROOT = ROOT / '_tooling/wp-root'
OUTPUT = ROOT / '_tooling/releases/current'
PREFIXES = ['', 'fa-', 'ar-', 'de-', 'es-', 'fr-', 'tr-', 'zh-', 'ja-', 'ru-']
THEME_URL = '/wp-content/themes/kohandezhcv/'


def sha(data):
    return hashlib.sha256(data).hexdigest()


def root_payload():
    # The root sitemap is intentionally WP-specific; posts come from the live
    # DB-backed sitemap advertised in robots. Never copy the static blog list.
    payload = {'sitemap.xml': (WP_ROOT / 'sitemap.xml').read_bytes()}
    for name in ['sw.js', 'offline.html', 'manifest.json']:
        payload[name] = (ROOT / name).read_text().replace('/assets/', THEME_URL + 'assets/').encode()
    # feed.xml ships from the project root, NOT the wp-root mirror. The mirror
    # carried the STATIC blog URLs (/blog/*.html), which do not exist on
    # WordPress -- every one of the ten links in the live feed answered 404.
    # The root copy uses the real WordPress permalinks. It is deliberately not
    # path-rewritten: these are absolute post URLs, not theme assets.
    payload['feed.xml'] = (ROOT / 'feed.xml').read_bytes()
    payload['robots.txt'] = ((ROOT / 'robots.txt').read_text().rstrip() + '\n\n# WordPress published posts (dynamic, paginated)\nSitemap: https://kohandezh.com/?kdcv_sitemap=index\n').encode()
    for prefix in PREFIXES:
        name = prefix + 'llms.txt'
        data = (ROOT / name).read_bytes()
        if (THEME / name).read_bytes() != data:
            raise RuntimeError(f'{name}: theme differs from source; run theme sync')
        payload[name] = data
    return payload


def prepare():
    check = subprocess.run(['python3', str(ROOT / '_tooling/wp-theme/sync-from-static.py'), '--dry-run'], capture_output=True, text=True, check=True)
    if 'pipeline is in parity' not in check.stdout:
        raise RuntimeError('Theme is not in parity; sync before packaging.\n' + check.stdout)
    theme_zip = THEME.parent / 'kohandezhcv.zip'
    if not theme_zip.is_file():
        raise RuntimeError('Build the installable theme ZIP first')
    # Verify the existing archive itself, not just its file name or timestamp.
    with zipfile.ZipFile(theme_zip) as archive:
        for member in archive.infolist():
            if not member.is_dir():
                local = THEME.parent / member.filename
                if not local.is_relative_to(THEME) or archive.read(member) != local.read_bytes():
                    raise RuntimeError(f'Theme ZIP is stale/invalid: {member.filename}')
        for required in ['inc/publication.php', 'inc/diagnostics.php', 'functions.php', 'single.php']:
            if THEME.name + '/' + required not in archive.namelist():
                raise RuntimeError('Theme ZIP lacks ' + required)
    OUTPUT.mkdir(parents=True, exist_ok=True)
    payload = root_payload()
    manifest = {'schema': 1, 'theme_zip_sha256': sha(theme_zip.read_bytes()), 'public': []}
    with zipfile.ZipFile(OUTPUT / 'root-files.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
        for name, data in payload.items():
            archive.writestr(name, data)
            # Persist the WP-root mirror so old manual deployment paths cannot
            # silently select a stale service worker or summary next time.
            (WP_ROOT / name).write_bytes(data)
            manifest['public'].append({'path': '/' + name, 'sha256': sha(data)})
    for name in ['assets/js/linkedin-content.min.js', 'assets/js/kdcv-interaction-fix.js', 'assets/js/home-blog-scroll.js', 'assets/css/styles.min.css', 'assets/images/social/bale-logo.png', 'assets/images/social/eitaa.svg']:
        manifest['public'].append({'path': THEME_URL + name, 'sha256': sha((THEME / name).read_bytes())})
    (OUTPUT / 'release-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print(f'Prepared {len(payload)} root files and verified theme ZIP; no production writes.\n{OUTPUT}')


def verify(base):
    manifest = json.loads((OUTPUT / 'release-manifest.json').read_text())

    def fetch(item):
        url = base.rstrip('/') + item['path']
        try:
            request = urllib.request.Request(url, headers={'User-Agent': 'KohandezhReleaseVerifier/1.0'})
            with urllib.request.urlopen(request, timeout=20) as response:
                data = response.read(4 * 1024 * 1024 + 1)
                return {'path': item['path'], 'status': response.status, 'match': sha(data) == item['sha256'], 'cache': response.headers.get('X-LiteSpeed-Cache', ''), 'expected': item['sha256'], 'received': sha(data)}
        except (urllib.error.URLError, TimeoutError) as error:
            return {'path': item['path'], 'match': False, 'error': str(error)}

    with ThreadPoolExecutor(max_workers=4) as pool:
        results = list(pool.map(fetch, manifest['public']))
    (OUTPUT / 'public-verification.json').write_text(json.dumps(results, indent=2) + '\n')
    for item in results:
        print(('MATCH' if item['match'] else 'DRIFT/ERROR') + ' ' + item['path'])
    print('Hash matching verifies the response served now, not search-engine indexing.')
    return 0 if all(item['match'] for item in results) else 1


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['prepare', 'verify'])
    parser.add_argument('--base-url', default='https://kohandezh.com')
    args = parser.parse_args()
    if args.command == 'prepare':
        prepare()
    else:
        raise SystemExit(verify(args.base_url))
