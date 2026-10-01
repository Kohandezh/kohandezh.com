import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('release', Path(__file__).resolve().parents[1] / 'release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class Release(unittest.TestCase):
    def test_root_files_are_built_from_current_sources(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            theme = root / 'theme'
            wp_root = root / 'wp-root'
            theme.mkdir(); wp_root.mkdir()
            for name in ['sw.js', 'offline.html', 'manifest.json']:
                (root / name).write_text('/assets/new-current-file')
                (wp_root / name).write_text('stale')
            (root / 'robots.txt').write_text('User-agent: *\nAllow: /\n')
            (root / 'feed.xml').write_text('<rss>current-published-posts</rss>')
            (wp_root / 'feed.xml').write_text('stale')
            (wp_root / 'sitemap.xml').write_text('<urlset/>')
            for prefix in release.PREFIXES:
                (root / (prefix + 'llms.txt')).write_text('current')
                (theme / (prefix + 'llms.txt')).write_text('current')
            old = release.ROOT, release.THEME, release.WP_ROOT
            release.ROOT, release.THEME, release.WP_ROOT = root, theme, wp_root
            try:
                payload = release.root_payload()
                self.assertEqual(len(payload), 16)
                self.assertEqual(payload['feed.xml'], b'<rss>current-published-posts</rss>')
                self.assertEqual(payload['fa-llms.txt'], b'current')
                self.assertIn(b'/wp-content/themes/kohandezhcv/assets/', payload['sw.js'])
                self.assertIn(b'?kdcv_sitemap=index', payload['robots.txt'])
                self.assertNotIn(b'stale', b''.join(payload.values()))
                (theme / 'llms.txt').write_text('stale')
                with self.assertRaisesRegex(RuntimeError, 'theme differs'):
                    release.root_payload()
            finally:
                release.ROOT, release.THEME, release.WP_ROOT = old


if __name__ == '__main__':
    unittest.main()
