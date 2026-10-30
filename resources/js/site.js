/**
 * Small, dependency-free animations for AurumBit.
 * Everything re-runs after Livewire's wire:navigate page swaps.
 */

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Fade elements up as they scroll into view. */
function initReveal() {
    const items = document.querySelectorAll('[data-reveal]:not(.is-visible)');

    if (reducedMotion() || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    items.forEach((el) => observer.observe(el));
}

/** Count numbers up from zero, e.g. <span data-count="1250" data-decimals="0">. */
function initCounters() {
    document.querySelectorAll('[data-count]:not([data-counted])').forEach((el) => {
        el.dataset.counted = '1';
        const target = parseFloat(el.dataset.count);
        const decimals = parseInt(el.dataset.decimals || '0', 10);
        const format = (n) => n.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

        if (reducedMotion() || Number.isNaN(target)) {
            el.textContent = format(target);
            return;
        }

        const run = () => {
            const start = performance.now();
            const duration = 1400;
            const step = (now) => {
                const t = Math.min(1, (now - start) / duration);
                el.textContent = format(target * (1 - Math.pow(1 - t, 3)));
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                run();
                observer.disconnect();
            }
        });
        observer.observe(el);
    });
}

/** Live BTC and gold tickers: fill [data-price="btc|gold"] and [data-change="btc|gold"]. */
const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
let priceTimer = null;
let lastPrices = {};

function renderPrices(data) {
    ['btc', 'gold'].forEach((asset) => {
        const cents = data[`${asset}_cents`];
        const change = data[`${asset}_change`];

        document.querySelectorAll(`[data-price="${asset}"]`).forEach((el) => {
            if (cents == null) return;
            el.textContent = usd.format(cents / 100);
            if (lastPrices[asset] != null && lastPrices[asset] !== cents && !reducedMotion()) {
                el.classList.remove('price-up', 'price-down');
                void el.offsetWidth;
                el.classList.add(cents > lastPrices[asset] ? 'price-up' : 'price-down');
            }
        });

        document.querySelectorAll(`[data-change="${asset}"]`).forEach((el) => {
            if (change == null) return;
            el.textContent = `${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
            el.classList.remove('text-zinc-400');
            el.classList.toggle('text-green-600', change >= 0);
            el.classList.toggle('dark:text-green-400', change >= 0);
            el.classList.toggle('text-red-600', change < 0);
            el.classList.toggle('dark:text-red-400', change < 0);
        });

        if (cents != null) lastPrices[asset] = cents;
    });

    document.querySelectorAll('[data-price-updated]').forEach((el) => {
        if (data.updated_at) el.textContent = new Date(data.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    });
}

async function refreshPrices(url) {
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (response.ok) renderPrices(await response.json());
    } catch {
        // Keep showing the last prices.
    }
}

function initPrices() {
    clearInterval(priceTimer);
    const source = document.querySelector('[data-prices-url]');
    if (!source) return;

    const url = source.dataset.pricesUrl;
    refreshPrices(url);
    priceTimer = setInterval(() => document.visibilityState === 'visible' && refreshPrices(url), 60000);
}

/** Gentle network of gold particles behind the hero. */
let heroFrame = null;

function initHeroCanvas() {
    cancelAnimationFrame(heroFrame);
    const canvas = document.querySelector('[data-hero-canvas]');
    if (!canvas || reducedMotion()) return;

    const ctx = canvas.getContext('2d');
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let width = 0;
    let height = 0;
    let points = [];

    const resize = () => {
        width = canvas.clientWidth;
        height = canvas.clientHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const count = Math.round(Math.min(70, (width * height) / 16000));
        points = Array.from({ length: count }, () => ({
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.35,
            vy: (Math.random() - 0.5) * 0.35,
            r: Math.random() * 1.8 + 0.6,
        }));
    };

    resize();
    window.addEventListener('resize', resize, { passive: true });

    const draw = () => {
        if (!document.body.contains(canvas)) {
            window.removeEventListener('resize', resize);
            return;
        }

        ctx.clearRect(0, 0, width, height);

        for (const p of points) {
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0 || p.x > width) p.vx *= -1;
            if (p.y < 0 || p.y > height) p.vy *= -1;
        }

        for (let i = 0; i < points.length; i++) {
            for (let j = i + 1; j < points.length; j++) {
                const a = points[i];
                const b = points[j];
                const d = Math.hypot(a.x - b.x, a.y - b.y);
                if (d < 120) {
                    ctx.strokeStyle = `rgba(224, 180, 52, ${0.18 * (1 - d / 120)})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                }
            }
        }

        for (const p of points) {
            ctx.fillStyle = 'rgba(236, 201, 85, 0.75)';
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        }

        heroFrame = requestAnimationFrame(draw);
    };

    draw();
}

/** Copy buttons anywhere on the page: <button data-copy="text">. One listener, so it also works inside Livewire updates. */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    try {
        await navigator.clipboard.writeText(button.dataset.copy);
        const label = button.querySelector('[data-copy-label]');
        if (label) {
            const original = label.textContent;
            label.textContent = 'Copied';
            setTimeout(() => (label.textContent = original), 1600);
        }
    } catch {
        // Clipboard blocked; the text is still visible to copy by hand.
    }
});

/** Header gets a solid background once the page is scrolled. */
function initHeader() {
    const header = document.querySelector('[data-site-header]');
    if (!header) return;
    const update = () => header.toggleAttribute('data-scrolled', window.scrollY > 8);
    update();
    window.addEventListener('scroll', update, { passive: true });
}

function boot() {
    initReveal();
    initCounters();
    initPrices();
    initHeroCanvas();
    initHeader();
}

document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:navigated', boot);
