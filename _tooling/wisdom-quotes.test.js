/* wisdom-quotes.test.js — node test of the dataset + selection invariants.
 * No test framework (repo has none). Plain node assertions. Run: node this-file.
 * Tests the data contract + the algorithm invariants the spec requires:
 *   1 only verified&enabled selectable
 *   2 no repeat within last 5
 *   3 page→category mapping
 *   4 weighted selection stays within allowed categories
 *   5 empty categories don't crash
 *   6 malformed JSON doesn't break (validator rejects)
 *   11 history storage failure falls back safely
 */
"use strict";
var fs = require("fs");
var path = require("path");
var assert = require("assert");

var DATA_PATH = path.resolve(__dirname, "../assets/data/wisdom-quotes.fa.json");
var raw = JSON.parse(fs.readFileSync(DATA_PATH, "utf8"));

var passed = 0, failed = 0;
function ok(name, cond) { if (cond) { passed++; } else { failed++; console.log("  ✗ FAIL:", name); } }

// ---- validator (mirror of the module's validate()) ----
function validate(input) {
  if (!input || typeof input !== "object" || !Array.isArray(input.categories)) return null;
  var cats = [];
  for (var i = 0; i < input.categories.length; i++) {
    var c = input.categories[i];
    if (!c || !c.id || !Array.isArray(c.quotes)) continue;
    var weight = typeof c.weight === "number" ? c.weight : typeof c.weight_percent === "number" ? c.weight_percent : 0;
    var qs = [];
    for (var j = 0; j < c.quotes.length; j++) {
      var q = c.quotes[j];
      if (!q || q.verified !== true || q.enabled === false) continue;
      if (!q.id || !q.text || !q.author) continue;
      qs.push({ id: String(q.id), priority: typeof q.priority === "number" ? q.priority : 5 });
    }
    if (qs.length) cats.push({ id: c.id, weight: weight, quotes: qs });
  }
  return { categories: cats };
}

// ---- pure selector (mirror of module logic, deterministic rng for test) ----
function buildCandidates(ds, allowedIds) {
  var pool = allowedIds ? ds.categories.filter(function (c) { return allowedIds.indexOf(c.id) !== -1; }) : ds.categories;
  if (!pool.length) pool = ds.categories;
  var out = [];
  pool.forEach(function (c) { c.quotes.forEach(function (q) { out.push({ id: q.id, cat: c.id, catWeight: c.weight, priority: q.priority }); }); });
  return out;
}
function selectWith(ds, allowedIds, history, rng) {
  rng = rng || Math.random;
  var cand = buildCandidates(ds, allowedIds);
  if (!cand.length) return null;
  var fresh = cand.filter(function (c) { return history.indexOf(c.id) === -1; });
  var usePool = cand.length >= 6 && fresh.length ? fresh : cand;
  var byCat = {};
  usePool.forEach(function (c) { byCat[c.cat] = (byCat[c.cat] || 0) + Math.max(1, c.catWeight); });
  var total = 0; Object.keys(byCat).forEach(function (k) { total += byCat[k]; });
  var roll = rng() * total, acc = 0, chosen = Object.keys(byCat)[0];
  for (var k in byCat) { acc += byCat[k]; if (roll <= acc) { chosen = k; break; } }
  var inCat = usePool.filter(function (c) { return c.cat === chosen; });
  var ptot = 0; inCat.forEach(function (c) { ptot += Math.max(1, c.priority); });
  var roll2 = rng() * ptot, acc2 = 0, pick = inCat[0];
  for (var i = 0; i < inCat.length; i++) { acc2 += Math.max(1, inCat[i].priority); if (roll2 <= acc2) { pick = inCat[i]; break; } }
  return pick;
}

// ================= TESTS =================
console.log("wisdom-quotes dataset + selector tests\n");

