/**
 * Chart e INLINE e safhe ye instrument (`/price/{symbol}`، P10-13) — hamun
 * KLineCharts 10 e `price-chart.js` vali bedun e modal: ba load e safhe
 * baz mishe، interval ba dokme ha avaz mishe. Data az `/candles.json`
 * (OMUMI، spread e'mal shode، toman ba `displayDivisor`).
 */

const PERIODS = {
    m1: { span: 1, type: 'minute' },
    m5: { span: 5, type: 'minute' },
    m15: { span: 15, type: 'minute' },
    h1: { span: 1, type: 'hour' },
    h4: { span: 4, type: 'hour' },
    d1: { span: 1, type: 'day' },
};

export default ({ url, symbol, interval = 'm15', precision = 0 }) => ({
    symbol,
    interval,
    precision,
    provider: '',
    loading: false,
    empty: false,
    degraded: false,
    chart: null,
    klinecharts: null,

    async init() {
        this.klinecharts = await import('klinecharts');
        await this.$nextTick();

        this.chart = this.klinecharts.init(this.$refs.canvas, { styles: this.chartStyles() });
        this.chart.setDataLoader({
            getBars: async ({ callback }) => {
                callback(await this.fetchBars(), false);
            },
        });
        this.chart.setSymbol({ ticker: this.symbol, pricePrecision: this.precision, volumePrecision: 0 });
        this.chart.setPeriod(PERIODS[this.interval] ?? PERIODS.m15);
    },

    destroy() {
        if (this.chart && this.klinecharts) this.klinecharts.dispose(this.$refs.canvas);
    },

    setInterval(next) {
        if (next === this.interval || !this.chart || !PERIODS[next]) return;

        this.interval = next;
        this.chart.setPeriod(PERIODS[next]);
    },

    async fetchBars() {
        this.loading = true;
        this.empty = false;

        let data = null;

        try {
            const params = new URLSearchParams({ symbol: this.symbol, interval: this.interval });
            const res = await fetch(`${url}?${params}`, { credentials: 'same-origin' });
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
        this.provider = data.provider || '';

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

    /** Rang ha az token haye CSS — hich hex e hardcode (fallback faghat). */
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
                tooltip: { showRule: 'always', showType: 'standard' },
            },
            xAxis: { tickText: { color: text } },
            yAxis: { tickText: { color: text } },
        };
    },
});
