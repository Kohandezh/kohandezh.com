/* Pixel canvas — green pixel shimmer on hover for blog cards.
   Vanilla port of the pixel-canvas component; uses the site's --primary green. */
(() => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const GAP = 8;
    const SIZE = 3;
    const COLORS = ['rgba(0,222,81,.55)', 'rgba(0,222,81,.3)', 'rgba(0,222,81,.15)'];

    const SELECTOR = '.section-blog .blog-local-item';

    const attach = (card) => {
        // The homepage blog list is re-rendered by home-blog-feed.js AFTER this
        // script runs, so a one-shot querySelectorAll left every regenerated
        // tile without a canvas — which is why only some tiles shimmered. The
        // guard keeps re-scans from stacking duplicate canvases on a card.
        if (card.dataset.pixelCanvas === 'on') return;
        card.dataset.pixelCanvas = 'on';

        const canvas = document.createElement('canvas');
        canvas.className = 'pixel-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        card.prepend(canvas);
        const ctx = canvas.getContext('2d');
        let pixels = [];
        let raf = 0;
        let leaving = false;

        const build = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = card.clientWidth;
            const h = card.clientHeight;
            canvas.width = w * dpr;
            canvas.height = h * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            pixels = [];
            for (let x = 0; x < w; x += GAP) {
                for (let y = 0; y < h; y += GAP) {
                    pixels.push({
                        x, y,
                        color: COLORS[Math.floor(Math.random() * COLORS.length)],
                        delay: Math.hypot(x - w / 2, y - h / 2) * 2 + Math.random() * 300,
                        alpha: 0,
                    });
                }
            }
        };

        const tick = (start) => (now) => {
            const t = now - start;
            ctx.clearRect(0, 0, card.clientWidth, card.clientHeight);
            let animating = false;
            pixels.forEach((p) => {
                if (leaving) {
                    p.alpha = Math.max(0, p.alpha - 0.04);
                } else {
                    const target = t > p.delay ? 0.5 + 0.5 * Math.sin((t - p.delay) / 260) : 0;
                    p.alpha += (Math.max(0, target) - p.alpha) * 0.1;
                }
                if (p.alpha > 0.01) {
                    animating = true;
                    ctx.globalAlpha = p.alpha;
                    ctx.fillStyle = p.color;
                    ctx.fillRect(p.x, p.y, SIZE, SIZE);
                }
            });
            ctx.globalAlpha = 1;
            if (!leaving || animating) raf = requestAnimationFrame(tick(start));
        };

        card.addEventListener('mouseenter', () => {
            cancelAnimationFrame(raf);
            leaving = false;
            build();
            raf = requestAnimationFrame((now) => { raf = requestAnimationFrame(tick(now)); });
        });
        card.addEventListener('mouseleave', () => { leaving = true; });
    };

    const scan = () => document.querySelectorAll(SELECTOR).forEach(attach);

    scan();

    // Re-scan whenever the feed swaps in new cards, so every tile behaves the
    // same regardless of whether it came from the static markup or the JSON
    // feed. Observing the section (not the whole body) keeps this cheap.
    const host = document.querySelector('.section-blog');
    if (host && 'MutationObserver' in window) {
        let queued = false;
        new MutationObserver(() => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; scan(); });
        }).observe(host, { childList: true, subtree: true });
    }
})();
