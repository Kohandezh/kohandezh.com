/*!
 * sw.js — offline support for kohandezh.com
 *
 * Strategy, and why:
 *   HTML   -> network-first. The site's assets are rebuilt by an external
 *             minification pipeline, and pages are edited often. Serving a
 *             cached page first would show stale content after every deploy,
 *             which is a worse failure than a slightly slower first paint.
 *   Assets -> cache-first, but ONLY for URLs carrying a ?v= cache-busting
 *             query. Those are immutable by construction: when the file
 *             changes, the version changes, so the URL changes. Unversioned
 *             assets fall through to the network so a rebuilt .min.js can
 *             never be pinned forever.
 *
 * Bump CACHE_VERSION on any change to this file or to PRECACHE.
 *
 * v3: the JPEG/PNG originals were replaced by WebP and every ?v= was bumped, so
 * a returning visitor's v2 runtime cache is now entirely dead entries. The
 * activate handler deletes any cache whose key is not the current RUNTIME or
 * PRECACHE, so bumping here is what actually evicts it.
 */
var CACHE_VERSION = "v8";
var RUNTIME = "kdcv-runtime-" + CACHE_VERSION;
var PRECACHE = "kdcv-precache-" + CACHE_VERSION;
var OFFLINE_URL = "/offline.html";

// Deliberately small: just enough to render an offline fallback. Precaching
// the whole site would download tens of megabytes on first visit.
//
// No ?v= on these. The versions here were pinned at v=1 and never moved with
// the build, so every rebuild left them addressing a version no page requests —
// cached, then never read. Unversioned URLs are served by the same files and
// fall under the network path in fetch(), which is the correct behaviour for
// entries whose only job is to render the offline page.
var PRECACHE_URLS = [
  OFFLINE_URL,
  "/wp-content/themes/kohandezhcv/assets/images/logo/logo.png",
  "/wp-content/themes/kohandezhcv/assets/images/logo/favicon-192.png",
  // The offline page renders the flow-field background too. With no network
  // these must already be cached or the page falls back to a flat background.
  "/wp-content/themes/kohandezhcv/assets/css/flow-field-background.min.css",
  "/wp-content/themes/kohandezhcv/assets/js/flow-field-background.min.js"
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(PRECACHE)
      .then(function (cache) { return cache.addAll(PRECACHE_URLS); })
      // A missing precache entry must not block installation outright,
      // otherwise one renamed file permanently breaks the worker.
      .catch(function () {})
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        if (key !== RUNTIME && key !== PRECACHE) return caches.delete(key);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isVersionedAsset(url) {
  return /[?&]v=/.test(url.search) && /\.(css|js|woff2?|png|jpe?g|webp|avif|svg)$/i.test(url.pathname);
}

self.addEventListener("fetch", function (event) {
  var req = event.request;

  // Never touch non-GET: the contact form POSTs to Web3Forms and must always
  // hit the network. Caching or replaying it would be wrong and lossy.
  if (req.method !== "GET") return;

  var url;
  try { url = new URL(req.url); } catch (e) { return; }

  // Same-origin only. Aparat embeds, fonts and share targets are left alone so
  // this worker never becomes a proxy for third-party content.
  if (url.origin !== self.location.origin) return;

  // Let the browser handle range requests (video seeking) natively.
  if (req.headers.has("range")) return;

  if (req.mode === "navigate" || (req.headers.get("accept") || "").indexOf("text/html") > -1) {
    event.respondWith(
      fetch(req)
        .then(function (res) {
          var copy = res.clone();
          caches.open(RUNTIME).then(function (c) { c.put(req, copy); });
          return res;
        })
        .catch(function () {
          return caches.match(req).then(function (hit) {
            return hit || caches.match(OFFLINE_URL);
          });
        })
    );
    return;
  }

  if (isVersionedAsset(url)) {
    event.respondWith(
      caches.match(req).then(function (hit) {
        if (hit) return hit;
        return fetch(req).then(function (res) {
          if (res && res.status === 200 && res.type === "basic") {
            var copy = res.clone();
            caches.open(RUNTIME).then(function (c) { c.put(req, copy); });
          }
          return res;
        });
      })
    );
  }
});

// Lets a page trigger an immediate update instead of waiting for all tabs to close.
self.addEventListener("message", function (event) {
  if (event.data === "skipWaiting") self.skipWaiting();
});
