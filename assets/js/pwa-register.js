/*!
 * pwa-register.js — registers the service worker.
 * Registration is deferred to window load so it never competes with the
 * critical render path, and it is skipped on insecure origins where the
 * ServiceWorker API is unavailable (plain-HTTP local previews).
 */
(function () {
  "use strict";
  if (!("serviceWorker" in navigator)) return;
  window.addEventListener("load", function () {
    navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(function () {
      // Registration failures are non-fatal: the site works without offline support.
    });
  });
})();
