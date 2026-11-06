/**
 * Small, dependency-free animations for Hashvale.
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
/** Prices quoted in USDT, e.g. "77,196.00" for BTC/USDT. */
const usdtNumber = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
let priceTimer = null;
let lastPrices = {};

function renderPrices(data) {
    ['btc', 'gold', 'eth', 'sol'].forEach((asset) => {
        const cents = data[`${asset}_cents`];
        const change = data[`${asset}_change`];

        document.querySelectorAll(`[data-price="${asset}"]`).forEach((el) => {
            if (cents == null) return;
            el.textContent = el.dataset.priceFormat === 'usdt' ? usdtNumber.format(cents / 100) : usd.format(cents / 100);
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
        if (data.updated_at) el.textContent = new Date(data.updated_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    });

    // Let calculators recalculate their coin amounts with the new prices.
    document.dispatchEvent(new CustomEvent('prices:updated', { detail: { ...lastPrices } }));
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
                    ctx.strokeStyle = `rgba(22, 82, 240, ${0.16 * (1 - d / 120)})`;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                }
            }
        }

        for (const p of points) {
            ctx.fillStyle = 'rgba(22, 82, 240, 0.45)';
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        }

        heroFrame = requestAnimationFrame(draw);
    };

    draw();
}

/**
 * Copy text to the clipboard. navigator.clipboard only exists on https:// and
 * localhost, so plain http:// sites fall back to a hidden textarea + execCommand.
 */
async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
    document.body.append(area);
    area.select();
    area.setSelectionRange(0, text.length);
    const copied = document.execCommand('copy');
    area.remove();
    return copied;
}

/** Copy buttons anywhere on the page: <button data-copy="text">. One listener, so it also works inside Livewire updates. */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    const label = button.querySelector('[data-copy-label]');
    const original = label?.dataset.copyOriginal ?? label?.textContent;
    if (label) label.dataset.copyOriginal = original;

    let copied = false;
    try {
        copied = await copyText(button.dataset.copy);
    } catch {
        copied = false;
    }

    if (label) {
        label.textContent = copied ? 'Copied' : 'Press Ctrl+C to copy';
        setTimeout(() => (label.textContent = original), 1600);
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
    initCalculators();
    initConverters();
}

/** Coin ⇄ USDT converters: <div data-converter data-prices='{"BTC":76890,...}'>. Display only. */
function initConverters() {
    document.querySelectorAll('[data-converter]:not([data-converter-ready])').forEach((root) => {
        root.dataset.converterReady = '1';
        const prices = JSON.parse(root.dataset.prices || '{}');
        const coin = root.querySelector('[data-converter-coin]');
        const coinAmount = root.querySelector('[data-converter-coin-amount]');
        const usdtAmount = root.querySelector('[data-converter-usdt]');
        const rate = root.querySelector('[data-converter-rate]');
        const parse = (text) => {
            const value = parseFloat(String(text).replace(/,/g, ''));
            return Number.isFinite(value) && value >= 0 ? value : null;
        };
        const label = () => (coin.value === 'GOLD' ? 'oz of gold' : coin.value);

        const fromCoin = () => {
            const amount = parse(coinAmount.value);
            usdtAmount.value = amount === null ? '' : (amount * prices[coin.value]).toFixed(2);
            rate.textContent = `1 ${label()} = ${usdtNumber.format(prices[coin.value])} USDT`;
        };
        const fromUsdt = () => {
            const amount = parse(usdtAmount.value);
            coinAmount.value = amount === null ? '' : String(+(amount / prices[coin.value]).toFixed(8));
        };

        coinAmount.addEventListener('input', fromCoin);
        usdtAmount.addEventListener('input', fromUsdt);
        coin.addEventListener('change', fromCoin);
        fromCoin();
    });
}

/** USDT text → micros (BigInt), or null. Mirrors App\Support\Usdt::toMicros. */
function toMicros(text) {
    const match = /^(\d{1,9})(?:\.(\d{1,2}))?$/.exec(String(text).trim().replace(/,/g, ''));
    if (!match) return null;
    return BigInt(match[1]) * 1000000n + BigInt((match[2] || '').padEnd(6, '0'));
}

/** Micros (BigInt) → USDT text with 2 decimals, rounded down. Mirrors App\Support\Usdt::format. */
function formatUsdt(micros) {
    const whole = micros / 1000000n;
    const fraction = (micros % 1000000n).toString().padStart(6, '0').slice(0, 2);
    return `${whole.toLocaleString('en-US')}.${fraction} USDT`;
}

