/**
 * Kohandezh locale router
 *
 * Behaviour (v2 — bot- and SEO-safe):
 *   1. The English front page (/) is the canonical entry. Bots (Googlebot,
 *      GPTBot, ClaudeBot, PerplexityBot, …) and any browser whose preferred
 *      language is English stay on the English page — no JS redirect.
 *   2. Visitors whose browser reports a supported non-English locale
 *      (navigator.language / Accept-Language) are auto-routed to that locale.
 *   3. An explicit choice via ?lang=xx (or a previously stored preference)
 *      always wins and is remembered for the session.
 *   4. Localized deep links (fa.html, /fa/, etc.) are never overridden.
 *
 * v1 hard-coded `route("fa")` as the default, which forced every visitor —
 * including crawlers and English-speaking recruiters — onto the Persian page
 * unless they had an explicit `kdcvExplicitLocale=en` flag in sessionStorage.
 * That defeated the English canonical URL for SEO/GEO.
 */
(function () {
    "use strict";

    var root = document.documentElement;
    var mode = root.getAttribute("data-kdcv-router-mode") || "static";
    var current = (root.getAttribute("lang") || "en").toLowerCase().split("-")[0];
    var supported = ["en", "fa", "ar", "de", "es", "fr", "tr", "zh", "ja"];

    // Where a visitor with NO usable language signal lands. English stays the
    // canonical URL for crawlers; this only affects real browsers that tell us
    // nothing about their preference.
    var DEFAULT_LOCALE = "en";

    var BOT_PATTERN = /(bot|spider|crawler|slurp|bingpreview|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|skypeuripreview|google-?structured|feedfetcher|ia_archiver|archive\.org_bot|perplexity|gptbot|claudebot|bytespider|applebot|yandex|baidu|duckduckbot|seznambot|facebot|semrush|ahrefsbot|dotbot|petalbot|dataforseo|mj12bot|sitesucker)/i;

    function storageGet(area, key) {
        try { return area.getItem(key); } catch (error) { return null; }
    }

    function storageSet(area, key, value) {
        try { area.setItem(key, value); } catch (error) { /* private mode */ }
    }

    function cleanLocale(value) {
        var locale = (value || "").toLowerCase().split("-")[0];
        return supported.indexOf(locale) > -1 ? locale : "";
    }

    function targetFor(locale) {
        if (mode === "wordpress") {
            var base = root.getAttribute("data-kdcv-site-base") || "/";
            return new URL(locale === "en" ? "./" : locale + "/", base).href;
        }
        return new URL(locale === "en" ? "index.html" : locale + ".html", window.location.href).href;
    }

    function route(locale) {
        locale = cleanLocale(locale) || "";
        if (!locale || locale === current) return;
        window.location.replace(targetFor(locale));
    }

    // Detect the visitor's preferred UI language from the browser.
    // Falls back to "" (unknown) — caller decides what to do.
    function browserLocale() {
        var nav = window.navigator || {};
        var list = nav.languages && nav.languages.length ? nav.languages : [];
        var candidates = list.concat([nav.language, nav.userLanguage, nav.browserLanguage]);
        for (var i = 0; i < candidates.length; i++) {
            var loc = cleanLocale(candidates[i]);
            if (loc) return loc;
        }
        return "";
    }

    function looksLikeBot() {
        var ua = (window.navigator && window.navigator.userAgent) || "";
        return BOT_PATTERN.test(ua);
    }

    var query = new URLSearchParams(window.location.search);
    var requested = cleanLocale(query.get("lang"));
    if (requested) {
        storageSet(window.localStorage, "siteLang", requested);
        storageSet(window.sessionStorage, "kdcvExplicitLocale", requested);
        route(requested);
        return;
    }

    // Never override a deep/localized link. Default routing only applies to
    // the English front page, which is the canonical site entry point.
    if (current !== "en") return;

    var pathname = window.location.pathname || "/";
    var neutralEntry = mode === "wordpress" || /\/$/.test(pathname);
    if (!neutralEntry) return;

    var explicitSessionLocale = cleanLocale(storageGet(window.sessionStorage, "kdcvExplicitLocale"));
    if (explicitSessionLocale) {
        // User picked a language earlier this session — honour it.
        route(explicitSessionLocale);
        return;
    }

    // Crawlers and bots: stay on the English canonical so they index /, not /fa.
    if (looksLikeBot()) return;

    var saved = cleanLocale(storageGet(window.localStorage, "siteLang"));
    if (saved) {
        route(saved);
        return;
    }

    // No explicit choice yet. Order of trust:
    //   1. The browser's own preferred language — the strongest signal we have
    //      client-side, and it beats geography (an Iranian in Berlin whose
    //      browser is set to Persian wants Persian, not German).
    //   2. Nothing usable → ENGLISH, the stable canonical/x-default URL.
    //
    // `detected === "en"` is a SIGNAL, not an absence: an English browser stays
    // on English. Only a genuinely unknown preference falls through to fa.
    //
    // Bots never reach this point (they returned above), so the English page
    // at / remains the canonical URL crawlers index — per CLAUDE.md #5 this is
    // the condition that makes a non-English default safe for SEO.
    var detected = browserLocale();
    if (detected) {
        if (detected !== "en") route(detected);
        return;                      // English browser: stay on English.
    }
    route(DEFAULT_LOCALE);
}());
