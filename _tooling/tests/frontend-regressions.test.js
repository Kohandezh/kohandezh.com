const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

const functionsPhp = read('_tooling/wp-theme/kohandezhcv/functions.php');
const themeAvatarCss = read('assets/css/kohan-avatar.css');
const pluginAvatarCss = read('_tooling/wp-theme/kohan-avatar/assets/css/kohan-avatar.css');
const pluginAvatarJs = read('_tooling/wp-theme/kohan-avatar/assets/js/kohan-avatar.js');
const pluginAvatarPhp = read('_tooling/wp-theme/kohan-avatar/includes/class-kohan-avatar.php');
const legacyAvatarJs = read('assets/js/ai-pet.js');
assert.match(functionsPhp, /<h3 class="blog-local-title">/, 'WordPress blog cards use the section-level heading');
assert.doesNotMatch(functionsPhp, /<h5 class="blog-local-title">/, 'WordPress blog cards must not skip from the section h2 to h5');
assert.match(themeAvatarCss, /@media \(max-width: 767\.98px\)[\s\S]*?\.kdcv-pet-root\s*\{\s*display:\s*none\s*!important;/, 'legacy floating avatar stays off mobile content');
assert.match(pluginAvatarCss, /@media \(max-width: 560px\)[\s\S]*?\.kohan-avatar-root\s*\{\s*display:\s*none\s*!important;/, 'plugin floating avatar stays off mobile content');
assert.match(pluginAvatarJs, /matchMedia\("\(max-width: 560px\)"\)\.matches\) return;/, 'plugin skips its atlas runtime on mobile');
assert.match(pluginAvatarPhp, /media="\(min-width: 561px\)"/, 'atlas preload is restricted to desktop viewports');
assert.match(legacyAvatarJs, /matchMedia\("\(max-width: 767\.98px\)"\)\.matches/, 'legacy avatar skips mobile runtime and media');

const privacyHtml = read('privacy.html');
const privacyLocales = read('assets/data/i18n/privacy.json');
assert.doesNotMatch(privacyHtml, /هیچ کوکی‌ای تنظیم نمی‌کند|This site sets no cookies/, 'privacy page must not deny functional cookies');
assert.doesNotMatch(privacyLocales, /sets no cookies|no coloca ninguna cookie|ne dépose aucun cookie|setzt keine Cookies/i, 'translated privacy copy must not deny functional cookies');
assert.match(privacyHtml, /kdcv_lang/, 'privacy page identifies the language cookie');
assert.match(privacyLocales, /kdcv_lang/, 'translated privacy copy identifies the language cookie');

// A WordPress feed is ordered by publication, not the four-card static fixture.
const dom = new JSDOM('<html lang="en"><body><section class="section-blog"><article class="blog-local-item"><span class="blog-local-date" datetime="2025-10-26T09:00:00+03:30">October 26, 2025</span><a class="blog-local-link" href="/2025/10/26/gitex-2025/">Read</a></article><article class="blog-local-item"><span class="blog-local-date">Original date</span><a class="blog-local-link" href="/future-post/">Read</a></article></section></body></html>', {url:'https://kohandezh.com/', runScripts:'outside-only'});
dom.window.eval(read('assets/js/linkedin-content.js'));
assert.equal(dom.window.document.querySelector('.blog-local-date').textContent, 'October 26, 2025', 'must not assign May 11 by array index');
assert.equal(dom.window.document.querySelectorAll('.blog-local-date')[1].textContent, 'Original date', 'unknown posts keep their own dates');
dom.window.close();

const badgeDom = new JSDOM('<html lang="en"><body><section id="certificates"><div class="award-list"></div></section></body></html>', {url:'https://kohandezh.com/', runScripts:'outside-only'});
badgeDom.window.eval(read('assets/js/linkedin-content.js'));
assert.equal(badgeDom.window.document.querySelector('.badge-metro-title')?.tagName, 'H3', 'Badge Metro uses the section-level heading');
badgeDom.window.close();

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
  const booking = doc.querySelector('.contact-schedule');
  assert.ok(booking && !/pick a slot|یک زمان را انتخاب کنید|اختر موعدًا|Wählen Sie unten|Elige un horario|Choisissez un créneau|Aşağıdan bir zaman|在下方选择|下記から時間帯|Выберите удобное время/i.test(booking.textContent), `${locale}: booking copy does not promise an unavailable slot picker`);
  assert.ok(doc.querySelector('.section-tech-stack .tech-scale-note'), `${locale}: skill percentages carry a measurement disclaimer`);
  assert.equal(doc.querySelectorAll('h5.tes-text').length, 0, `${locale}: testimonial quotes are not headings`);
  assert.equal(doc.querySelectorAll('blockquote.tes-text').length, 3, `${locale}: authored testimonial quotes use blockquote semantics`);
  const unlabeledFields = [...doc.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]), textarea, select')]
    .filter(field => !(field.getAttribute('aria-label') || field.getAttribute('aria-labelledby') ||
      (field.id && doc.querySelector(`label[for="${field.id}"]`)) || field.closest('label')));
  assert.equal(unlabeledFields.length, 0, `${locale}: every visible form control has a static accessible name`);
}
console.log('PASS: ordered blog dates and 10-locale profile/contact regressions');

require('./credentials-regression.test.js');