/** Returns calculators: <div data-calculator data-plans='[...]'>. */
function initCalculators() {
    document.querySelectorAll('[data-calculator]:not([data-calc-ready])').forEach((root) => {
        root.dataset.calcReady = '1';
        const plans = JSON.parse(root.dataset.plans || '[]');
        const livePrices = JSON.parse(root.dataset.livePrices || '{}');
        const goldPriceCents = () => livePrices.gold || parseInt(root.dataset.goldPrice || '0', 10);
        /**
         * "≈ 0.01340000 BTC" for a USDT amount at the live coin price, "≈ 0.0261 oz (0.81 g) of gold"
         * for gold plans, or '' for USDT and Mix plans.
         */
        const inCoin = (plan, micros) => {
            if (plan.mix) {
                // A mix has no single coin: show it in each coin the user can withdraw in.
                return [['btc', 'BTC', 6], ['eth', 'ETH', 5], ['sol', 'SOL', 4]]
                    .filter(([key]) => livePrices[key])
                    .map(([key, ticker, decimals]) => `${(Number(micros) / 10000 / livePrices[key]).toFixed(decimals)} ${ticker}`)
                    .join(' · ')
                    .replace(/^(?=.)/, '≈ ');
            }
            const cents = plan.priceKey ? livePrices[plan.priceKey] : null;
            if (!cents) return '';
            const units = Number(micros) / 10000 / cents;
            if (!plan.gold) return `≈ ${units.toFixed(8)} ${plan.ticker}`;
            const grams = units * 31.1034768;
            return `≈ ${units.toFixed(4)} oz (${grams.toFixed(grams < 1 ? 4 : 2)} g) of gold`;
        };
        const select = root.querySelector('[data-calc-plan]');
        const input = root.querySelector('[data-calc-amount]');
        const out = (key, text, sub = '') => {
            root.querySelector(`[data-calc-${key}]`).textContent = text;
            root.querySelector(`[data-calc-${key}-sub]`).textContent = sub;
        };

        const update = () => {
            const plan = plans.find((p) => String(p.id) === select.value) || plans[0];
            const micros = toMicros(input.value);
            root.querySelector('[data-calc-limits]').textContent = `Min ${plan.min} USDT${plan.max ? ` · Max ${plan.max} USDT` : ''}`;
            out('rate', `${plan.rate}%`, `${plan.daily}% a day for ${plan.days} days`);

            if (micros === null || micros <= 0n) {
                ['daily', 'profit', 'total'].forEach((key) => out(key, '—'));
                root.querySelector('[data-calc-summary]').textContent = 'Enter an amount in USDT, e.g. 1000';
                return;
            }

            const profit = (micros * BigInt(plan.bps)) / 10000n;
            const daily = profit / BigInt(plan.days);
            const total = micros + profit;
            const ends = new Date(Date.now() + plan.days * 86400000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const outside = micros < BigInt(plan.minMicros) || (plan.maxMicros && micros > BigInt(plan.maxMicros));

            out('daily', formatUsdt(daily), inCoin(plan, daily));
            out('profit', '+' + formatUsdt(profit), inCoin(plan, profit));
            out('total', formatUsdt(total), inCoin(plan, total));
            root.querySelector('[data-calc-summary]').textContent = outside
                ? `This amount is outside the ${plan.name} limits.`
                : `Invest ${formatUsdt(micros)} in ${plan.name} today and receive ${formatUsdt(total)} on ${ends} (after ${plan.days} days).`
                    + (! plan.gold && inCoin(plan, total)
                        ? (plan.mix
                            ? ` Withdrawn in one coin, that is about ${inCoin(plan, total).slice(2).replaceAll(' · ', ', or ')} at live prices.`
                            : ` If you withdraw in ${plan.ticker}, that is ${inCoin(plan, total).slice(2)} at the live price.`)
                        : '')
                    + (plan.gold && goldPriceCents()
                        ? ` At today's prices that is about ${(Number(total / 10000n) / goldPriceCents()).toFixed(3)} oz of gold.`
                        : '');
        };

        select.addEventListener('change', update);
        input.addEventListener('input', update);
        document.addEventListener('prices:updated', (event) => {
            if (!root.isConnected) return;
            Object.assign(livePrices, event.detail);
            update();
        });
        update();
    });
}

document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:navigated', boot);
