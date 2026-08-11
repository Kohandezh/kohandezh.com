/*
 * Minimal Real User Monitoring for Core Web Vitals — no dependencies.
 * Collects LCP, CLS, INP, FCP and TTFB via PerformanceObserver and sends one
 * beacon per page view when the page is hidden/unloaded.
 *
 * Where the data goes:
 *   window.KDCV_RUM_ENDPOINT = "https://…"   -> navigator.sendBeacon(JSON)
 *   (not set)                                -> console.debug only (dev mode)
 *
 * The payload is anonymous: metric values, page path, connection type and
 * device memory bucket. No cookies, no fingerprinting, no user identifiers.
 */
(function () {
    "use strict";
    if (!("PerformanceObserver" in window)) return;

    var metrics = { page: location.pathname, lang: document.documentElement.lang || "" };

    // TTFB straight off the navigation entry.
    try {
        var nav = performance.getEntriesByType("navigation")[0];
        if (nav) metrics.ttfb = Math.round(nav.responseStart);
    } catch (e) { /* older engines */ }

    function observe(type, buffered, cb) {
        try {
            var po = new PerformanceObserver(function (list) {
                list.getEntries().forEach(cb);
            });
            po.observe({ type: type, buffered: buffered });
            return po;
        } catch (e) { return null; }
    }

    // FCP
    observe("paint", true, function (entry) {
        if (entry.name === "first-contentful-paint") {
            metrics.fcp = Math.round(entry.startTime);
        }
    });

    // LCP — keep the latest candidate.
    observe("largest-contentful-paint", true, function (entry) {
        metrics.lcp = Math.round(entry.startTime);
    });

    // CLS — sum of shifts without recent input, session-window style.
    var clsValue = 0;
    observe("layout-shift", true, function (entry) {
        if (!entry.hadRecentInput) {
            clsValue += entry.value;
            metrics.cls = Math.round(clsValue * 1000) / 1000;
        }
    });

    // INP approximation — worst interaction duration seen.
    var worstINP = 0;
    observe("event", true, function (entry) {
        if (entry.interactionId && entry.duration > worstINP) {
            worstINP = entry.duration;
            metrics.inp = Math.round(worstINP);
        }
    });

    // Context that helps interpret the numbers.
    if (navigator.connection && navigator.connection.effectiveType) {
        metrics.net = navigator.connection.effectiveType;
    }
    if (navigator.deviceMemory) metrics.mem = navigator.deviceMemory;

    var sent = false;
    function flush() {
        if (sent) return;
        sent = true;
        var endpoint = window.KDCV_RUM_ENDPOINT;
        var body = JSON.stringify(metrics);
        if (endpoint && navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, body);
        } else if (window.console && console.debug) {
            console.debug("[cwv-rum]", metrics);
        }
    }

    addEventListener("visibilitychange", function () {
        if (document.visibilityState === "hidden") flush();
    });
    addEventListener("pagehide", flush);
})();
