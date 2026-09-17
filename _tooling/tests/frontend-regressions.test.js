const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

// A WordPress feed is ordered by publication, not the four-card static fixture.
const dom = new JSDOM('<html lang="en"><body><section class="section-blog"><article class="blog-local-item"><span class="blog-local-date" datetime="2025-10-26T09:00:00+03:30">October 26, 2025</span><a class="blog-local-link" href="/2025/10/26/gitex-2025/">Read</a></article><article class="blog-local-item"><span class="blog-local-date">Original date</span><a class="blog-local-link" href="/future-post/">Read</a></article></section></body></html>', {url:'https://kohandezh.com/', runScripts:'outside-only'});
dom.window.eval(read('assets/js/linkedin-content.js'));
assert.equal(dom.window.document.querySelector('.blog-local-date').textContent, 'October 26, 2025', 'must not assign May 11 by array index');
assert.equal(dom.window.document.querySelectorAll('.blog-local-date')[1].textContent, 'Original date', 'unknown posts keep their own dates');
dom.window.close();

for (const locale of ['index','fa','ar','de','es','fr','tr','zh','ja','ru']) {
  const html = read(locale + '.html');
  const doc = new JSDOM(html).window.document;
  const profile = doc.querySelector('.user-social');
  for (const href of ['https://t.me/kohandezh','https://ble.ir/kohandezh','https://eitaa.com/kohandezhh','https://wa.me/18106662283']) {
    assert.equal(profile.querySelectorAll(`a[href="${href}"]`).length, 1, `${locale}: exactly one ${href}`);
  }
  assert.equal(profile.querySelectorAll('a[href^="mailto:"],a[href="https://ksf.ir"]').length, 0, `${locale}: no extra profile buttons`);
  assert.ok(doc.querySelector('.action-group a[href="#contact"]'), `${locale}: conversation targets contact`);
  assert.ok(doc.querySelector('[data-cv-id="national-ai-platform"]'), `${locale}: preserve Sako role`);
  assert.ok(!html.includes('دکتریی'));
}
console.log('PASS: ordered blog dates and 10-locale profile/contact regressions');

require('./credentials-regression.test.js');
