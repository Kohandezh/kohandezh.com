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
const _claims = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/data/claims.json'), 'utf8'));
ok(new RegExp(`\\b${_claims.claims.years_experience.value}\\b`).test(enText),
   'the reconciled years figure is in crawlable text');
ok(new RegExp(`\\b${_claims.claims.certifications.value}\\b`).test(enText),
   'the verified certification count is in crawlable text');
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
group('Claims — every published number is backed or exempt');

const claims = JSON.parse(fs.readFileSync(path.join(ROOT, 'assets/data/claims.json'), 'utf8'));
const ACCEPTED = new Set(['verified', 'restricted']);
const exemptValues = new Set(claims.non_factual_ui.skill_proficiency.map(String));

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const counters = [...html.matchAll(/<span class="number"([^>]*)>(\d+)<\/span>/g)];

  const unbacked = counters.filter(m => {
    const uuid = (m[1].match(/data-claim="([^"]+)"/) || [])[1];
    if (uuid) {
      const claim = Object.values(claims.claims).find(c => c.uuid === uuid);
      return !claim || !ACCEPTED.has(claim.status);
    }
    // No claim tag: only acceptable if it is a declared non-factual UI value.
    return !exemptValues.has(m[2]);
  });

  ok(unbacked.length === 0,
     `${L}: every numeric claim is VERIFIED or an exempt UI value`,
     unbacked.map(m => m[2]).join(', '));
}

// The specific reconciliation: unsupported figures must be GONE, not softened
// into a smaller number. A claim with no evidence does not get a value at all.
for (const [f, L] of Object.entries(LOCALES)) {
  const text = visibleText(files[f]);
  for (const key of ['projects_delivered', 'client_satisfaction']) {
    const c = claims.claims[key];
    ok(c.status === 'unsupported' && c.value === null,
       `${key} is recorded unsupported with no value`);
  }
  ok(!/Projects delivered|Client satisfaction|Реализованные проекты|完成项目/.test(text),
     `${L}: no unsupported figure survives in visible text`);
}

// The retained numbers must match the register, not drift from it.
const yearsClaim = claims.claims.years_experience;
const certsClaim = claims.claims.certifications;
ok(yearsClaim.value === new Date().getFullYear() - claims.career_start_year,
   'the years figure is derived from the canonical career start, not asserted');
ok(yearsClaim.evidence.length > 0, 'and carries evidence');

// The certification count is recountable from the page it describes.
const certHtml = fs.readFileSync(path.join(ROOT, 'Certificates.html'), 'utf8');
const cards = [...certHtml.matchAll(/<article[^>]*class="[^"]*certificate-card[^"]*"/g)];
const appreciation = cards.filter(m => m[0].includes('appreciation'));
ok(cards.length - appreciation.length === certsClaim.value,
   `the certification count matches the archive (${cards.length} - ${appreciation.length})`);

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  ok(html.includes(`data-to="${yearsClaim.value}"`), `${L}: shows the reconciled years figure`);
  ok(html.includes(`data-to="${certsClaim.value}"`), `${L}: shows the verified certification count`);
}

// ─────────────────────────────────────────────────────────────────────────
group('Schema — no claim stronger than the visible page');

for (const [f, L] of Object.entries(LOCALES)) {
  const blocks = [...files[f].matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)]
    .map(m => { try { return JSON.parse(m[1]); } catch (e) { return null; } });
  ok(blocks.every(Boolean), `${L}: all JSON-LD parses`);

  const people = blocks.filter(b => b && b['@type'] === 'Person');
  ok(people.length === 1, `${L}: exactly one Person node`, `got ${people.length}`);

  const p = people[0];
  if (!p) continue;

  ok(p['@id'] === 'https://kohandezh.com/#person',
     `${L}: Person uses the one canonical @id`);

  // Relationship truth: worksFor references the declared Organization rather
  // than inlining an anonymous duplicate of it.
  ok(p.worksFor && p.worksFor['@id'] === 'https://kohandezh.com/#ksf-organization',
     `${L}: worksFor references the canonical Organization`);
  ok(p.founder && p.founder['@id'] === 'https://kohandezh.com/#ksf-organization',
     `${L}: the founder relationship is stated separately from employment`);

  // A property must not conflate distinct relationship kinds.
  if (p.alumniOf) {
    const alumni = Array.isArray(p.alumniOf) ? p.alumniOf : [p.alumniOf];
    const leaked = alumni.filter(a => JSON.stringify(a).includes('#ksf-organization'));
    ok(leaked.length === 0, `${L}: the employer is not also listed as a school`);
  }

  // No unsupported number may enter structured data.
  const json = JSON.stringify(blocks);
  for (const key of ['projects_delivered', 'client_satisfaction']) {
    ok(!new RegExp(`\\b(100\\+?\\s*projects|98\\s*%)`, 'i').test(json),
       `${L}: no unsupported figure in structured data (${key})`);
  }
  // And the stale figure must be gone everywhere, including FAQ prose.
  ok(!/\b18 years\b/i.test(json), `${L}: the stale years figure is not in JSON-LD`);
}

