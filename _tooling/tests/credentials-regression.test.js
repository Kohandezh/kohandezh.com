const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const locales = ['en', 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja', 'ru'];
const cv = JSON.parse(read('assets/data/cv.json'));
const printable = JSON.parse(read('_tooling/cv/cv-data.json'));

// Only the second-doctorate/candidate claim was withdrawn by the owner.
// Test prose as well as entry IDs so the old claim cannot return in a biography,
// metadata, an unkeyed runtime element, or the printable CV's separate dataset.
const retiredClaim = /phd-candidate|ph\.?d\.?\s+candidate|second\s+doctorate|دکتر(?:ای|ی+)[\s\u200c]+دوم|دومین\s+مقطع\s+دکتری|دكتوراه\s+ثانية|الدكتوراه\s+الثانية|مرشح\s+(?:ل(?:نيل\s+)?)?دكتوراه|Doktorand|zweite[nr]?\s+(?:Promotion|Promotionsstudium|Doktortitel)|segundo\s+doctorado|candidato\s+a\s+doctor(?:ado)?|second\s+doctorat|doctorant|ikinci\s+(?:bir\s+)?doktora|doktora\s+adayı|第二个博士|在读博士生|2つ目の博士|博士課程在籍|вторую\s+докторскую|соискатель\s+степени\s+phd/iu;
const assertNoRetiredClaim = (text, label) => assert.doesNotMatch(text, retiredClaim, `${label}: second doctorate must stay removed`);

assertNoRetiredClaim(JSON.stringify(cv), 'canonical timeline data');
assertNoRetiredClaim(JSON.stringify(printable), 'printable CV data');
assert.equal(cv.entries.filter(entry => entry.id === 'phd-it-management').length, 1, 'retain exactly one original doctorate entry');
const doctorate = cv.entries.find(entry => entry.id === 'phd-it-management');
assert.equal(doctorate.type, 'education');
assert.equal(doctorate.current, false, 'the retained doctorate is completed, not a candidate role');
assert.equal(doctorate.locales.en.title, 'PhD — IT Management, Smart Business');
assert.equal(doctorate.locales.en.date, '2018 - 2023');
assert.equal(cv.entries.filter(entry => entry.id === 'national-ai-platform').length, 1);
const sako = cv.entries.find(entry => entry.id === 'national-ai-platform');
assert.equal(sako.type, 'work');
assert.equal(sako.locales.en.title, 'Developer — National Open-Source AI Platform');

const printableDoctorates = printable.education.filter(entry => /\bPhD\b/i.test(entry.degree.en));
assert.equal(printableDoctorates.length, 1, 'printable CV retains exactly the original doctorate');
assert.equal(printableDoctorates[0].degree.en, 'PhD, IT Management — Smart Business');
assert.equal(printableDoctorates[0].school, 'Islamic Azad University, Central Tehran Branch');
assert.equal(printableDoctorates[0].period.en, '2018 – 2023');
assert.equal(printable.experience.filter(entry => entry.org === 'Sako').length, 1);
assert.equal(printable.experience.find(entry => entry.org === 'Sako').role.en, 'Developer — National Open-Source AI Platform');

function assertRetainedRoles(doc, locale, label) {
  for (const entry of [doctorate, sako]) {
    const nodes = doc.querySelectorAll(`[data-cv-id="${entry.id}"]`);
    assert.equal(nodes.length, 1, `${label}: exactly one ${entry.id}`);
    assert.equal(nodes[0].querySelector('.timeline-role').textContent.trim(), entry.locales[locale].title,
      `${label}: preserve the localized ${entry.id} title`);
    assert.equal(nodes[0].querySelector('.timeline-desc').textContent.trim(), entry.locales[locale].desc,
      `${label}: preserve the localized ${entry.id} description`);
  }
  assert.equal(doc.querySelectorAll('[data-cv-id="phd-candidate"]').length, 0, `${label}: no retired entry`);
  assertNoRetiredClaim(doc.documentElement.textContent, label);
}

for (const locale of locales) {
  const file = `${locale === 'en' ? 'index' : locale}.html`;
  const html = read(file);
  assertNoRetiredClaim(html, file);
  const staticDom = new JSDOM(html);
  assertRetainedRoles(staticDom.window.document, locale, file);
  const education = staticDom.window.document.querySelector('#education').outerHTML;
  const expectedIds = [...staticDom.window.document.querySelectorAll('#education [data-cv-id]')]
    .map(node => node.dataset.cvId);
  staticDom.window.close();

  // Execute both source and deployable-minified enhancements without any
  // browser network requests. Neither may recreate the withdrawn doctorate.
  for (const suffix of ['', '.min']) {
    const dom = new JSDOM(`<!doctype html><html lang="${locale}"><body>${education}</body></html>`, {
      url: 'https://kohandezh.com/', runScripts: 'outside-only', pretendToBeVisual: true
    });
    dom.window.eval(read(`assets/js/linkedin-content${suffix}.js`));
    dom.window.eval(read(`assets/js/resume-timeline${suffix}.js`));
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    assertRetainedRoles(dom.window.document, locale, `${file}, runtime${suffix || ' source'}`);
    assert.deepEqual([...dom.window.document.querySelectorAll('#education [data-cv-id]')].map(node => node.dataset.cvId),
      expectedIds, `${file}: runtime keeps the canonical career entries intact`);
    dom.window.close();
  }
}

// A small negative control protects the guard itself against an empty or
// accidentally over-narrow pattern, without changing any working-tree files.
for (const oldClaim of ['second doctorate', 'دکتری دوم', 'دكتوراه ثانية', 'zweiten Promotionsstudium',
  'segundo doctorado', 'second doctorat', 'ikinci bir doktora', '第二个博士学位', '2つ目の博士号', 'вторую докторскую степень']) {
  assert.throws(() => assertNoRetiredClaim(oldClaim, 'negative control'), assert.AssertionError);
}

console.log('PASS: second-doctorate removal in 2 CV datasets, 10 static locales and 20 runtime checks; original doctorate and Sako role preserved');
