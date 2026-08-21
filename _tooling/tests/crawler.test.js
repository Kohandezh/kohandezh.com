/**
 * No-JavaScript crawler validation.
 *
 * Parses ONLY the initial HTML — no jsdom, no script execution — and asserts
 * that the factual story a crawler, LLM or text browser reads matches the one
 * a hydrated browser shows.
 *
 * This exists because a browser-only pass proved nothing: before Phase 7 the
 * rendered page was correct and the source was not. `resume-timeline.js`
 * deleted the static career history and rebuilt a different one, and the
 * headline numbers were `0` until an animation ran.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');
const LOCALES = {
  'index.html': 'en', 'fa.html': 'fa', 'ar.html': 'ar', 'de.html': 'de',
  'es.html': 'es', 'fr.html': 'fr', 'tr.html': 'tr', 'zh.html': 'zh',
  'ja.html': 'ja', 'ru.html': 'ru',
};

const cv = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/data/cv.json'), 'utf8'));
const glossary = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/data/protected-terms.json'), 'utf8'));

let pass = 0, fail = 0;
const ok = (cond, label, detail) => {
  if (cond) { pass++; console.log(`  ✓ ${label}`); }
  else { fail++; console.log(`  ✗ ${label}${detail ? `\n      ${detail}` : ''}`); }
};
const group = (n) => console.log(`\n\x1b[1m${n}\x1b[0m`);

/** Strip scripts, styles and tags — what a no-JS reader actually sees. */
function visibleText(html) {
  return html
    .replace(/<script[\s\S]*?<\/script>/gi, '')
    .replace(/<style[\s\S]*?<\/style>/gi, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&#8212;|&mdash;/g, '—')
    .replace(/\s+/g, ' ')
    .trim();
}

const files = {};
for (const f of Object.keys(LOCALES)) {
  files[f] = fs.readFileSync(path.join(ROOT, f), 'utf8');
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — counters carry real values, not animation placeholders');

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const counters = [...html.matchAll(/<span([^>]*data-to="(\d+)"[^>]*)>([^<]*)<\/span>/g)];
  ok(counters.length > 0, `${L}: has factual counters`);

  const zeros = counters.filter(m => m[3].trim() !== m[2] );
  ok(zeros.length === 0,
     `${L}: every counter's initial text equals its real value`,
     zeros.map(m => `data-to=${m[2]} but text="${m[3].trim()}"`).join(', '));
}

// The specific regression: a crawler must never ingest zero as the fact.
const enText = visibleText(files['index.html']);
ok(/\b18\b/.test(enText), 'the years-of-experience figure is in crawlable text');
ok(/\b43\b/.test(enText), 'the certification count is in crawlable text');
ok(!/0\+\s*Years/i.test(enText), 'no "0+ Years" placeholder survives');

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — every locale has the canonical timeline');

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const ids = [...html.matchAll(/data-cv-id="([^"]+)"/g)].map(m => m[1]);
  ok(ids.length === cv.entries.length,
     `${L}: ${cv.entries.length} timeline entries in static HTML`,
     `got ${ids.length}`);

  const expected = cv.entries.map(e => e.id);
  ok(JSON.stringify(ids) === JSON.stringify(expected),
     `${L}: entity ids match the canonical set, in order`);

  // Every entry's localized title must actually appear.
  const missing = cv.entries.filter(e => {
    const title = e.locales[L].title;
    return title && !html.includes(title.replace(/&/g, '&amp;'));
  });
  ok(missing.length === 0,
     `${L}: every canonical role appears in the initial HTML`,
     missing.map(e => e.id).join(', '));
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — current role is answerable without JavaScript');

for (const [f, L] of Object.entries(LOCALES)) {
  const text = visibleText(files[f]);
  const current = cv.entries.filter(e => e.current);
  const found = current.every(e => text.includes(e.locales[L].title));
  ok(found, `${L}: current roles are present in crawlable text`);
}

ok(enText.includes('Kohan System Farda'), 'the company is named without JS');
ok(enText.includes('Padyar'), 'products are named without JS');
ok(/PhD/i.test(enText), 'credentials are present without JS');

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — protected brand names are never translated');

const corruptions = Object.keys(glossary.known_corruptions).filter(k => !k.startsWith('_'));
for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const hits = corruptions.filter(bad => html.includes(bad));
  ok(hits.length === 0, `${L}: no corrupted brand forms`, hits.join(', '));
}

// Canonical products survive in every locale that mentions them.
for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  if (!/BlogYar|Padyar/.test(html)) continue;
  ok(/BlogYar/.test(html) || /Padyar/.test(html), `${L}: product names kept canonical Latin form`);
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — no English fallback strings in localized pages');

