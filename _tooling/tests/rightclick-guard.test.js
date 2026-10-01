// The right-click guard has TWO implementations and they must agree.
//
// Fourteen pages carry a hand-written inline handler (localized toast + speech
// + avatar cue). The KDCV Right-Click Guard plugin carries a second, plain one.
// Turning the plugin off used to remove only the plugin's listener, so the
// inline handler kept blocking the context menu and the setting looked dead.
// The inline handler now reads window.KDCV_RIGHTCLICK_GUARD; these tests keep
// it reading it.

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = f => fs.readFileSync(path.join(root, f), 'utf8');

const MARKER = 'kdcv-nocontext-toast';
const GUARD = /<script>\s*\(function\(\)\{\s*function msg\(lang\)\{[\s\S]*?\}\)\(\);\s*<\/script>/;

const PAGES = ['index.html','fa.html','ar.html','de.html','es.html','fr.html','tr.html',
               'zh.html','ja.html','ru.html','PSN.html','Certificates.html','videos.html','404.html'];
const TEMPLATES = fs.readdirSync(path.join(root, '_tooling/wp-theme/kohandezhcv'))
  .filter(f => f.endsWith('.php'))
  .map(f => '_tooling/wp-theme/kohandezhcv/' + f)
  .filter(f => read(f).includes(MARKER));

assert.equal(TEMPLATES.length, 13, 'ten locales + PSN + Certificates + 404 ship the inline guard');

for (const file of [...PAGES, ...TEMPLATES]) {
  const src = read(file);
  assert.ok(src.includes(MARKER), `${file}: inline guard present`);

  const handler = src.slice(src.indexOf("document.addEventListener('contextmenu'"));
  const gate = handler.indexOf('window.KDCV_RIGHTCLICK_GUARD === false');
  const block = handler.indexOf('e.preventDefault()');
  assert.notEqual(gate, -1, `${file}: inline guard must read the plugin flag`);
  // Ordering matters: a gate placed after preventDefault() cannot un-block.
  assert.ok(gate < block, `${file}: the flag is checked before the menu is blocked`);
  // All ten locales must have a toast string of their own.
  for (const loc of ['en','fa','ar','de','es','fr','tr','zh','ja','ru']) {
    assert.ok(new RegExp(`\\b${loc}: "`).test(src), `${file}: ${loc} toast string`);
  }
}

// The flag is read at EVENT time, not registration time, so the page works
// whether the plugin prints it above or below this block. Prove both.
const guardSrc = read('index.html').match(GUARD)[0].replace(/<\/?script>/g, '');

function rightClick({ flag }) {
  const dom = new JSDOM('<!doctype html><html lang="en"><body></body></html>', { pretendToBeVisual: true, runScripts: 'outside-only' });
  if (flag !== undefined) dom.window.KDCV_RIGHTCLICK_GUARD = flag;
  dom.window.eval(guardSrc);
  const ev = new dom.window.MouseEvent('contextmenu', { bubbles: true, cancelable: true });
  dom.window.document.body.dispatchEvent(ev);
  const blocked = ev.defaultPrevented;
  dom.window.close();
  return blocked;
}

assert.equal(rightClick({ flag: undefined }), true, 'no plugin (static site): guard stays on');
assert.equal(rightClick({ flag: true }), true, 'plugin ON: right-click blocked');
assert.equal(rightClick({ flag: false }), false, 'plugin OFF: right-click works — the bug this test exists for');

// The plugin has to announce the flag in BOTH states; printing it only when
// enabled is exactly what left the inline handler stuck on.
const plugin = read('_tooling/wp-theme/kdcv-rightclick-guard/kdcv-rightclick-guard.php');
assert.ok(/add_action\(\s*'wp_head'/.test(plugin), 'plugin publishes the flag in wp_head');
assert.ok(plugin.includes("'true' : 'false'"), 'plugin prints the flag in both states');
assert.ok(/wp_head'[\s\S]{0,600}?\}, 1 \);/.test(plugin), 'flag printed at wp_head priority 1');
assert.ok(plugin.includes('window.KDCV_RIGHTCLICK_GUARD===false'), "plugin's own fallback listener honours the flag");

console.log('PASS: right-click guard obeys the WordPress switch (27 files, 3 flag states)');
