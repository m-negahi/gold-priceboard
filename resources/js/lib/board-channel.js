/**
 * Client-e kanal-e ramz-shode-ye tablo (ADR-0009). Yek singleton dar har safhe:
 *
 * 1. Config az `[data-board-channel]` (seed, nonce, csrf, interval, URL ha).
 * 2. Kelid = SHA-256("price-board-channel-v1|" + seed) ba WebCrypto → AES-256-GCM.
 * 3. Aval `POST beacon` (neshane-haye morurgar + nonce) → avalin payload + nonce-e ba'di;
 *    ba'd har `interval` yek `GET feed` ba nonce-e yek-bar-masraf.
 * 4. Payload = base64(iv[12] ‖ ct ‖ tag[16]) → JSON `{ at, quotes[] }` → be har consumer dade mishe.
 *
 * Khata ha: 403 `board.challenge` → rooye safhe-ye tablo be challenge miravim, jaye dige saket
 * mimunim; nonce/handshake/beacon-e kohne → handshake (seed-e tazeh, nonce-e tazeh) va dobare;
 * 429 → Retry-After; decrypt-e nashode (charkhesh-e kelid) → handshake. Backoff-e namayi ta 60s.
 * Tab-e penhan poll ro negah midare va ba didan-e dobare foran mizane.
 *
 * Consumer: `{ mapCipher, apply(payload, map) }` — naghshe-ye selector-hash ba hamun kelid-e
 * avalie baz mishe va tu hafeze mimune (ba'd az charkhesh-e kelid dige ghabel-e baz-kardan nist).
 */
const CONTEXT = 'price-board-channel-v1|';
const MAX_BACKOFF_MS = 60_000;
const MAX_RECOVERIES = 3;

const encoder = new TextEncoder();
const decoder = new TextDecoder();

const state = {
    config: null,
    key: null,
    seed: null,
    nonce: null,
    interval: 5000,
    needBeacon: true,
    consumers: [],
    timer: null,
    inflight: false,
    failures: 0,
    recoveries: 0,
    started: false,
    stopped: false,
    status: 'idle',
    startedAt: performance.now(),
    previous: {},
};

function readConfig() {
    const el = document.querySelector('[data-board-channel]');

    if (!el) return null;

    try {
        return JSON.parse(el.dataset.config ?? '');
    } catch {
        return null;
    }
}

async function deriveKey(seed) {
    const digest = await crypto.subtle.digest('SHA-256', encoder.encode(CONTEXT + seed));

    return crypto.subtle.importKey('raw', digest, { name: 'AES-GCM' }, false, ['decrypt']);
}

function fromBase64(text) {
    const binary = atob(text);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    return bytes;
}

/** null vaghti tag nakhoone (kelid-e ghalat / charkhesh). */
export async function decrypt(key, ciphertext) {
    try {
        const bytes = fromBase64(ciphertext);
        const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: bytes.slice(0, 12), tagLength: 128 }, key, bytes.slice(12));

        return JSON.parse(decoder.decode(plain));
    } catch {
        return null;
    }
}

export function signals() {
    const ua = navigator.userAgent ?? '';

    return {
        webdriver: navigator.webdriver === true,
        headless_ua: /HeadlessChrome/i.test(ua),
        languages: navigator.languages?.length ?? 0,
        plugins: navigator.plugins?.length ?? 0,
        touch: (navigator.maxTouchPoints ?? 0) > 0,
        screen_w: window.screen?.width ?? 0,
        screen_h: window.screen?.height ?? 0,
        hp: document.querySelector('[data-board-hp]')?.value ?? '',
        elapsed_ms: Math.round(performance.now() - state.startedAt),
    };
}

async function request(url, { method = 'GET', body } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': state.config.csrf,
    };

    if (state.nonce) headers['X-Board-Nonce'] = state.nonce;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const response = await fetch(url, { method, credentials: 'same-origin', headers, body: body === undefined ? undefined : JSON.stringify(body) });
    const data = await response.json().catch(() => ({}));

    return { status: response.status, data, retryAfter: Number(response.headers.get('Retry-After') ?? 0) };
}

function setStatus(status) {
    state.status = status;

    for (const consumer of state.consumers) consumer.onStatus?.(status);
}

function schedule(ms) {
    clearTimeout(state.timer);

    if (state.stopped || document.hidden) return;

    state.timer = setTimeout(() => void tick(), ms);
}

