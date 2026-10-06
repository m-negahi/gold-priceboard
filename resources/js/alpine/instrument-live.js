/**
 * Poll e zende ye safhe ye instrument: har 15s `/board.json` (ba ETag) ro
 * mikhune va faghat radif e hamin symbol ro ru safhe be-rooz mikone —
 * gheymat e har manba'، kamtarin/bishtarin/miangin. SSR hamun reshte haye
 * `*Display` ro dare، pas hich format e client-side-i lazem nist.
 */
export default ({ url, symbol, interval = 15000 }) => ({
    etag: null,
    degraded: false,
    timer: null,

    init() {
        this.timer = setInterval(() => this.poll(), interval);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.poll();
        });
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
    },

    async poll() {
        if (document.visibilityState !== 'visible') return;

        try {
            const headers = { Accept: 'application/json' };
            if (this.etag) headers['If-None-Match'] = this.etag;

            const res = await fetch(url, { headers, credentials: 'same-origin' });
            if (res.status === 304) return;
            if (!res.ok) return;

            this.etag = res.headers.get('ETag');
            this.apply(await res.json());
        } catch {
            // Shabake ghat' — SSR mimune.
        }
    },

    apply(board) {
        const row = (board.rows || []).find((r) => r.symbol === symbol);
        if (!row) return;

        this.degraded = !!board.degraded;

        for (const [key, cell] of Object.entries(row.cells || {})) {
            const el = this.$root.querySelector(`[data-provider-cell="${key}"]`);
            if (!el) continue;

            const price = el.querySelector('[data-price]');
            if (price) {
                price.textContent = cell.sellDisplay ?? '—';
                price.classList.toggle('text-muted-2', !!cell.isStale);
            }

            const change = el.querySelector('[data-change]');
            if (change) {
                const pct = Number(cell.changePercent || 0);
                change.textContent = pct === 0 ? '' : `${cell.direction === 'up' ? '▲' : (cell.direction === 'down' ? '▼' : '')}${Math.abs(pct).toFixed(2)}٪`;
                change.classList.toggle('text-up', cell.direction === 'up');
                change.classList.toggle('text-down', cell.direction === 'down');
            }
        }

        for (const [stat, value] of [['min', row.minDisplay], ['max', row.maxDisplay], ['avg', row.avgDisplay]]) {
            const el = this.$root.querySelector(`[data-stat="${stat}"]`);
            if (el && value) el.textContent = value;
        }
    },
});
