/**
 * "Waiting" page loader — placement and behaviour.
 *
 * Placement: build.py --check is clean; every stamped page (static and
 * WordPress) carries exactly one copy of waiting.partial.html right after its
 * <body> tag (after wp_body_open() on WordPress); the redirect stubs and the
 * offline page carry none; ids inside the block are unique on every page.
 *
 * Behaviour (jsdom, runScripts: 'dangerously'): the partial's own inline script
 * is run in a small page and driven through the dismissal contract — first and
 * repeat views, parser-done cap, window load, readyState already complete,
 * bfcache restore, crawler UA, user input, throwing storage, localized label.
 * jsdom has no CSS animation, so "hidden" here means the fade class was added
 * and the node was then removed; the CSS failsafe is asserted statically.
 *
 * Run: node _tooling/tests/waiting.test.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');
const { JSDOM } = require('jsdom');

const ROOT = path.join(__dirname, '..', '..');
const read = (f) => fs.readFileSync(path.join(ROOT, f), 'utf8');
const PARTIAL = read('_tooling/waiting/waiting.partial.html').replace(/\n+$/, '');
const BEGIN = '<!-- WAITING:BEGIN generated from _tooling/waiting/waiting.partial.html by _tooling/waiting/build.py -- do not edit by hand -->';
const END = '<!-- WAITING:END -->';

let pass = 0, fail = 0;
const ok = (cond, label, detail) => {
  if (cond) { pass++; console.log(`  ✓ ${label}`); }
  else { fail++; console.log(`  ✗ ${label}${detail ? `\n      ${detail}` : ''}`); }
};
const group = (n) => console.log(`\n\x1b[1m${n}\x1b[0m`);
const count = (s, needle) => s.split(needle).length - 1;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const CV = { 'index.html': 'en', 'fa.html': 'fa', 'ar.html': 'ar', 'de.html': 'de', 'es.html': 'es',
  'fr.html': 'fr', 'tr.html': 'tr', 'zh.html': 'zh', 'ja.html': 'ja', 'ru.html': 'ru' };
const blogPosts = fs.readdirSync(path.join(ROOT, 'blog')).filter((f) => f.endsWith('.html')).map((f) => 'blog/' + f);
const STATIC = [...Object.keys(CV), 'Certificates.html', 'PSN.html', 'knowledge.html', 'privacy.html',
  'terms.html', 'videos.html', '404.html', 'portfolio/index.html', ...blogPosts];
const THEME = '_tooling/wp-theme/kohandezhcv/';
const WP_GENERATED = fs.readdirSync(path.join(ROOT, THEME))
  .filter((f) => f === 'front-page.php' || f === '404.php' || /^page-.+\.php$/.test(f)).map((f) => THEME + f);
const WP_HAND = [THEME + 'home.php', THEME + 'single.php'];
const EXCLUDED = [...fs.readdirSync(ROOT).filter((f) => /^404-.+\.html$/.test(f)), 'offline.html'];

// ─────────────────────────────────────────────────────────────────────────
group('Source and build');

let checkOut = '', checkOk = true;
try { checkOut = execFileSync('python3', [path.join(ROOT, '_tooling/waiting/build.py'), '--check'], { encoding: 'utf8' }); }
catch (e) { checkOk = false; checkOut = String(e.stdout || '') + String(e.stderr || ''); }
ok(checkOk, 'build.py --check: every target is up to date', checkOut.split('\n').filter((l) => /stale|ERROR/.test(l)).join('; '));

ok(PARTIAL.startsWith(BEGIN + '\n') && PARTIAL.endsWith('\n' + END), 'partial is delimited by the exact BEGIN / END marker lines');
const bytes = Buffer.byteLength(PARTIAL, 'utf8');
ok(bytes <= 6144, `block stays under the 6 KB hard cap (${bytes} bytes)`);
ok(!/<\?|<\/?(body|head)\b|\bsrc=|\bhref=|https?:|@import/i.test(PARTIAL), 'block has no PHP opener, no body/head tag, no external URL');
ok((PARTIAL.match(/url\(([^)]*)\)/g) || []).every((u) => u.startsWith('url(#')), 'only in-document url(#…) references');

const scriptSrc = (PARTIAL.match(/<script>([\s\S]*?)<\/script>/) || [])[1] || '';
let parses = true;
try { new vm.Script(scriptSrc); } catch (e) { parses = false; }
ok(parses && scriptSrc.length > 0, 'the inline script parses');

// The logo geometry is the vector from assets/images/logo/logo.svg, unchanged.
const logo = read('assets/images/logo/logo.svg');
for (const d of ['M262 268 205 380 258 380 258 268Z', 'M126 372V174l79 100 79-100v198', 'M284 268 396 156M284 268l112 112']) {
  ok(logo.includes(`d="${d}"`) && PARTIAL.includes(`d="${d}"`), `logo path "${d.slice(0, 18)}…" is identical to logo.svg`);
}
ok(/x1="200" y1="270" x2="255" y2="380"/.test(PARTIAL) && /#d9ff8a/i.test(PARTIAL) && /#4fd35f/i.test(PARTIAL), 'accent gradient matches logo.svg');
ok(PARTIAL.includes('<rect width="512" height="512" rx="114" fill="url(#kdcvw-tile)"/>') && /id="kdcvw-tile" x1="0" y1="0" x2="512" y2="512"/.test(PARTIAL) && /#171d1b/i.test(PARTIAL) && /#050807/i.test(PARTIAL), 'tile = rx 114/512 rounded square with the logo.svg tile gradient');
ok(/animation:kdcvw-h \.4s 6s forwards/.test(PARTIAL) && /@keyframes kdcvw-h\{from\{pointer-events:none\}to\{opacity:0;visibility:hidden;pointer-events:none\}\}/.test(PARTIAL), 'CSS-only failsafe hides the overlay at 6 s and stops taking clicks the moment it starts');
ok(/<noscript><style>#kdcv-waiting\{display:none\}<\/style><\/noscript>/.test(PARTIAL), '<noscript> hides the overlay');
ok(/\.kdcv-waiting-out\{[^}]*pointer-events:none/.test(PARTIAL), 'fading overlay stops taking pointer events at once');
ok(!/\.kdcv-waiting-out\{[^}]*animation/.test(PARTIAL), 'the dismissal does not replace the failsafe animation');
ok(/@media \(prefers-reduced-motion:no-preference\)\{[\s\S]*kdcvw-p[\s\S]*\n\}/.test(PARTIAL), 'all transform motion sits behind prefers-reduced-motion: no-preference');
ok(/html\[data-kdcv-theme=dark\] #kdcv-waiting/.test(PARTIAL) && /html\[data-kdcv-theme=light\] #kdcv-waiting/.test(PARTIAL), 'surface follows the page theme attribute');
ok(/z-index:2147483000/.test(PARTIAL), 'z-index is above the floating avatar');

// ─────────────────────────────────────────────────────────────────────────
group('Placement');

const BODY_RE = /<body(?=[\s>])[^>]*>/gi;
const placed = (src, wp) => {
  const bodies = src.replace(/<!--[\s\S]*?-->/g, (m) => ' '.repeat(m.length)).match(BODY_RE) || [];
  if (bodies.length !== 1) return false;
  const at = src.search(/<body(?=[\s>])[^>]*>/i) + bodies[0].length;
  const lead = wp ? /^\n<\?php wp_body_open\(\); \?>\n/ : /^\n/;
  const m = src.slice(at).match(lead);
  return !!m && src.slice(at + m[0].length).startsWith(PARTIAL);
};

for (const f of STATIC) {
  const src = read(f);
  ok(count(src, '<!-- WAITING:BEGIN') === 1 && count(src, END) === 1 && placed(src, false),
     `${f}: exactly one block, immediately after <body>`);
}
for (const f of [...WP_GENERATED, ...WP_HAND]) {
  const src = read(f);
  ok(count(src, '<!-- WAITING:BEGIN') === 1 && count(src, END) === 1 && placed(src, true),
     `${f}: exactly one block, right after <body> + wp_body_open()`);
}
ok(WP_GENERATED.length >= 17, `every generated WP template is covered (${WP_GENERATED.length})`);
for (const f of EXCLUDED) {
  ok(!read(f).includes('WAITING:BEGIN') && !read(f).includes('kdcv-waiting'), `${f}: excluded, carries no loader`);
}

const blockIds = [...PARTIAL.matchAll(/\sid="([^"]+)"/g)].map((m) => m[1]);
for (const f of [...STATIC, ...WP_GENERATED, ...WP_HAND]) {
  const src = read(f);
  const dupes = blockIds.filter((id) => count(src, `id="${id}"`) !== 1);
  ok(dupes.length === 0, `${f}: block ids (${blockIds.join(', ')}) are unique on the page`, dupes.join(', '));
}
ok(blockIds.includes('kdcvw-accent') && !blockIds.some((id) => /^mk-/.test(id)), 'gradient ids carry the kdcvw- prefix');

// ─────────────────────────────────────────────────────────────────────────
group('Behaviour (jsdom)');

/**
 * Build a page around the partial. `hooks` runs before parsing so storage,
 * the user agent or listeners can be replaced the way a browser would.
 */