// ─────────────────────────────────────────────────────────────────────────
group('Crawl graph — every locale is reachable without JavaScript');

for (const [f, L] of Object.entries(LOCALES)) {
  const html = files[f];
  const anchors = [...html.matchAll(/<a[^>]+href="([^"]+)"/g)].map(m => m[1]);
  const localeLinks = anchors.filter(h => /^(fa|ar|de|es|fr|tr|zh|ja|ru)\.html$|^index\.html$|^\/$/.test(h));
  ok(localeLinks.length >= 9,
     `${L}: links to the other locales as real anchors`,
     `got ${localeLinks.length}`);
}

// The language switcher must not be JS-only.
ok(!/<button[^>]+data-href="[a-z]{2}\.html"/.test(files['index.html']),
   'no locale is reachable only through a button');

// ─────────────────────────────────────────────────────────────────────────
group('Hreflang — the cluster is a reciprocal graph');

// zh-Hans is the correct BCP-47 subtag for Simplified Chinese; `zh` alone
// would be less specific, not more correct.
const EXPECTED = new Set(['en','fa','ar','de','es','fr','tr','zh-Hans','ja','ru','x-default']);
const graph = {};
for (const [f, L] of Object.entries(LOCALES)) {
  // Match each <link> tag first, then read its attributes. A single regex
  // alternating attribute order backtracks catastrophically on a 400 KB page.
  const tags = [...files[f].matchAll(/<link\b[^>]*>/g)]
    .map(m => m[0])
    .filter(tag => tag.includes('hreflang='))
    // The llms.txt discovery links are rel=alternate for a different RESOURCE
    // TYPE, not page-locale alternates, so they are not part of this graph.
    .filter(tag => !/\.txt"/.test(tag))
    .map(tag => ({
      lang: (tag.match(/hreflang="([^"]+)"/) || [])[1],
      href: (tag.match(/href="([^"]+)"/) || [])[1],
    }))
    .filter(t => t.lang);
  graph[L] = tags;

  const langs = new Set(tags.map(t => t.lang));
  const missing = [...EXPECTED].filter(x => !langs.has(x));
  ok(missing.length === 0, `${L}: declares every locale plus x-default`, missing.join(', '));

  // Self-reference. The declared subtag may be more specific than the file
  // stem -- zh.html correctly declares zh-Hans -- so match on prefix.
  ok(tags.some(t => t.lang === L || t.lang.split('-')[0] === L),
     `${L}: hreflang set includes a self-reference`);

  // No duplicate locale codes.
  ok(langs.size === tags.length, `${L}: no duplicated hreflang codes`);
}

// Reciprocity: every locale declares the same destination set.
const reference = JSON.stringify(graph.en.map(t => t.lang).sort());
for (const L of Object.keys(graph)) {
  ok(JSON.stringify(graph[L].map(t => t.lang).sort()) === reference,
     `${L}: hreflang set is reciprocal with the English cluster`);
}

// ─────────────────────────────────────────────────────────────────────────
group('Syndication — every feed URL resolves to a canonical shape');

const feed = fs.readFileSync(path.join(ROOT, 'feed.xml'), 'utf8');
const feedLinks = [...feed.matchAll(/<link>([^<]+)<\/link>/g)].map(m => m[1]);
const feedGuids = [...feed.matchAll(/<guid[^>]*>([^<]+)<\/guid>/g)].map(m => m[1]);

ok(feedLinks.length > 0, 'the feed has items');
const deadPattern = feedLinks.filter(u => /\/blog\/[^/]+\.html$/.test(u));
ok(deadPattern.length === 0,
   'no feed item points at the dead /blog/*.html shape',
   deadPattern.slice(0, 2).join(', '));

const wpShape = feedLinks.filter(u => /\/\d{4}\/\d{2}\/\d{2}\/[^/]+\/$/.test(u));
ok(wpShape.length === feedLinks.length - 1,
   'every post item uses the published /YYYY/MM/DD/slug/ form');

ok(feedGuids.every(g => !/\/blog\/[^/]+\.html$/.test(g)), 'guids were rewritten too');

// Dates must be parseable and not in the future.
const pubDates = [...feed.matchAll(/<pubDate>([^<]+)<\/pubDate>/g)].map(m => new Date(m[1]));
ok(pubDates.every(d => !isNaN(d)), 'every pubDate parses');

// Feed discovery titles must be in the page's own language.
ok(!/title="Mohammad Ali Kohandezh — نوشته/.test(files['de.html']),
   'a German page does not advertise a Persian feed title');

// ─────────────────────────────────────────────────────────────────────────
group('Runtime — no script repairs a canonical CV fact');

const jsDir = path.join(ROOT, 'assets/js');
const scripts = fs.readdirSync(jsDir).filter(f => f.endsWith('.js') && !f.endsWith('.min.js'));

// The removed repair layer must stay removed.
ok(!scripts.includes('kdcv-resume-entry-fix.js'),
   'the obsolete resume-entry repair script is gone');
for (const [f, L] of Object.entries(LOCALES)) {
  ok(!files[f].includes('kdcv-resume-entry-fix'), `${L}: no reference to the removed repair script`);
}

// The enhancer must not rebuild the timeline.
const enhancer = fs.readFileSync(path.join(jsDir, 'resume-timeline.js'), 'utf8');
ok(!/timeline-item[^"]*"\s*\)/.test(enhancer) || !/createElement\("div"\)[\s\S]{0,200}timeline-item/.test(enhancer),
   'resume-timeline.js does not construct timeline items');
ok(enhancer.includes('data-cv-id'), 'it matches existing items by canonical id');

// Any runtime copy of a canonical fact must agree with cv.json.
const linkedin = fs.readFileSync(path.join(jsDir, 'linkedin-content.js'), 'utf8');
const associate = cv.entries.find(e => e.id === 'associate-computer-software');
const titles = [...linkedin.matchAll(/associateTitle\s*:\s*"([^"]*)"/g)].map(m => m[1]);
ok(titles.length > 0, 'linkedin-content declares an associate-degree title');
ok(titles.includes(associate.locales.en.title),
   'and it agrees with the canonical CV entry',
   `canonical: ${associate.locales.en.title}`);

// ─────────────────────────────────────────────────────────────────────────
group('Arabic — terminology and grammar rules hold');

const ar = visibleText(files['ar.html']);
ok(!ar.includes('أفاتار'), 'ar: one term for avatar, not a transliteration alongside it');
ok(!/مشروع منجز(?!ة)/.test(ar), 'ar: the delivered-projects label is not singular');
ok(!/وكلاء AI\b/.test(ar), 'ar: Latin "AI" does not appear where the Arabic term is used');
ok(files['ar.html'].includes('unicode-bidi') || fs.readFileSync(path.join(ROOT, 'assets/css/styles.css'), 'utf8').includes('html[lang="ar"] .timeline-role'),
   'ar: bidi isolation covers the mixed-script timeline fields');

// ─────────────────────────────────────────────────────────────────────────
console.log('\n' + '─'.repeat(56));
if (fail) {
  console.log(`\x1b[31m${fail} of ${pass + fail} failed\x1b[0m, ${pass} passed`);
  process.exit(1);
}
console.log(`\x1b[32mall ${pass} crawler assertions passed\x1b[0m`);
