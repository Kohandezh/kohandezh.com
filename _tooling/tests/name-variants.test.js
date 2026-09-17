// One name, many spellings -- and three places that have to agree about them.
//
// A search or a model that meets "محمد کهن دژ", "محمدعلی کوهندژ" or
// "Mohammadali Kohandezh" has to be able to resolve it to this person. Nothing
// on the site said so: the Person entity carried only the Latin spelling, and
// the Persian page's structured data carried no Persian name at all.
//
// The variants now live in assets/data/identity.json and are mirrored into
// schema.org alternateName in ten HTML files and into the ten llms.txt files.
// These assertions exist so the three copies cannot drift.

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

const identity = JSON.parse(read('assets/data/identity.json'));
const aka = identity.canonical.name.aka;

assert.ok(Array.isArray(aka) && aka.length >= 20, 'identity.json carries the variant list');
assert.equal(new Set(aka).size, aka.length, 'no duplicate variants');
for (const v of aka) assert.ok(v.trim() === v && v.length > 0, `"${v}" is trimmed and non-empty`);

// The canonical spellings must be covered, in every script the site serves.
const mustCover = [
  'محمدعلی کهن‌دژ', // ZWNJ, the owner's own spelling
  'محمدعلی کهندژ',        // joined
  'محمد علی کهن دژ',      // fully spaced
  'محمدعلی کوهندژ',  // the kuh/kooh misspelling
  'محمد کهن‌دژ',                    // given name dropped
  'Mohammadali Kohandezh',
  'Mohammad Kohandezh',
  'Kohandezh',
];
for (const v of mustCover) assert.ok(aka.includes(v), `variant list covers ${v}`);

const LOCALES = ['index','fa','ar','de','es','fr','tr','zh','ja','ru'];

for (const loc of LOCALES) {
  const html = read(loc + '.html');
  const person = html.slice(html.indexOf('"@id": "https://kohandezh.com/#person"'));
  const m = person.match(/"alternateName": (\[[\s\S]*?\]),\n/);
  assert.ok(m, `${loc}.html: Person carries alternateName`);
  assert.deepEqual(JSON.parse(m[1]), aka, `${loc}.html: alternateName matches identity.json`);

  // The point of the exercise: a Persian query has a Persian string to match.
  assert.ok(/[؀-ۿ]/.test(m[1]), `${loc}.html: Person schema carries Perso-Arabic spellings`);
}

for (const loc of ['llms', 'fa-llms', 'ar-llms', 'de-llms', 'es-llms', 'fr-llms', 'tr-llms', 'zh-llms', 'ja-llms', 'ru-llms']) {
  const txt = read(loc + '.txt');
  for (const v of mustCover) assert.ok(txt.includes(v), `${loc}.txt lists ${v}`);
}

console.log(`PASS: ${aka.length} name spellings agree across identity.json, 10 Person schemas and 10 llms files`);
