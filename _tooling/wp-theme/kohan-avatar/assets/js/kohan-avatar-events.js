/**
 * Kohan Avatar — lifecycle event bridge.
 *
 * Translates the website's real request lifecycle into avatar moods by
 * dispatching the documented `kohan:avatar:mood` CustomEvent that the
 * controller listens for. Prefers real events over guessing from response
 * text. Two integration paths, both opt-in via KohanAvatarConfig.options:
 *
 *   1. Explicit: any site code can fire
 *        window.dispatchEvent(new CustomEvent('kohan:avatar:mood',
 *          { detail: { mood: 'macbook-work', returnTo: 'idle' } }));
 *
 *   2. Auto (responseEvents): observe fetch()/EventSource to the configured
 *      AI chat REST route and map request -> macbook-work, ok -> ipad-review
 *      then wink, error -> angry. Only the route prefix in config is watched;
 *      nothing else is intercepted.
 */
(function () {
  "use strict";
  var CFG = window.KohanAvatarConfig || {};
  var OPTS = CFG.options || {};
  if (!OPTS.responseEvents) return;

  var ROUTE = (CFG.chatRoute || "").toString();
  if (!ROUTE) return;

  function fire(mood, returnTo) {
    try {
      window.dispatchEvent(
        new CustomEvent("kohan:avatar:mood", {
          detail: { mood: mood, returnTo: returnTo || "idle" },
        })
      );
    } catch (e) {}
  }

  var ALLOW = {
    "macbook-work": 1, "ipad-review": 1, confused: 1, wink: 1,
    waving: 1, angry: 1, "goodbye-smoke": 1, idle: 1,
  };

  // Accept semantic metadata { avatarMood } only against the allowlist.
  function fromMeta(meta) {
    if (meta && typeof meta.avatarMood === "string" && ALLOW[meta.avatarMood]) {
      fire(meta.avatarMood);
      return true;
    }
    return false;
  }

  // --- wrap fetch for the chat route only -------------------------------
  if (typeof window.fetch === "function") {
    var origFetch = window.fetch;
    window.fetch = function (input, init) {
      var url = typeof input === "string" ? input : (input && input.url) || "";
      var isChat = url.indexOf(ROUTE) !== -1;
      if (isChat) fire("macbook-work"); // request submitted / generating
      var p = origFetch.apply(this, arguments);
      if (!isChat) return p;
      return p.then(
        function (res) {
          // Try to read an avatarMood hint without consuming the caller's body.
          var ct = res.headers && res.headers.get && res.headers.get("content-type");
          if (ct && ct.indexOf("application/json") !== -1 && res.clone) {
            res.clone().json().then(function (j) {
              if (!fromMeta(j)) {
                fire("ipad-review"); // reviewing rendered response
                setTimeout(function () { fire("wink"); }, 1400);
              }
            }).catch(function () {
              fire(res.ok ? "ipad-review" : "angry");
            });
          } else {
            fire(res.ok ? "ipad-review" : "angry");
          }
          return res;
        },
        function (err) {
          fire("angry"); // error / blocked / failed
          throw err;
        }
      );
    };
  }
})();
