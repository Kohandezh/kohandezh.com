"""Regression: static navigation must not resolve below a WP page permalink."""
import importlib.util
import os
from pathlib import Path
import unittest
import contextlib
import io
import tempfile
import subprocess

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('sync', os.environ.get('KDCV_SYNC_TEST_SOURCE', str(ROOT / '_tooling/wp-theme/sync-from-static.py')))
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)


class Links(unittest.TestCase):
    def test_responsive_image_candidates_use_theme_urls(self):
        source = '<img src="assets/images/avatar/avatar-professional-w480.webp" srcset="assets/images/avatar/avatar-professional-w480.webp 480w, assets/images/avatar/avatar-professional-w800.webp 800w">'
        converted = sync.transform('<html><head></head><body>' + source + '</body></html>', 'test', False)
        self.assertIn(sync.KDCV + '/assets/images/avatar/avatar-professional-w480.webp 480w, ' + sync.KDCV + '/assets/images/avatar/avatar-professional-w800.webp 800w', converted)

    def test_portrait_preload_matches_display_candidates(self):
        import re
        for locale in ['index', 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja', 'ru']:
            html = (ROOT / (locale + '.html')).read_text()
            preload = re.search(r'imagesrcset="([^"]+)"', html)[1]
            portrait = re.search(r'<img class="profile-avatar-image"[^>]+>', html)[0]
            self.assertIn('srcset="' + preload + '"', portrait)
            for candidate in preload.split(', '):
                self.assertTrue((ROOT / candidate.split(' ')[0]).is_file())

    def test_pet_assets_only_when_plugin_does_not_own_pet(self):
        source = '<html><head><link rel="stylesheet" href="assets/css/kohan-avatar.min.css?v=1"></head><body><script src="assets/js/kohan-avatar.min.js?v=1" defer></script></body></html>'
        converted = sync.transform(source, 'test', False)
        for enabled in (False, True):
            prelude = '<?php function home_url($x=""){return $x;} function wp_head(){} function wp_footer(){} function wp_body_open(){} define("KDCV", "/theme"); class Kohan_Avatar {static function instance(){return new self;} function get_options(){return ["enabled" => ' + ('true' if enabled else 'false') + '];}} ?>'
            # Render only the guarded resource tags, avoiding unrelated template hooks.
            import re
            tags = re.findall(r'<\?php if \( ! class_exists\(\'Kohan_Avatar\'\).*?<\?php endif; \?>', converted)
            self.assertEqual(len(tags), 2)
            output = subprocess.check_output(['php'], input=prelude + ''.join(tags), text=True)
            self.assertEqual('kohan-avatar.min.js' in output, not enabled)
            self.assertEqual('kohan-avatar.min.css' in output, not enabled)

    def test_plugin_atlas_url_is_shared_and_initialization_is_single(self):
        script = r'''
const {JSDOM}=require('jsdom'); const fs=require('fs'); const assert=require('assert');
const dom=new JSDOM('<!doctype html><body></body>',{runScripts:'outside-only',pretendToBeVisual:true,url:'https://example.test/'});
const w=dom.window; const urls=[];
w.KohanAvatarConfig={assetBase:'/plugin/assets/kohan',atlasUrl:'/plugin/assets/kohan/spritesheet.webp?v=testhash',options:{enabled:true,chat:false,idleRangeMs:[999999,999999]}};
w.matchMedia=()=>({matches:false,addEventListener(){},addListener(){}});
w.addEventListener('error', e=>{throw e.error;});
w.Image=class {set src(v){urls.push(v)} addEventListener(){} };
const src=fs.readFileSync('_tooling/wp-theme/kohan-avatar/assets/js/kohan-avatar.js','utf8');
w.eval(src); w.eval(src); w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
assert.equal(w.document.querySelectorAll('.kohan-avatar-root').length,1);
assert(w.document.querySelector('.kohan-avatar-root').style.backgroundImage.includes(w.KohanAvatarConfig.atlasUrl));
assert.deepEqual(urls.filter(x=>x.includes('spritesheet.webp')),[w.KohanAvatarConfig.atlasUrl]);
assert.equal(typeof w.KohanAvatar.setMood,'function');
w.close();
'''
        subprocess.run(['node', '-e', script], cwd=ROOT, check=True)

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