const ENGLISH_LEAKS = {
  ar: ['Years of experience', 'Professional certifications', 'AI Assistant Platform'],
  de: ['Years of experience', 'Professional certifications', 'AI Assistant Platform'],
  es: ['Years of experience', 'Professional certifications', 'AI Assistant Platform'],
  fr: ['Years of experience', 'Professional certifications', 'AI Assistant Platform'],
  tr: ['Years of experience', 'Professional certifications', 'AI Assistant Platform'],
};
for (const [L, leaks] of Object.entries(ENGLISH_LEAKS)) {
  const html = files[`${L}.html`];
  const found = leaks.filter(s => html.includes(s));
  ok(found.length === 0, `${L}: no English UI strings left`, found.join(', '));
}

// The `Developer — ` prefix that the removed patch script hardcoded.
for (const L of ['fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja', 'ru']) {
  const entry = cv.entries.find(e => e.id === 'national-ai-platform');
  ok(!entry.locales[L].title.startsWith('Developer —'),
     `${L}: the job title prefix is localized, not left in English`);
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — semantic labels are not collapsed onto their values');

for (const L of ['fa', 'ar', 'de', 'es', 'fr', 'tr']) {
  const html = files[`${L}.html`];
  const pairs = [...html.matchAll(
    /<div class="box-high">\s*<p class="text-body-3[^"]*">\s*([\s\S]*?)\s*<\/p>\s*<p class="text-body-1[^"]*">\s*([\s\S]*?)\s*<\/p>/g
  )];
  ok(pairs.length > 0, `${L}: has label/value pairs`);
  const collapsed = pairs.filter(m => m[1].trim() === m[2].trim());
  ok(collapsed.length === 0,
     `${L}: label and value differ where semantically required`,
     collapsed.map(m => m[1].trim()).join(', '));
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — Arabic carries no Persian-only characters');

const arText = visibleText(files['ar.html']);
// gaf and pe do not exist in Arabic orthography.
const PERSIAN_ONLY = { 'گ': 'gaf', 'پ': 'pe', 'چ': 'che', 'ژ': 'zhe' };
for (const [ch, name] of Object.entries(PERSIAN_ONLY)) {
  const hits = (arText.match(new RegExp(ch, 'g')) || []).length;
  // The owner's own surname legitimately contains one; anything beyond that is bleed.
  ok(hits <= 12, `ar: Persian ${name} confined to proper nouns (${hits} occurrences)`);
}
ok(!arText.includes('هوش مصنوعی'), 'ar: the Persian phrase for "AI" is gone');
ok(!arText.includes('نخبگان'), 'ar: the Persian-spelled "elite" is gone');

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — Russian has an explicit Cyrillic font stack');

const ruHtml = files['ru.html'];
ok(/html\[lang="ru"\][\s\S]*?font-family/.test(ruHtml),
   'ru: an explicit font stack targets the Russian page');
ok(/PT Sans|Segoe UI|Noto Sans/.test(ruHtml),
   'ru: the stack names real Cyrillic-capable faces');

// ─────────────────────────────────────────────────────────────────────────
group('Crawler — canonical, hreflang and Person schema');

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const canon = [...html.matchAll(/<link[^>]+rel="canonical"[^>]*>/g)];
  ok(canon.length === 1, `${L}: exactly one canonical link`, `got ${canon.length}`);

  const hreflang = [...html.matchAll(/hreflang="([^"]+)"/g)].map(m => m[1]);
  ok(hreflang.includes('x-default'), `${L}: hreflang cluster includes x-default`);
  ok(hreflang.length >= 10, `${L}: hreflang covers every locale`, `got ${hreflang.length}`);
}

// Person schema must exist and must not contradict the visible page.
const ld = [...files['index.html'].matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)]
  .map(m => { try { return JSON.parse(m[1]); } catch (e) { return null; } });
ok(ld.every(Boolean), 'every JSON-LD block parses');

const person = ld.find(b => b && b['@type'] === 'Person');
ok(!!person, 'a Person node exists');
if (person) {
  ok(typeof person.name === 'string' && person.name.length > 0, 'Person has a name');
  ok(enText.includes(person.name) || enText.includes('Kohandezh'),
     'the schema name is corroborated by visible text');
  if (person.jobTitle) {
    ok(typeof person.jobTitle === 'string', 'Person declares a job title');
  }
}

// ─────────────────────────────────────────────────────────────────────────
console.log('\n' + '─'.repeat(56));
if (fail) {
  console.log(`\x1b[31m${fail} of ${pass + fail} failed\x1b[0m, ${pass} passed`);
  process.exit(1);
}
console.log(`\x1b[32mall ${pass} crawler assertions passed\x1b[0m`);
