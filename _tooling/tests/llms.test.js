#!/usr/bin/env node
/**
 * Regression lock for the machine-readable identity.
 *
 * The ten *-llms.txt files are what AI crawlers read. When they drifted from
 * index.html, the identity a machine resolved was a positioning generation
 * behind the identity a human read — and nothing in the build noticed for
 * months, because ten hand-maintained copies have no single thing to check
 * against.
 *
 * These assertions are that thing. Each one locks a defect that actually
 * shipped:
 *
 *   1. all ten carried an infrastructure-only title after index.html moved on
 *   2. five of ten asserted a doctorate no credential record supports
 *   3. every one omitted Russian from its own language list
 *   4. the blog was claimed to be in nine languages; it is ten
 *   5. ru-llms.txt had drifted to .html URLs and lost both phone numbers
 *   6. fa-llms.txt translated product names the glossary marks never_translate
 *
 * Run: node _tooling/tests/llms.test.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
const json = (p) => JSON.parse(read(p));

let pass = 0;
let fail = 0;
let group = '';

function g(name) {
  group = name;
  console.log(`\n\x1b[1m${name}\x1b[0m`);
}
function ok(cond, label, detail) {
  if (cond) {
    pass++;
    console.log(`  \x1b[32m✓\x1b[0m ${label}`);
  } else {
    fail++;
    console.log(`  \x1b[31m✗\x1b[0m ${label}${detail ? `\n      \x1b[33m${detail}\x1b[0m` : ''}`);
  }
}
const eq = (a, b, label) => ok(a === b, label, `expected ${JSON.stringify(b)}, got ${JSON.stringify(a)}`);

const identity = json('assets/data/identity.json');
const protectedTerms = json('assets/data/protected-terms.json');
const canonical = identity.canonical;
const locales = canonical.locales;
const files = Object.fromEntries(locales.map((l) => [l.code, read(l.file)]));
const indexHtml = read('index.html');

// The Person node from the live page. Everything below compares against this
// rather than against a copy of it — a test that compared identity.json to
// itself would pass while the site said something else.
function personNode() {
  const blocks = indexHtml.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g) || [];
  for (const b of blocks) {
    const body = b.replace(/^<script[^>]*>/, '').replace(/<\/script>$/, '');
    let data;
    try { data = JSON.parse(body); } catch { continue; }
    const nodes = Array.isArray(data) ? data : (data['@graph'] || [data]);
    for (const n of nodes) {
      if (n && String(n['@type'] || '').includes('Person')) return n;
    }
  }
  return null;
}
const person = personNode();

// ─────────────────────────────────────────────────────────────────────────
g('Canonical identity agrees with the live HTML');

ok(person !== null, 'index.html carries a Person node in JSON-LD');
eq(canonical.job_title, person && person.jobTitle,
  'identity.json job_title is byte-identical to the page jobTitle');
eq(canonical.name.latin, person && person.name, 'the canonical name matches');
eq(
  canonical.contact.email,
  person && String(person.email || '').replace(/^mailto:/, ''),
  'the canonical email matches'
);

// Phones: the page stores them unspaced, the llms files render them spaced.
// Comparing on digits alone is what makes that difference a formatting choice
// rather than a discrepancy.
const digits = (s) => String(s).replace(/\D/g, '');
const pagePhones = ((person && person.telephone) || []).map(digits).sort();
const canonPhones = canonical.contact.phones.map(digits).sort();
eq(JSON.stringify(canonPhones), JSON.stringify(pagePhones), 'both canonical phone numbers match the page');

// The credential set — the anchor for the "second doctorate" regression.
const pageCreds = ((person && person.hasCredential) || []).length;
eq(canonical.credentials.length, pageCreds,
  'identity.json lists exactly as many credentials as the page');
eq(canonical.credentials.filter((c) => c.level === 'Doctorate').length, 1,
  'exactly one doctorate is recorded');

// ─────────────────────────────────────────────────────────────────────────
g('Every locale is rendered from the canonical source');

eq(locales.length, 10, 'ten locales are declared');

for (const loc of locales) {
  const body = files[loc.code];
  ok(/Generated from|Generiert|Généré|Generado|Сгенерировано|生成|تولیدشده|مُولَّد|üretildi/.test(body),
    `${loc.file} is marked generated, not hand-maintained`);
}

// (1) Primary role semantics. Every locale must assert the advisory position;
// none may still present the infrastructure-only identity as the title.
const QUANTUM = /quantum|kuantum|cuántic|quantique|Quanten|کوانت|كمّي|量子|кванто/i;
const AI_TOKEN = /\bAI\b|IA|KI|yapay zekâ|هوش مصنوعی|الذكاء الاصطناعي|人工智能|ИИ/i;

for (const loc of locales) {
  const titleLine = files[loc.code].split('\n').find((l) => /^- [^:]+: .*/.test(l) && QUANTUM.test(l));
  ok(!!titleLine, `${loc.code}: the title line states the quantum-readiness position`);
  ok(AI_TOKEN.test(files[loc.code]), `${loc.code}: and names AI`);
}

// The foundation must NOT have been erased in the repositioning.
const FOUNDATION = /infrastructure|Infrastruktur|infraestructura|altyapı|زیرساخت|البنية التحتية|基础设施|インフラ|инфраструктур/i;
for (const loc of locales) {
  ok(FOUNDATION.test(files[loc.code]),
    `${loc.code}: enterprise infrastructure survives as biography, not erased`);
}

// ─────────────────────────────────────────────────────────────────────────
g('Unsupported credentials cannot reappear');