function page({ lang = 'en', htmlAttrs = '', body = '', head = '', hooks } = {}) {
  const html = `<!doctype html><html lang="${lang}"${htmlAttrs}><head>${head}</head><body${body}>\n${PARTIAL}\n<main>page</main></body></html>`;
  return new JSDOM(html, {
    runScripts: 'dangerously', pretendToBeVisual: true, url: 'https://kohandezh.test/',
    beforeParse(w) {
      w.__errors = [];
      w.addEventListener('error', (e) => w.__errors.push(e.message));
      if (hooks) hooks(w);
    },
  });
}
const el = (dom) => dom.window.document.getElementById('kdcv-waiting');
const state = (dom) => { const e = el(dom); return !e ? 'removed' : /kdcv-waiting-out/.test(e.className) ? 'fading' : 'shown'; };
/**
 * Simulate a page whose window load lags far behind: the parser finishes, but
 * readyState never reaches "complete" and the window load event never arrives.
 */
const holdLoad = (w) => {
  const add = w.addEventListener.bind(w);
  w.addEventListener = (t, f, o) => (t === 'load' ? undefined : add(t, f, o));
  const real = Object.getOwnPropertyDescriptor(w.Document.prototype, 'readyState').get;
  Object.defineProperty(w.document, 'readyState', { configurable: true, get() { const v = real.call(this); return v === 'complete' ? 'interactive' : v; } });
};
const seen = (w) => w.sessionStorage.setItem('kdcvWaitingSeen', '1');
const since = (t0) => Date.now() - t0;

