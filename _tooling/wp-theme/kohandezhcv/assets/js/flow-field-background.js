/* Flow field background — vanilla port of the NeuralBackground React component.
   Green particles drift along a flow field with mouse repulsion and trails. */
(() => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const COLOR = '#a8ff46';
    const TRAIL = 'rgba(8, 11, 13, 0.12)';
    const COUNT = 450;
    const SPEED = 0.8;

    const container = document.createElement('div');
    container.className = 'flow-field-bg';
    container.setAttribute('aria-hidden', 'true');
    const canvas = document.createElement('canvas');
    container.append(canvas);
    document.body.prepend(container);

    const ctx = canvas.getContext('2d');
    let width = 0;
    let height = 0;
    let particles = [];
    const mouse = { x: -1000, y: -1000 };

    class Particle {
        constructor() { this.reset(); }
        reset() {
            this.x = Math.random() * width;
            this.y = Math.random() * height;
            this.vx = 0;
            this.vy = 0;
            this.age = 0;
            this.life = Math.random() * 200 + 100;
        }
        update() {
            const angle = (Math.cos(this.x * 0.005) + Math.sin(this.y * 0.005)) * Math.PI;
            this.vx += Math.cos(angle) * 0.2 * SPEED;
            this.vy += Math.sin(angle) * 0.2 * SPEED;

            const dx = mouse.x - this.x;
            const dy = mouse.y - this.y;
            const distance = Math.hypot(dx, dy);
            if (distance < 150) {
                const force = (150 - distance) / 150;
                this.vx -= dx * force * 0.05;
                this.vy -= dy * force * 0.05;
            }

            this.x += this.vx;
            this.y += this.vy;
            this.vx *= 0.95;
            this.vy *= 0.95;

            if (++this.age > this.life) this.reset();
            if (this.x < 0) this.x = width;
            if (this.x > width) this.x = 0;
            if (this.y < 0) this.y = height;
            if (this.y > height) this.y = 0;
        }
        draw() {
            ctx.globalAlpha = 1 - Math.abs(this.age / this.life - 0.5) * 2;
            ctx.fillStyle = COLOR;
            ctx.fillRect(this.x, this.y, 1.5, 1.5);
        }
    }

    const init = () => {
        width = container.clientWidth;
        height = container.clientHeight;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        particles = Array.from({ length: COUNT }, () => new Particle());
    };

    const animate = () => {
        ctx.globalAlpha = 1;
        ctx.fillStyle = TRAIL;
        ctx.fillRect(0, 0, width, height);
        particles.forEach((p) => { p.update(); p.draw(); });
        requestAnimationFrame(animate);
    };

    window.addEventListener('resize', init);
    window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
    window.addEventListener('mouseout', () => { mouse.x = -1000; mouse.y = -1000; });

    init();
    animate();
})();
