/**
 * Board e TV (P5-03): first paint SSR e؛ in component har 15s `/tv.json` ro
 * ba ETag poll mikone va kashi ha ro DOM-patch + flash mikone.
 *
 * ── Chera fetch e Alpine (na Livewire) ────────────────────────────────
 * Hamun dalil e `board.js`: payload baraye HAME ye namayeshgar ha
 * byte-identical ast va ETag dare (304 e arzun)؛ TV tamam e rooz roshan
 * mimune va `wire:poll` har 15s yek round-trip e kamel e Livewire mishod.
 *
 * Adad ha az server `display` (bcmath) miad — JS hich format e pul-i
 * nemikone؛ `value` (minor) faghat baraye jahat e flash moghayese mishe.
 */
const TEHRAN = 'Asia/Tehran';
// Taghvim e shamsi ba raghame LATIN (policy e proje).
const clockFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', {
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: TEHRAN,
});
const dateFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: TEHRAN,
});
const timeFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', {
    hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TEHRAN,
});

const CHANGE_TONE = {
    up: 'text-up-on-dark',
    down: 'text-down-on-dark',
    flat: 'text-ink-invert/50',
};

export default ({ url, interval = 15000 }) => ({
    etag: null,
    degraded: false,
    alarm: false,
    clock: '',
    today: '',
    updatedLabel: '',
    fullscreen: false,
    canFullscreen: false,
    paused: false,
    timer: null,
    ticker: null,
    wakeLock: null,

    init() {
        this.etag = this.$root.dataset.etag || null;
        this.setUpdated(this.$root.dataset.updatedAt || null);
        this.tick();

        this.ticker = setInterval(() => this.tick(), 1000);
        this.timer = setInterval(() => {
            if (!this.paused) this.refresh();
        }, interval);

        this.canFullscreen = typeof document.documentElement.requestFullscreen === 'function';
        document.addEventListener('fullscreenchange', () => {
            this.fullscreen = document.fullscreenElement !== null;
        });

        document.addEventListener('visibilitychange', () => {
            this.paused = document.hidden;
            if (!this.paused) {
                this.refresh();
                this.keepAwake();
            }
        });

        this.keepAwake();
    },

    destroy() {
        clearInterval(this.timer);
        clearInterval(this.ticker);
    },

    tick() {
        const now = new Date();
        this.clock = clockFmt.format(now);
        // `format()` e fa-IR ba raghame Latin «1405 مهر 14, سه‌شنبه» mide (bidi
        // e kharab) — tartib e Farsi ro khodemun az part ha misazim.
        const part = Object.fromEntries(dateFmt.formatToParts(now).map((p) => [p.type, p.value]));
        this.today = `${part.weekday} ${part.day} ${part.month} ${part.year}`;
    },

    setUpdated(iso) {
        if (!iso) return;
        const date = new Date(iso);
        if (!Number.isNaN(date.getTime())) this.updatedLabel = timeFmt.format(date);
    },

    /** Namayeshgar e TV nabayad khamoosh/screensaver beshe (age morurgar ejaze bede). */
    async keepAwake() {
        try {
            if ('wakeLock' in navigator && !document.hidden) {
                this.wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch {
            // Ejaze nadad (masalan bedun e HTTPS) — mohem nist.
        }
    },

    async toggleFullscreen() {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else await document.documentElement.requestFullscreen();
        } catch {
            // Morurgar rad kard — dokme bi-asar mimune.
        }
    },

    async refresh() {
        let response;

        try {
            response = await fetch(url, {
                headers: this.etag ? { 'If-None-Match': this.etag } : {},
                credentials: 'same-origin',
            });
        } catch {
            return; // Shabake ghat' — poll e ba'di.
        }

        if (response.status === 304 || !response.ok) return;

        this.etag = response.headers.get('ETag') || this.etag;
        const data = await response.json().catch(() => null);

        if (data) this.apply(data);
    },

    apply(data) {
        this.degraded = Boolean(data.degraded);
        this.alarm = Boolean(data.alarm);
        this.setUpdated(data.updatedAt);

        for (const row of data.rows) {
            const tile = this.$root.querySelector(`[data-tv-tile="${row.symbol}"]`);

            // Symbol e jadid (ya avvalin gheymat ru safhe ye khali): TV
            // tamam e rooz baz mimune — yek reload e sade kashi ha ro misaze.
            if (!tile) {
                window.location.reload();
                return;
            }

            const prev = Number(tile.dataset.value);
            const price = tile.querySelector('[data-tv-price]');

            price.textContent = row.display;
            price.classList.toggle('text-ink-invert/40', row.isStale);
            tile.toggleAttribute('data-stale', row.isStale);
            tile.querySelector('[data-tv-stale]')?.classList.toggle('hidden', !row.isStale);

            const change = tile.querySelector('[data-tv-change]');
            const arrow = row.direction === 'up' ? '▲' : row.direction === 'down' ? '▼' : '';
            change.textContent = `${arrow} ${row.change}٪`;
            change.classList.remove(...Object.values(CHANGE_TONE).flatMap((c) => c.split(' ')));
            change.classList.add(CHANGE_TONE[row.direction] ?? CHANGE_TONE.flat);

            if (!Number.isNaN(prev) && row.value !== prev) this.flash(tile, row.value > prev);
            tile.dataset.value = String(row.value);
        }
    },

    flash(tile, up) {
        tile.classList.remove('tv-flash-up', 'tv-flash-down');
        void tile.offsetWidth; // reflow ta animation dobare ejra beshe
        tile.classList.add(up ? 'tv-flash-up' : 'tv-flash-down');
    },
});