function backoff() {
    state.failures++;

    return Math.min(state.interval * 2 ** state.failures, MAX_BACKOFF_MS);
}

async function handshake() {
    const { status, data } = await request(state.config.urls.handshake);

    if (status !== 200 || typeof data.seed !== 'string') return false;

    if (data.seed !== state.seed) {
        state.seed = data.seed;
        state.key = await deriveKey(data.seed);
    }

    state.nonce = data.nonce;
    state.interval = Number(data.interval) || state.interval;
    state.needBeacon = data.beacon_required !== false;

    return true;
}

async function deliver(payload) {
    for (const consumer of state.consumers) {
        if (!consumer.map) continue;

        try {
            consumer.apply(payload, consumer.map);
        } catch (error) {
            console.error('board consumer failed', error);
        }
    }
}

async function tick() {
    if (state.inflight || state.stopped || document.hidden) return;

    state.inflight = true;

    try {
        const url = state.needBeacon ? state.config.urls.beacon : state.config.urls.feed;
        const { status, data, retryAfter } = state.needBeacon
            ? await request(url, { method: 'POST', body: signals() })
            : await request(url);

        if (status === 200 && typeof data.payload === 'string') {
            state.nonce = data.nonce;
            state.interval = Number(data.interval) || state.interval;
            state.needBeacon = false;

            const payload = await decrypt(state.key, data.payload);

            if (payload === null) {
                // Kelid charkhide: seed-e tazeh begir va dobare (map ha ba kelid-e avalie baz shodand va mimunand).
                if (state.recoveries++ < MAX_RECOVERIES && (await handshake())) return schedule(0);

                setStatus('error');

                return schedule(backoff());
            }

            state.failures = 0;
            state.recoveries = 0;
            setStatus('live');
            await deliver(payload);

            return schedule(state.interval);
        }

        const code = data?.error?.code ?? '';

        if (status === 403 && code === 'board.challenge') {
            setStatus('challenge');

            if (state.config.page === 'board' && typeof data.error.url === 'string') window.location.assign(data.error.url);

            state.stopped = true;

            return;
        }

        if (status === 403 && /^board\.(nonce_|beacon_required|handshake_required)/.test(code)) {
            if (state.recoveries++ < MAX_RECOVERIES && (await handshake())) return schedule(0);
        }

        if (status === 419) {
            // CSRF/session kohne: yek handshake session ro zende mikone; age nashod backoff.
            if (state.recoveries++ < MAX_RECOVERIES && (await handshake())) return schedule(0);
        }

        if (status === 429) {
            setStatus('throttled');

            return schedule(Math.max(1, retryAfter) * 1000);
        }

        setStatus('error');
        schedule(backoff());
    } catch {
        setStatus('error');
        schedule(backoff());
    } finally {
        state.inflight = false;
    }
}

async function start() {
    if (state.started) return;

    state.started = true;
    state.config = readConfig();

    if (!state.config || !window.crypto?.subtle) {
        setStatus('unsupported');

        return;
    }

    state.seed = state.config.seed;
    state.nonce = state.config.nonce;
    state.interval = Number(state.config.interval) || 5000;
    state.key = await deriveKey(state.seed);

    for (const consumer of state.consumers) {
        if (!consumer.map && consumer.mapCipher) consumer.map = await decrypt(state.key, consumer.mapCipher);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(state.timer);
        else schedule(0);
    });

    schedule(0);
}

/**
 * Yek consumer sabt kon; kanal ba avalin consumer rah miofte.
 *
 * @param {{ mapCipher: string, apply: (payload: object, map: object) => void, onStatus?: (status: string) => void }} consumer
 */
export function subscribe(consumer) {
    state.consumers.push(consumer);

    if (state.started && state.key && consumer.mapCipher) {
        decrypt(state.key, consumer.mapCipher).then((map) => { consumer.map = map; });
    }

    queueMicrotask(() => void start());

    return () => {
        state.consumers = state.consumers.filter((c) => c !== consumer);
    };
}

