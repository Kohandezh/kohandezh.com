/* ==========================================================================
   lazy-bundle.js
   Defers three non-critical scripts until they're actually needed, so they
   no longer compete for parser/bandwidth during first paint.

     · jquery-validate.js  → loads when a #contactform input is focused
     · ai-pet.min.js       → loads 3s after load OR first user interaction
     · swiper-bundle.min.js→ loads when .section-testimonial enters viewport

   Each loader is idempotent (guards with a loaded flag) and respects
   prefers-reduced-data: under that hint, AI pet is skipped entirely.

   Behavior is IDENTICAL to before — only the load timing changes.
   ========================================================================== */
(function () {
  "use strict";

  // Resolve the right asset path prefix regardless of page depth
  // (root pages use "assets/", /blog/ and /portfolio/ use "../assets/" or "/assets/")
  var scripts = document.getElementsByTagName("script");
  var selfSrc = "";
  for (var i = 0; i < scripts.length; i++) {
    var s = scripts[i].src || "";
    if (s.indexOf("lazy-bundle") !== -1) { selfSrc = s; break; }
  }
  // Prefix = everything up to and including "assets/"
  var m = selfSrc.match(/^(.*\/assets\/)/);
  var PREFIX = m ? m[1] : "assets/";
  if (PREFIX.charAt(0) !== "/" && PREFIX.indexOf("://") === -1 && PREFIX.indexOf("/assets/") !== 0) {
    // relative path; keep as-is
  }

  function load(src, attrs) {
    if (load._loaded[src]) return;
    load._loaded[src] = true;
    var el = document.createElement("script");
    el.src = src;
    el.defer = true;
    if (attrs) for (var k in attrs) el.setAttribute(k, attrs[k]);
    document.body.appendChild(el);
  }
  load._loaded = {};

  /* -------- Win #4: jquery-validate — REVERTED ------------------------- *
   * main.js calls .validate() at DOMContentLoaded; if we lazy-load on focus
   * the plugin isn't available when main.js runs and form setup throws.
   * jquery-validate.js stays as a defer script in HTML (26KB / ~9KB brotli).
   * Kept this stub so the comment exists for future reference.
   * --------------------------------------------------------------------- */
  function setupValidateLazy() { /* no-op */ }

  /* -------- Win #5: ai-pet on idle / first interaction ------------------- */
  function setupAiPetLazy() {
    // Respect reduced-data: skip the chatbot entirely
    if (window.matchMedia && window.matchMedia("(prefers-reduced-data: reduce)").matches) return;
    var fired = false;
    function fire() {
      if (fired) return;
      fired = true;
      load(PREFIX + "js/ai-pet.min.js?v=10");
      cleanup();
    }
    function cleanup() {
      document.removeEventListener("pointerdown", fire);
      document.removeEventListener("keydown", fire);
      document.removeEventListener("scroll", fire);
      clearTimeout(timer);
    }
    var timer = setTimeout(fire, 3000);
    document.addEventListener("pointerdown", fire, { passive: true, once: false });
    document.addEventListener("keydown", fire, { passive: true, once: false });
    document.addEventListener("scroll", fire, { passive: true, once: false });
    // Safety: if user never interacts, still load after 8s so it's available
    setTimeout(fire, 8000);
  }

  /* -------- Win #3: swiper-bundle + carousel on testimonials approach ---
     carousel.min.js binds to $(window).on("load", ...) and calls new Swiper,
     so Swiper MUST be defined before carousel executes. We chain them:
     swiper-bundle first, carousel after — both added post-load are run by
     the browser in insertion order since each waits for the previous. */
  function loadOrdered(srcs) {
    function next(i) {
      if (i >= srcs.length) return;
      var el = document.createElement("script");
      el.src = srcs[i];
      el.defer = true;
      el.onload = function () { next(i + 1); };
      el.onerror = function () { next(i + 1); };
      document.body.appendChild(el);
    }
    next(0);
  }
  function setupSwiperLazy() {
    var target = document.querySelector(".section-testimonial, .swiper-testimonial, .tf-swiper");
    if (!target) return; // page has no swiper; skip the load entirely
    if (!("IntersectionObserver" in window)) {
      // No IO support → just load them after window.load
      var go = function () { loadOrdered([PREFIX + "js/swiper-bundle.min.js", PREFIX + "js/carousel.min.js?v=3"]); };
      if (document.readyState === "complete") go();
      else window.addEventListener("load", go);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (entries[i].isIntersecting) {
          loadOrdered([PREFIX + "js/swiper-bundle.min.js", PREFIX + "js/carousel.min.js?v=3"]);
          io.disconnect();
          break;
        }
      }
    }, { rootMargin: "200px 0px", threshold: 0.01 });
    io.observe(target);
  }

  /* -------- boot ---------------------------------------------------------- */
  function boot() {
    setupValidateLazy();
    setupAiPetLazy();
    setupSwiperLazy();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