// (2) THE regression. Five of ten shipped this.
const SECOND_DOCTORATE = [
  /second doctorate/i, /zweite Promotion/i, /segundo doctorado/i, /deuxième doctorat|second doctorat/i,
  /ikinci doktora/i, /второй доктор|вторая доктор/i, /第二个博士|第二博士/, /二つめの博士|二つ目の博士/,
  /دکتری دوم|دکتریی دوم/, /دكتوراه ثانية/,
];
for (const loc of locales) {
  const hit = SECOND_DOCTORATE.find((re) => re.test(files[loc.code]));
  ok(!hit, `${loc.code}: no unsupported "second doctorate" assertion`, hit && String(hit));
}

// And the source cannot smuggle it back either.
ok(!/in progress|laufend|en curso|en cours|sürüyor|в процессе|在读|在籍/i.test(JSON.stringify(identity.locales)),
  'no locale string asserts an in-progress degree');

// ─────────────────────────────────────────────────────────────────────────
g('All locales point at valid canonical resources');

// (5) The Russian .html drift, generalised to every locale.
for (const loc of locales) {
  const htmlUrls = (files[loc.code].match(/kohandezh\.com\/[a-z]{2}\.html/g) || []);
  eq(htmlUrls.length, 0, `${loc.code}: no legacy .html locale URLs`);
}

const validPaths = new Set([
  ...locales.map((l) => canonical.site + l.path),
  ...Object.values(canonical.resources).map((p) => canonical.site + p),
]);
for (const loc of locales) {
  const urls = files[loc.code].match(/https:\/\/kohandezh\.com[^\s)]*/g) || [];
  const bad = urls
    .map((u) => u.replace(/#service$/, ''))
    .filter((u) => !validPaths.has(u));
  eq(bad.length, 0, `${loc.code}: every kohandezh.com URL is a declared canonical resource`,
    bad.slice(0, 3).join(', '));
}

// Nothing may concatenate host and path without a separator — the bug the
// first render of this builder actually produced.
for (const loc of locales) {
  ok(!/kohandezh\.com[a-z]/.test(files[loc.code]),
    `${loc.code}: no malformed host+path concatenation`);
}

// (3) Every locale lists every OTHER locale. Russian was missing from all ten.
for (const loc of locales) {
  for (const other of locales) {
    if (other.code === loc.code) continue;
    ok(files[loc.code].includes(canonical.site + other.path),
      `${loc.code}: links to the ${other.code} locale`);
  }
}

// ─────────────────────────────────────────────────────────────────────────
g('Language count is consistent and true');

// (4) The blog claimed nine. There are ten CV locales and the in-place
// dictionaries resolve in ten, so ten is the honest number.
const cvPages = fs.readdirSync(ROOT)
  .filter((f) => /^(index|fa|ar|de|es|fr|tr|zh|ja|ru)\.html$/.test(f));
eq(cvPages.length, 10, 'ten CV pages exist on disk');
eq(locales.length, cvPages.length, 'the declared locale count matches the pages on disk');

const NINE = /\bnine\b|neun|nueve|neuf|dokuz|девят|九|9 言語|نه زبان|تسع/i;
for (const loc of locales) {
  ok(!NINE.test(files[loc.code]), `${loc.code}: does not undercount the languages as nine`);
}

// ─────────────────────────────────────────────────────────────────────────
g('Protected terminology is unchanged in every locale');

// (6) fa-llms.txt had rendered Padyar/HomaYar/NetYar in Persian script. The
// glossary marks product and company names never_translate.
const corruptions = Object.keys(protectedTerms.known_corruptions).filter((k) => !k.startsWith('_'));
for (const loc of locales) {
  for (const bad of corruptions) {
    ok(!files[loc.code].includes(bad),
      `${loc.code}: no known corruption "${bad}"`);
  }
}

// Where a product IS named, it must be named in canonical Latin.
const PRODUCT_FA = { 'پادیار': 'Padyar', 'همایار': 'HomaYar', 'نت‌یار': 'NetYar', 'بلاگ‌یار': 'BlogYar' };
for (const loc of locales) {
  for (const [wrong, right] of Object.entries(PRODUCT_FA)) {
    ok(!files[loc.code].includes(wrong),
      `${loc.code}: product name "${right}" is not transliterated`);
  }
}

// The company MAY be transliterated, but only into the forms the glossary
// explicitly approves — a machine translator never decides brand identity.
const approved = protectedTerms.locale_transliteration;
for (const loc of locales) {
  ok(files[loc.code].includes(canonical.company.name),
    `${loc.code}: the canonical company name appears in Latin`);
  const local = approved[loc.code] && approved[loc.code]['Kohan System Farda'];
  if (local && files[loc.code].includes(local)) {
    ok(true, `${loc.code}: uses the glossary-approved transliteration alongside it`);
  }
}

// ─────────────────────────────────────────────────────────────────────────
g('The rendered files match the canonical source exactly');

const { execFileSync } = require('child_process');
let drift = '';
try {
  execFileSync('python3', [path.join(ROOT, '_tooling/llms/build.py'), '--check'], { encoding: 'utf8' });
} catch (e) {
  drift = String((e.stderr || '') + (e.stdout || '')).trim();
}
ok(drift === '', 'build.py --check reports no file out of date', drift);

// ─────────────────────────────────────────────────────────────────────────
console.log('\n' + '─'.repeat(56));
if (fail) {
  console.log(`\x1b[31m${fail} of ${pass + fail} failed\x1b[0m, ${pass} passed`);
  process.exit(1);
}
console.log(`\x1b[32mall ${pass} llms assertions passed\x1b[0m`);
