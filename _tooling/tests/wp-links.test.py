"""Regression: static navigation must not resolve below a WP page permalink."""
import importlib.util
import os
from pathlib import Path
import unittest
import contextlib
import io
import tempfile

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('sync', os.environ.get('KDCV_SYNC_TEST_SOURCE', str(ROOT / '_tooling/wp-theme/sync-from-static.py')))
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)


class Links(unittest.TestCase):
    def converted(self, value):
        return sync.transform('<html><head></head><body><a href="' + value + '">Go</a></body></html>', 'knowledge page', False)

    def test_home_fragments_and_queries(self):
        for value in ['index.html#contact', '../index.html#contact', '/index.html#contact', 'https://kohandezh.com/index.html#contact']:
            with self.subTest(value=value):
                self.assertIn('href="' + sync.HOME + '#contact"', self.converted(value))
        self.assertIn(sync.HOME + '?lang=fa&amp;x=1#contact', self.converted('index.html?lang=fa&amp;x=1#contact'))

    def test_blog_aliases(self):
        for value in ['blog/index.html', '../blog/index.html', './blog/', '/blog/index.html?lang=ar#posts']:
            with self.subTest(value=value):
                self.assertIn('href="' + sync.page_url('blog'), self.converted(value))

    def test_locale_and_standalone_suffixes(self):
        for path, slug in [('fa.html', 'fa'), ('PSN.html', 'psn'), ('Certificates.html', 'certificates'), ('knowledge.html', 'knowledge'), ('portfolio/index.html', 'portfolio')]:
            self.assertIn(sync.page_url(slug) + '?lang=ar#section', self.converted('../' + path + '?lang=ar#section'))

    def test_external_fragment_assets_unchanged(self):
        for value in ['https://example.com/index.html#contact', '//example.com/blog/index.html', 'mailto:Kohandezh@hotmail.com', 'tel:+18106662283', '#contact', 'unknown.html#contact']:
            with self.subTest(value=value):
                self.assertIn('href="' + value + '"', self.converted(value))

    def test_nested_source_and_single_quotes(self):
        source = "<a href='index.html#clients'>Local</a><a href='../index.html#contact'>Home</a>"
        result = sync.rewrite_navigation(source, 'portfolio/index.html')
        self.assertIn(sync.page_url('portfolio') + '#clients', result)
        self.assertIn(sync.HOME + '#contact', result)

    def test_regeneration_repairs_generated_only(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory)
            manual = target / 'functions.php'
            manual.write_text('<?php // Hand-maintained sentinel\n')
            with contextlib.redirect_stdout(io.StringIO()):
                sync.sync_pages(ROOT, target)
                expected = {name: (target / name).read_bytes() for name, _ in sync.PAGE_MAP.values()}
                (target / 'page-knowledge.php').write_text('deliberately corrupted test fixture')
                sync.sync_pages(ROOT, target)
            self.assertTrue(all((target / name).read_bytes() == data for name, data in expected.items()))
            self.assertEqual(manual.read_text(), '<?php // Hand-maintained sentinel\n')

    def test_parent_relative_assets_reach_the_theme(self):
        """portfolio/index.html is one directory down and writes "../assets/".

        Unrewritten, that resolves to /assets/ under the /portfolio/ permalink,
        so every stylesheet and script on that page answered 404 with the HTML
        404 body -- a MIME error on top of a missing file. Eleven assets were
        broken this way in production.
        """
        for kind in ('css/page-chrome.min.css', 'js/lazy-bundle.min.js', 'images/logo/logo.webp'):
            converted = self.converted('../assets/' + kind)
            self.assertIn(sync.KDCV + '/assets/' + kind, converted)
            self.assertNotIn('"../assets/', converted)

    def test_no_generated_template_ships_a_parent_relative_asset(self):
        """The whole generated tree, not just the transform in isolation."""
        theme = ROOT / '_tooling/wp-theme/kohandezhcv'
        offenders = [p.name for p in sorted(theme.glob('*.php'))
                     if '"../assets/' in p.read_text(encoding='utf-8', errors='replace')]
        self.assertEqual(offenders, [], f'templates still carry ../assets/: {offenders}')


if __name__ == '__main__':
    unittest.main()
