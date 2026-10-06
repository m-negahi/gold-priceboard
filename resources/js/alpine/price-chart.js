/**
 * Chart e candlestick (P5-04) — KLineCharts 10، self-host (TradingView ru
 * host block e). Lazy-import: ketabkhane faghat vaghti chart baz she load
 * mishe (board e asli sabok mimune).
 *
 * ── API e v10 ───────────────────────────────────────────────────────
 * v10 olgu ye «data-loader» dare (na `applyNewData` e v9): `setDataLoader`
 * yek `getBars` migire ke vaghti symbol/period avaz she seda mishe va data
 * ro ba `callback(list, false)` bar migardune.
 *
 * Data az `/candles.json` miad (OMUMI، bedun e auth) — spread hamun-ja
 * e'mal shode، pas close e akharin candle = gheymat e tablo.
 */

// interval e ma -> Period e KLineCharts.
const PERIODS = {
    m1: { span: 1, type: 'minute' },
    m5: { span: 5, type: 'minute' },
    m15: { span: 15, type: 'minute' },
    h1: { span: 1, type: 'hour' },
    h4: { span: 4, type: 'hour' },
    d1: { span: 1, type: 'day' },
};

export default ({ url, interval = 'm5' }) => ({
    open: false,
    loading: false,
    empty: false,
    degraded: false,
    symbol: null,
    label: '',
    provider: '',
    interval,
    precision: 0,
    chart: null,
    klinecharts: null,

    /** `detail`: { symbol, label, provider? } — az dokme ye har radif. */
    async show(detail) {
        this.symbol = detail.symbol;
        this.label = detail.label;
        this.provider = detail.provider || '';
        this.open = true;

        if (!this.klinecharts) {
            this.klinecharts = await import('klinecharts');
        }

        await this.$nextTick();
        this.render();

        // Symbol + period set mishan -> getBars khodesh fetch mikone.
        this.chart.setSymbol({ ticker: this.symbol, pricePrecision: 0, volumePrecision: 0 });
        this.chart.setPeriod(PERIODS[this.interval]);
    },

    close() {
        this.open = false;

        if (this.chart) {
            this.klinecharts.dispose(this.$refs.canvas);
            this.chart = null;
        }
    },

    render() {
        if (this.chart) return;

        this.chart = this.klinecharts.init(this.$refs.canvas, { styles: this.chartStyles() });

        this.chart.setDataLoader({
            getBars: async ({ callback }) => {
                const bars = await this.fetchBars();
                callback(bars, false); // false = data ye bishtari nist.
            },
        });
    },

    async setInterval(next) {
        if (next === this.interval || !this.chart) return;

        this.interval = next;
        // Taghir e period getBars ro dobare seda mizane.
        this.chart.setPeriod(PERIODS[next]);
    },

    /** @returns {Promise<Array>} KLineData[] */
    async fetchBars() {
        this.loading = true;
        this.empty = false;

        let data = null;

        try {
            const params = new URLSearchParams({ symbol: this.symbol, interval: this.interval });
            if (this.provider) params.set('provider', this.provider);

            const res = await fetch(`${url}?${params}`, { credentials: 'same-origin' });

            if (res.ok) data = await res.json();
        } catch {
            // Shabake ghat'.
        }

        this.loading = false;

        if (!data || !Array.isArray(data.data) || data.data.length === 0) {
            this.empty = true;
            return [];
        }

        this.degraded = !!data.degraded;
        this.provider = data.provider || this.provider;
        this.precision = data.precision ?? 0;

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

    /** Rang ha az CSS variable ha — hich hex e hardcode. */
    chartStyles() {
        const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        const up = css('--color-up') || '#1c8654';
        const down = css('--color-down') || '#c02e38';
        const grid = css('--color-line') || '#e6e7e3';
        const text = css('--color-muted') || '#6b6b6b';

        return {
            grid: { horizontal: { color: grid }, vertical: { color: grid } },
            candle: {
                bar: {
                    upColor: up, downColor: down, noChangeColor: text,
                    upBorderColor: up, downBorderColor: down, noChangeBorderColor: text,
                    upWickColor: up, downWickColor: down, noChangeWickColor: text,
                },
            },
            xAxis: { tickText: { color: text } },
            yAxis: { tickText: { color: text } },
        };
    },
});