/**
 * Yek darkhast-e mohr-shode-ye tak (masalan series-e chart-e `chart-pair`) az hamun kanal-e
 * singleton-e safhe — ADR-0044: yek kanal be azaye safhe, hich consumer-i (chart, jadval, ticker)
 * mojaz nist kanal-e khodesh-o besaze. Faghat pas az avalin cycle-e movafagh-e `tick()` (beacon +
 * avalin nonce) kar mikone ta beacon-e khodesh-o takrar nakone; nonce-e hamun zanjire-ro masraf
 * mikone, pas dar saf-e `state.inflight` mishine ta do darkhast-e hamzaman yek nonce ro nasuzunand.
 *
 * @returns {Promise<{ ok: boolean, status: number, data: object|null }>}
 */
export async function requestSealed(url) {
    queueMicrotask(() => void start());

    const deadline = performance.now() + 10_000;

    while (state.status !== 'live' && performance.now() < deadline) {
        if (state.stopped || state.status === 'unsupported' || state.status === 'challenge') return { ok: false, status: 0, data: null };

        await new Promise((resolve) => setTimeout(resolve, 100));
    }

    if (!state.config || !state.key) return { ok: false, status: 0, data: null };

    while (state.inflight) await new Promise((resolve) => setTimeout(resolve, 20));

    state.inflight = true;

    try {
        let { status, data } = await request(url);

        if (status === 403 && /^board\.(nonce_|beacon_required|handshake_required)/.test(data?.error?.code ?? '') && (await handshake())) {
            ({ status, data } = await request(url));
        }

        if (status !== 200 || typeof data.payload !== 'string') return { ok: false, status, data: null };

        state.nonce = data.nonce;

        const payload = await decrypt(state.key, data.payload);

        return { ok: payload !== null, status, data: payload };
    } finally {
        state.inflight = false;
    }
}

// ── Neveshtan tu DOM (moshtarak-e jadval va ticker) ────────────────────────────

const DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const number = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });
const STATE_LABEL = { fresh: 'زنده', stale: 'کهنه', closed: 'بازار بسته' };

export const fa = (value) => String(value).replace(/\d/g, (d) => DIGITS[Number(d)]);

export function rial(value) {
    return value === null || value === undefined ? '—' : number.format(Number(value));
}

export function signed(value) {
    const n = Number(value ?? 0);

    return (n > 0 ? '+' : n < 0 ? '−' : '') + number.format(Math.abs(n));
}

export function percent(value) {
    const n = Number(value ?? 0);

    return (n > 0 ? '+' : n < 0 ? '−' : '') + fa(Math.abs(n).toFixed(2).replace('.', '٫')) + '٪';
}

/** Matn-e yek cell-e naghshe ro (age tu DOM bashe) avaz mikone va element ro bar migardune. */
export function setText(id, text) {
    const el = id ? document.getElementById(id) : null;

    if (el && el.textContent !== text) el.textContent = text;

    return el;
}

/** Class-e ma'nayi (up/down/flat/stale/closed/fresh) ro ba esm-e tasadofi-ye hamin render jaygozin mikone. */
export function swapClass(el, classes, meaning) {
    if (!el) return;

    for (const cls of Object.values(classes)) if (cls !== classes.num) el.classList.remove(cls);
    if (classes[meaning]) el.classList.add(classes[meaning]);
}

/**
 * Yek quote ro tu cell-haye naghshe-ye in render minevise. Cell-i ke tu naghshe nist nadide gerefte
 * mishe (ticker faghat mid/pct dare).
 */
export function writeQuote(map, quote) {
    const cells = map.cells?.[quote.code];

    if (!cells) return;

    const classes = map.classes ?? {};

    setText(cells.mid, rial(quote.mid));
    setText(cells.buy, rial(quote.buy));
    setText(cells.sell, rial(quote.sell));
    setText(cells.at, quote.as_of_label ?? '');
    setText(cells.src, quote.source ?? '—');

    swapClass(setText(cells.chg, signed(quote.change_abs)), classes, quote.direction);
    swapClass(setText(cells.pct, percent(quote.change_pct)), classes, quote.direction);

    const badge = setText(cells.badge, STATE_LABEL[quote.state] ?? '');

    if (badge) {
        swapClass(badge, classes, quote.state);
        badge.classList.toggle('sr-only', quote.state === 'fresh');
    }

    const row = cells.row ? document.getElementById(cells.row) : null;
    const previous = state.previous[quote.code];

    if (row && previous !== undefined && previous !== quote.mid) {
        row.classList.add('bg-brand-soft');
        setTimeout(() => row.classList.remove('bg-brand-soft'), 1200);
    }

    state.previous[quote.code] = quote.mid;
}