async function run() {
  const tests = [];
  // Sequential on purpose: the timing assertions must not share the event loop
  // with other pages being parsed.
  const test = (fn) => tests.push(fn);

  // Present at start, before any timer or event.
  test(async () => {
    const dom = page();
    const e = el(dom);
    ok(!!e && state(dom) === 'shown' && e.getAttribute('role') === 'progressbar',
       'overlay is present and shown when parsing reaches it');
    ok(dom.window.sessionStorage.getItem('kdcvWaitingSeen') === '1', 'first view marks the session as seen');
  });

  // First view: window load, but not before the 940 ms entry minimum.
  test(async () => {
    const t0 = Date.now();
    const dom = page();
    await sleep(500);
    ok(state(dom) === 'shown', 'first view: still shown before the 940 ms minimum even though load fired');
    let fadedAt = 0;
    while (since(t0) < 2500 && state(dom) === 'shown') await sleep(20);
    fadedAt = since(t0);
    ok(state(dom) !== 'shown' && fadedAt >= 900 && fadedAt < 1400, `first view: fades at the minimum after load (${fadedAt} ms)`);
    await sleep(800);
    ok(state(dom) === 'removed', 'first view: node removed from the DOM after the ring closes and the fade ends');
    ok(dom.window.__errors.length === 0, 'first view: no script errors', dom.window.__errors.join('; '));
  });

  // Repeat view with load held back: dismissed at parser-done (DOMContentLoaded side), no minimum.
  test(async () => {
    const t0 = Date.now();
    const dom = page({ hooks: (w) => { seen(w); holdLoad(w); } });
    ok(/kdcv-waiting-r/.test(el(dom).className), 'repeat view: mark starts complete (no entry replay)');
    while (since(t0) < 1500 && state(dom) === 'shown') await sleep(10);
    const at = since(t0);
    ok(state(dom) !== 'shown' && at < 300, `repeat view: dismissed as soon as the parser is done, load not needed (${at} ms)`);
  });

  // First view with load held back: parser-done + 1200 ms cap.
  test(async () => {
    const t0 = Date.now();
    const dom = page({ hooks: holdLoad });
    await sleep(1000);
    ok(state(dom) === 'shown', 'first view, slow load: still shown 1 s in');
    while (since(t0) < 3500 && state(dom) === 'shown') await sleep(20);
    const at = since(t0);
    ok(state(dom) !== 'shown' && at >= 1150 && at < 1700, `first view, slow load: dismissed by the parser-done + 1200 ms cap (${at} ms)`);
  });

  // Neither parser-done nor load ever arrives (e.g. a stalled parser-blocking
  // script): the 3 s hard cap still releases the page.
  test(async () => {
    const t0 = Date.now();
    const dom = page({ hooks: (w) => {
      holdLoad(w);
      const add = w.document.addEventListener.bind(w.document);
      w.document.addEventListener = (t, f, o) => (t === 'readystatechange' ? undefined : add(t, f, o));
    } });
    while (since(t0) < 4500 && state(dom) === 'shown') await sleep(20);
    const at = since(t0);
    ok(state(dom) !== 'shown' && at >= 2900 && at < 3500, `stalled page: the 3 s hard cap dismisses it (${at} ms)`);
  });

  // readyState already complete when the script runs.
  test(async () => {
    const dom = page({ hooks: (w) => Object.defineProperty(w.document, 'readyState', { configurable: true, get: () => 'complete' }) });
    ok(state(dom) === 'removed', 'readyState already "complete": removed immediately');
  });

  // bfcache restore.
  test(async () => {
    const dom = page({ hooks: holdLoad });
    const w = dom.window;
    const ev = new w.PageTransitionEvent('pageshow', { persisted: true });
    w.dispatchEvent(ev);
    ok(state(dom) === 'removed', 'pageshow with persisted (bfcache restore): removed immediately');
  });

  // Crawlers never see it.
  for (const ua of ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
    'Mozilla/5.0 (compatible; bingbot/2.0)', 'facebookexternalhit/1.1']) {
    test(async () => {
      const dom = page({ hooks: (w) => Object.defineProperty(w.navigator, 'userAgent', { configurable: true, get: () => ua }) });
      ok(state(dom) === 'removed', `crawler UA never shows it: ${ua.match(/(Googlebot|GPTBot|bingbot|facebookexternalhit)/)[1]}`);
    });
  }

  // User input dismisses at once.
  test(async () => {
    const dom = page({ hooks: holdLoad });
    const w = dom.window;
    w.document.body.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
    ok(state(dom) === 'fading', 'keydown during the hold dismisses immediately');
    const dom2 = page({ hooks: holdLoad });
    dom2.window.document.body.dispatchEvent(new dom2.window.Event('pointerdown', { bubbles: true }));
    ok(state(dom2) === 'fading', 'pointerdown during the hold dismisses immediately');
  });

  // Throwing storage must not break it.
  test(async () => {
    const t0 = Date.now();
    const boom = { configurable: true, get() { throw new Error('SecurityError'); } };
    const dom = page({ hooks: (w) => { Object.defineProperty(w, 'localStorage', boom); Object.defineProperty(w, 'sessionStorage', boom); } });
    ok(state(dom) === 'shown' && dom.window.__errors.length === 0, 'throwing localStorage/sessionStorage getters: no error, overlay shown');
    while (since(t0) < 2000 && state(dom) === 'shown') await sleep(10);
    ok(state(dom) !== 'shown', `throwing storage: still dismissed (${since(t0)} ms)`);
  });

  // Theme resolution: attribute > darkMode > data-default-mode > prefers-color-scheme.
  test(async () => {
    const dk = (dom) => /kdcv-waiting-dk/.test(el(dom).className);
    ok(dk(page({ body: ' data-default-mode="dark"' })), 'theme: body data-default-mode="dark" -> dark surface');
    ok(!dk(page({ body: ' data-default-mode="dark"', hooks: (w) => w.localStorage.setItem('darkMode', 'disabled') })), 'theme: stored darkMode wins over data-default-mode');
    ok(!dk(page({ htmlAttrs: ' data-kdcv-theme="light"', body: ' data-default-mode="dark"', hooks: (w) => w.localStorage.setItem('darkMode', 'enabled') })), 'theme: html[data-kdcv-theme] wins over everything');
    ok(dk(page({ hooks: (w) => { w.matchMedia = (q) => ({ matches: /dark/.test(q) }); } })), 'theme: prefers-color-scheme is the last fallback');
    ok(!dk(page()), 'theme: no signal and no matchMedia -> light surface');
  });

  // Localized accessible label from html[lang], using each CV page's real lang value.
  const LABELS = { en: 'Loading', fa: 'در حال بارگذاری', ar: 'جارٍ التحميل', de: 'Wird geladen', es: 'Cargando',
    fr: 'Chargement', tr: 'Yükleniyor', zh: '加载中', ja: '読み込み中', ru: 'Загрузка' };
  for (const [file, code] of Object.entries(CV)) {
    const lang = (read(file).match(/<html[^>]*\slang="([^"]+)"/) || [])[1];
    test(async () => {
      const dom = page({ lang, hooks: holdLoad });
      ok(el(dom).getAttribute('aria-label') === LABELS[code], `aria-label for ${file} (lang="${lang}") is "${LABELS[code]}"`);
    });
  }

  for (const fn of tests) {
    try { await fn(); } catch (e) { ok(false, 'test threw', e && e.stack); }
  }
}

run().then(() => {
  console.log('\n' + '─'.repeat(56));
  if (fail) {
    console.log(`\x1b[31m${fail} of ${pass + fail} failed\x1b[0m, ${pass} passed`);
    process.exit(1);
  }
  console.log(`\x1b[32mall ${pass} waiting-loader assertions passed\x1b[0m`);
  process.exit(0);
});
