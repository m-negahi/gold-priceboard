/**
 * Tablo ye omumi (Phase 5): first paint SSR e، in component har 15s
 * `/board.json` ro poll mikone va gheymat ha ro be-rooz + flash mikone.
 *
 * ── Chera fetch e Alpine (na Livewire) inja mojaz e ──────────────────
 * Payload baraye HAME ye bazdid-konande BYTE-IDENTICAL e va ETag dare؛ yek
 * `wire:poll` ya'ni yek round-trip e kamel e Livewire be ezaye har bazdid.
 * In yek read e omumi ye hamegani-e، daghighan jayi ke fetch e sabok dorost
 * e. (resources/views/CLAUDE.md estesna ye «gheymat e zende» ro mishenase.)
 */
/**
 * Formatter haye LATIN (en-US): policy e proje «raghame Latin hame ja» ast
 * va SSR ba `number_format` (Latin) render mikone — JS bayad hamun bashe،
 * na raghame Farsi ye `digits.js`. Cache-shode (sakhtanesh geroon e).
 */
const nf = (precision) => new Intl.NumberFormat('en-US', {
    minimumFractionDigits: precision,
    maximumFractionDigits: precision,
});
const formatters = new Map();
const num = (value, precision) => {
    if (!formatters.has(precision)) formatters.set(precision, nf(precision));
    return formatters.get(precision).format(value);
};

export default ({ url, interval = 15000 }) => ({
    etag: null,
    degraded: false,
    updatedAt: null,
    // key: `${symbol}:${provider}` -> آخرین sell (baraye tashkhis e flash).
    lastSell: {},
    timer: null,
    paused: false,

    init() {
        // ETag e avvaliye az SSR (ta avvalin poll alaki 200 nagire).
        this.etag = this.$root.dataset.etag || null;

        this.loop();

        // Tab e makhfi poll nakone (sarfe-juyi + rate).
        document.addEventListener('visibilitychange', () => {
            this.paused = document.hidden;
            if (!this.paused) this.refresh();
        });
    },

    loop() {
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (!this.paused) this.refresh();
        }, interval);
    },

    async refresh() {
        let response;

        try {
            response = await fetch(url, {
                headers: this.etag ? { 'If-None-Match': this.etag } : {},
                credentials: 'same-origin',
            });
        } catch {
            return; // Shabake ghat' — poll e ba'di dobare emtehan mikone.
        }

        if (response.status === 304) return; // Bi-taghir.
        if (!response.ok) return;

        this.etag = response.headers.get('ETag') || this.etag;

        const data = await response.json().catch(() => null);

        if (data) this.apply(data);
    },

    apply(data) {
        this.degraded = data.degraded;
        this.updatedAt = data.updatedAt;

        for (const row of data.rows) {
            for (const [provider, cell] of Object.entries(row.cells)) {
                const key = `${row.symbol}:${provider}`;
                const prev = this.lastSell[key];
                const el = this.$root.querySelector(`[data-cell="${key}"]`);

                if (el) {
                    el.querySelector('[data-price]').textContent = cell.sellDisplay ?? this.fmt(cell.sell, row.divisor, row.precision);
                    this.setChange(el, cell);

                    // Flash: sabz age bala raft، ghermez age paeen.
                    if (prev !== undefined && cell.sell !== prev) {
                        this.flash(el, cell.sell > prev);
                    }
                }

                this.lastSell[key] = cell.sell;
            }

            // Kamtarin/bishtarin/miangin.
            this.setStat(row.symbol, 'min', row.min, row.divisor, row.precision, row.minDisplay);
            this.setStat(row.symbol, 'max', row.max, row.divisor, row.precision, row.maxDisplay);
            this.setStat(row.symbol, 'avg', row.avg, row.divisor, row.precision, row.avgDisplay);
        }
    },

    setChange(el, cell) {
        const badge = el.querySelector('[data-change]');
        if (!badge) return;

        const up = cell.direction === 'up';
        const down = cell.direction === 'down';
        badge.textContent = cell.changePercent ? `${up ? '▲' : down ? '▼' : ''}${num(Math.abs(cell.changePercent), 2)}٪` : '';
        badge.className = 'num text-xs ' + (up ? 'text-up' : down ? 'text-down' : 'text-muted-2');
    },

    setStat(symbol, kind, value, divisor, precision, display) {
        const el = this.$root.querySelector(`[data-stat="${symbol}:${kind}"]`);
        if (el && value !== null && value !== undefined) el.textContent = display ?? this.fmt(value * divisor, divisor, precision);
    },

    flash(el, up) {
        const cls = up ? 'flash-up' : 'flash-down';
        el.classList.remove('flash-up', 'flash-down');
        // reflow ta animation dobare run she
        void el.offsetWidth;
        el.classList.add(cls);
    },

    fmt(sell, divisor, precision) {
        return num((sell ?? 0) / (divisor || 1), precision);
    },
});
