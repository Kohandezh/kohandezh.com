/* Glowing effect — pointer-following green glow for image frames.
   Vanilla port of the GlowingEffect React component (proximity + eased angle).
   Applies to the avatar portrait plus the work / service / certificate image
   frames so any framed image lights up as the pointer nears it. */
(() => {
    const SELECTORS = [
        '.sidebar-user .user-image .image',
        '.wg-work .work-image',
        '.service-image .wrap_image',
        '.award_img',
        '.certificate-card-media',
    ];

    const PROXIMITY = 96;
    const INACTIVE_ZONE = 0.35;

    const attach = (frameHost) => {
        if (!frameHost || frameHost.querySelector(':scope > .glow-frame')) return;
        if (getComputedStyle(frameHost).position === 'static') {
            frameHost.style.position = 'relative';
        }
        const glow = document.createElement('span');
        glow.className = 'glow-frame';
        glow.setAttribute('aria-hidden', 'true');
        frameHost.append(glow);

        let currentAngle = 0;
        let targetAngle = 0;
        let raf = 0;

        const loop = () => {
            const diff = ((targetAngle - currentAngle + 180) % 360 + 360) % 360 - 180;
            currentAngle += diff * 0.08;
            glow.style.setProperty('--start', String(currentAngle + 90));
            if (Math.abs(diff) > 0.1) raf = requestAnimationFrame(loop);
            else raf = 0;
        };

        document.body.addEventListener('pointermove', (event) => {
            const box = frameHost.getBoundingClientRect();
            if (!box.width || !box.height) return;
            const cx = box.left + box.width / 2;
            const cy = box.top + box.height / 2;
            const dist = Math.hypot(event.clientX - cx, event.clientY - cy);

            if (dist < Math.min(box.width, box.height) * 0.5 * INACTIVE_ZONE) {
                glow.style.setProperty('--active', '0');
                return;
            }
            const active =
                event.clientX > box.left - PROXIMITY && event.clientX < box.right + PROXIMITY &&
                event.clientY > box.top - PROXIMITY && event.clientY < box.bottom + PROXIMITY;
            glow.style.setProperty('--active', active ? '1' : '0');
            if (!active) return;

            targetAngle = (180 * Math.atan2(event.clientY - cy, event.clientX - cx)) / Math.PI;
            if (!raf) raf = requestAnimationFrame(loop);
        }, { passive: true });
    };

    const init = () => {
        SELECTORS.forEach((sel) => document.querySelectorAll(sel).forEach(attach));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