// Test 1: only verified && enabled selectable
(function () {
  var ds = validate(raw);
  var allSelectable = [];
  ds.categories.forEach(function (c) { c.quotes.forEach(function (q) { allSelectable.push(q.id); }); });
  var allRaw = [];
  raw.categories.forEach(function (c) { c.quotes.forEach(function (q) {
    if (q.verified === true && q.enabled !== false) allRaw.push(q.id);
  }); });
  ok("T1 only verified&enabled selectable", allSelectable.length === allRaw.length && allSelectable.length === 25);
})();

// Test 6: malformed JSON rejected safely
(function () {
  ok("T6a malformed (no categories) → null", validate({}) === null);
  ok("T6b malformed (categories not array) → null", validate({ categories: "x" }) === null);
  ok("T6c malformed (quote missing text) → dropped", validate({ categories: [{ id: "x", weight: 1, quotes: [{ id: "a", author: "A", verified: true, enabled: true }] }] }).categories.length === 0);
  ok("T6d malformed (unverified dropped)", validate({ categories: [{ id: "x", weight: 1, quotes: [{ id: "a", author: "A", text: "t", verified: false, enabled: true }] }] }).categories.length === 0);
})();

// Test 5: empty categories don't crash
(function () {
  var ds = validate({ categories: [{ id: "empty", weight: 5, quotes: [] }] });
  ok("T5 empty category → no categories", ds.categories.length === 0);
  ok("T5 selectQuote on empty pool → null", selectWith(ds, null, []) === null);
})();

// Test 2: no repeat within last 5 (pool >= 6 forces fresh)
(function () {
  var ds = validate(raw);
  var history = [];
  var seen = {};
  var noRepeat = true;
  for (var i = 0; i < 25; i++) {
    var pick = selectWith(ds, null, history, function () { return (i * 7919 % 1000) / 1000; });
    if (!pick) { noRepeat = false; break; }
    if (history.indexOf(pick.id) !== -1) { noRepeat = false; break; }
    history.push(pick.id);
    if (history.length > 5) history.shift();
  }
  ok("T2 no quote repeats within rolling last-5 window", noRepeat);
})();

// Test 3 + 4: context → category mapping + weighted stays in allowed set
(function () {
  var ds = validate(raw);
  var ctx = { certificates: ["science_engineering", "management_entrepreneurship"], blog: ["iranian_wisdom", "psychology_human_behavior", "stoicism"] };
  Object.keys(ctx).forEach(function (page) {
    var allowed = ctx[page];
    var allInScope = true;
    for (var i = 0; i < 50; i++) {
      var pick = selectWith(ds, allowed, [], Math.random);
      if (!pick || allowed.indexOf(pick.cat) === -1) { allInScope = false; break; }
    }
    ok("T3/T4 [" + page + "] weighted selection stays within allowed categories", allInScope);
  });
})();

// Test 11: history storage failure falls back safely (simulate by ignoring history)
(function () {
  var ds = validate(raw);
  var pick = selectWith(ds, null, "BROKEN", Math.random); // non-array history
  ok("T11 broken (non-array) history → still returns a valid quote", pick && typeof pick.id === "string");
})();

// Bonus: weight distribution sanity (default → all categories by weight, 1000 samples)
(function () {
  var ds = validate(raw);
  var counts = {};
  for (var i = 0; i < 2000; i++) {
    var pick = selectWith(ds, null, [], Math.random);
    if (pick) counts[pick.cat] = (counts[pick.cat] || 0) + 1;
  }
  var iranian = (counts.iranian_wisdom || 0) / 20;
  var psych = (counts.psychology_human_behavior || 0) / 20;
  // irian_wisdom (25%) should clearly outweigh psychology (10%) in default context
  ok("T-weight default distribution favors higher-weight categories (iranian >> psychology)", iranian > psych * 1.8);
})();

console.log("\n---- " + passed + " passed, " + failed + " failed ----");
process.exit(failed ? 1 : 0);
