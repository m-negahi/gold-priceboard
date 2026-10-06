/**
 * Chart e panel e admin (P5-08) — KLineCharts 10، candle ya khati.
 *
 * Data az `/dashboard/charts/data` (posht e auth). Baze haye haftegi/mahane/
 * salane SERVER-side ba taghvim e Jalali tajmi' mishan — inja faghat
 * namayesh. Taghir e symbol/provider/baze bedun e reload، va URL ba
 * `replaceState` be-rooz mishe ta link e chart ghabel e share bashe.
 */

// ChartPeriod e ma -> Period e KLineCharts.
const PERIODS = {
    m5: { span: 5, type: 'minute' },
    m15: { span: 15, type: 'minute' },
    h1: { span: 1, type: 'hour' },
    h4: { span: 4, type: 'hour' },
    d1: { span: 1, type: 'day' },
    w1: { span: 1, type: 'week' },
    mo1: { span: 1, type: 'month' },
    y1: { span: 1, type: 'year' },
};

export default ({ url, symbol, period, precisions }) => ({
    symbol,
    period,
    provider: '',
    kind: 'candle',
    loading: false,
    empty: false,
    degraded: false,
    resolvedProvider: '',
    chart: null,
    klinecharts: null,

    async init() {
        this.readQuery();
        this.klinecharts = await import('klinecharts');
        await this.$nextTick();

        this.chart = this.klinecharts.init(this.$refs.canvas, { styles: this.chartStyles() });
        this.chart.setDataLoader({
            getBars: async ({ callback }) => {
                callback(await this.fetchBars(), false);
            },
        });
        this.applySymbol();
        this.chart.setPeriod(PERIODS[this.period]);
    },

    destroy() {
        if (this.chart && this.klinecharts) this.klinecharts.dispose(this.$refs.canvas);
    },

    /** Taghir e ticker getBars ro dobare seda mizane. */
    applySymbol() {
        this.chart.setSymbol({
            ticker: this.symbol,
            pricePrecision: precisions[this.symbol] ?? 0,
            volumePrecision: 0,
        });
    },

    changeSymbol() {
        this.writeQuery();
        this.applySymbol();
    },

    changeProvider() {
        this.writeQuery();
        // Ticker hamun-e — `resetData` data ro dobare az getBars migire.
        this.chart.resetData();
    },

    setPeriod(next) {
        if (next === this.period) return;

        this.period = next;
        this.writeQuery();
        this.chart.setPeriod(PERIODS[next]);
    },

    setKind(next) {
        this.kind = next;
        this.chart.setStyles({ candle: { type: next === 'line' ? 'area' : 'candle_solid' } });
    },

    /** @returns {Promise<Array>} KLineData[] */
    async fetchBars() {
        this.loading = true;
        this.empty = false;

        let data = null;

        try {
            const params = new URLSearchParams({ symbol: this.symbol, period: this.period });
            if (this.provider) params.set('provider', this.provider);

            const res = await fetch(`${url}?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (res.ok) data = await res.json();
        } catch {
            // Shabake ghat'.
        }

        this.loading = false;

        if (!data || !Array.isArray(data.data) || data.data.length === 0) {
            this.empty = true;
            this.degraded = !!data?.degraded;
            return [];
        }

        this.degraded = !!data.degraded;
        this.resolvedProvider = data.provider || '';

        const divisor = data.displayDivisor || 1;

        return data.data.map((c) => ({
            timestamp: c.timestamp,
            open: c.open / divisor,
            high: c.high / divisor,
            low: c.low / divisor,
            close: c.close / divisor,
            volume: c.volume || 0,
        }));
    },

    readQuery() {
        try {
            const q = new URLSearchParams(window.location.search);
            if (q.get('provider')) this.provider = q.get('provider');
            if (q.get('period') && PERIODS[q.get('period')]) this.period = q.get('period');
        } catch {
            // URL e kharab — default ha mimunan.
        }
    },

    writeQuery() {
        try {
            const q = new URLSearchParams({ symbol: this.symbol, period: this.period });
            if (this.provider) q.set('provider', this.provider);
            window.history.replaceState(null, '', `${window.location.pathname}?${q}`);
        } catch {
            // Bi-ahamiat — faghat share e link.
        }
    },

    /** Rang ha az CSS variable ha — hich hex e hardcode (fallback faghat). */
    chartStyles() {
        const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        const up = css('--color-up') || '#1c8654';
        const down = css('--color-down') || '#c02e38';
        const brand = css('--color-brand-700') || '#176039';
        // ⚠️ `--color-brand-soft` khodesh `var(...)`-e va `getPropertyValue`
        // resolve-esh nemikone (rang e na-motabar -> khakestari)؛ `transparent`
        // ham tu gradient e canvas be SIAH e shafaf mire. Pas rgba az hex e brand.
        const tint = (alpha) => {
            const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(brand);
            return m ? `rgba(${parseInt(m[1], 16)}, ${parseInt(m[2], 16)}, ${parseInt(m[3], 16)}, ${alpha})` : brand;
        };
        const grid = css('--color-line') || '#e6e7e3';
        const text = css('--color-muted') || '#6b6b6b';

        return {
            grid: { horizontal: { color: grid }, vertical: { color: grid } },
            candle: {
                type: 'candle_solid',
                bar: {
                    upColor: up, downColor: down, noChangeColor: text,
                    upBorderColor: up, downBorderColor: down, noChangeBorderColor: text,
                    upWickColor: up, downWickColor: down, noChangeWickColor: text,
                },
                // Khat (area) ba rang e BRAND — na up/down: khat jahat e taghir
                // nadare va sabz e so'ud inja gomrah-konande-st.
                area: {
                    lineColor: brand,
                    lineSize: 2,
                    value: 'close',
                    // KLineCharts: offset 0 = PAEEN e area — porrang nazdik e khat (bala).
                    backgroundColor: [{ offset: 0, color: tint(0) }, { offset: 1, color: tint(0.22) }],
                    point: { color: brand, rippleColor: tint(0.25) },
                },
            },
            xAxis: { tickText: { color: text } },
            yAxis: { tickText: { color: text } },
        };
    },
});
