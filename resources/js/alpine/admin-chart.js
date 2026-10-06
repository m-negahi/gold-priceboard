/**
 * Chart e panel e admin (P5-08) — KLineCharts 10، candle ya khati.
 *
 * Data az `/dashboard/charts/data` (posht e auth). Baze haye haftegi/mahane/
 * salane SERVER-side ba taghvim e Jalali tajmi' mishan — inja faghat
 * namayesh. Taghir e symbol/provider/baze bedun e reload، va URL ba
 * `replaceState` be-rooz mishe ta link e chart ghabel e share bashe.
 *
 * ── Manba' ha ru chart (2026-10-06) ─────────────────────────────────
 * Alave bar candle e manba' e asli، gheymat e CLOSE e har provider e
 * tik-khorde be sorat e khat ru hamun pane mishine (yek indicator e custom
 * ba `extendData = { providerKey: { timestamp: close } }`). Bardashtan e
 * tik = hazf e khat (extendData e un provider khali mishe va KLineCharts
 * meghdar e `undefined` ro nemikeshe). Series e har provider yek bar fetch
 * va cache mishe؛ ba taghir e symbol/baze cache khali mishe.
 */

const OVERLAY_INDICATOR = 'pbProviderLines';

/** Rang e khat ha az token haye CSS — tartib sabet ta har provider hamishe yek rang dashte bashe. */
// ⚠️ Brand inja NIST: khat e asli (halat e khati) brand ast va nabayad ba yek provider ham-rang she.
const PALETTE_TOKENS = ['--color-info', '--color-metal-gold', '--color-metal-copper', '--color-warn', '--color-metal-silver', '--color-down', '--color-up', '--color-ink'];
const PALETTE_FALLBACK = ['#1551c4', '#c8a031', '#b4683a', '#8a5a00', '#8e95a0', '#c02e38', '#1c8654', '#1f1f1f'];

/** Tarikh e Jalali ba raghame Latin baraye tooltip (vaght e Tehran). */
const jalaliTime = (ts, withTime) => {
    try {
        return new Intl.DateTimeFormat('fa-IR-u-nu-latn-ca-persian', {
            timeZone: 'Asia/Tehran',
            year: 'numeric', month: '2-digit', day: '2-digit',
            ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
        }).format(new Date(ts));
    } catch {
        return new Date(ts).toISOString().slice(0, 16).replace('T', ' ');
    }
};

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

export default ({ url, symbol, period, precisions, overlayProviders = {} }) => ({
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
    /** Provider haye ghabel e namayesh ru chart (key => esm). */
    overlayProviders,
    /** Provider haye tik-khorde — default: hame. */
    shown: Object.keys(overlayProviders),
    /**
     * Cache e series e provider ha: `${symbol}:${period}:${provider}` -> { timestamp: close }.
     *
     * 🔴 Kelid bayad symbol/baze ro ham dashte bashe: ghablan faghat provider
     * bud va fetch e dar-rah e symbol e GHABLI ba'd az avaz shodan e symbol
     * tu cache minevesht — khat e 18 ayar ru chart e sekke (ekhtelaf e -90٪).
     */
    overlayCache: {},
    overlayIndicatorId: null,
    /** Akharin candle e manba' e asli — marja' e «ekhtelaf». */
    lastBar: null,
    /** Ekhtelaf e har provider e tik-khorde ba manba' e asli ru akharin candle (baraye chip ha). */
    diffs: [],

    async init() {
        this.readQuery();
        this.klinecharts = await import('klinecharts');
        await this.$nextTick();

        this.registerOverlayIndicator();

        this.chart = this.klinecharts.init(this.$refs.canvas, { styles: this.chartStyles() });
        this.chart.setDataLoader({
            getBars: async ({ callback }) => {
                callback(await this.fetchBars(), false);
                // Khat haye provider ha BA'D az candle ha — hamun timestamp ha.
                this.refreshOverlays();
            },
        });
        this.applySymbol();
        this.chart.setPeriod(PERIODS[this.period]);
        if (this.kind === 'line') this.chart.setStyles({ candle: { type: 'area' } });
    },

    /** Daghighat e namayesh e symbol e fe'li (rial 0، dollar 2). */
    get precision() {
        return precisions[this.symbol] ?? 0;
    },

    /** Adad ba jodakonande ye hezargan va daghighat e symbol (Latin). */
    fmt(value) {
        if (value === null || value === undefined || Number.isNaN(value)) return '—';
        return Number(value).toLocaleString('en-US', { minimumFractionDigits: this.precision, maximumFractionDigits: this.precision });
    },

    /** Darsad e ekhtelaf e `value` nesbat be `base` ba alamat — '—' agar yeki nabashe. */
    diffPct(value, base) {
        if (!value || !base) return '—';
        const pct = ((value - base) / base) * 100;
        return `${pct > 0 ? '+' : ''}${pct.toFixed(2)}٪`;
    },

    /** Provider hayi ke alan khat daran (tik-khorde va gheyr az manba' e asli e candle). */
    get activeOverlays() {
        return this.shown.filter((key) => key !== this.resolvedProvider && key in this.overlayProviders);
    },

    /** Kelid e cache baraye symbol/baze ye FE'LI. */
    cacheKey(key) {
        return `${this.symbol}:${this.period}:${key}`;
    },

    /** Aya in provider baraye symbol/baze ye fe'li data dare? (null = hanuz nayamade) */
    hasData(key) {
        const series = this.overlayCache[this.cacheKey(key)];
        if (series === undefined) return null;
        return Object.keys(series).length > 0;
    },

    colorFor(key) {
        const index = Object.keys(this.overlayProviders).indexOf(key);
        if (index < 0) return PALETTE_FALLBACK[0];
        const token = PALETTE_TOKENS[index % PALETTE_TOKENS.length];
        const css = getComputedStyle(document.documentElement).getPropertyValue(token).trim();
        return /^#[0-9a-f]{6}$/i.test(css) ? css : PALETTE_FALLBACK[index % PALETTE_FALLBACK.length];
    },

    /**
     * Yek indicator e custom: har figure yek provider. `calc` baraye har
     * candle meghdar e close e un provider ro az extendData bar midare؛
     * `undefined` = khat unja ghat' mishe (provider data nadashte).
     */
    registerOverlayIndicator() {
        const keys = Object.keys(this.overlayProviders);
        if (keys.length === 0) return;

        this.klinecharts.registerIndicator({
            name: OVERLAY_INDICATOR,
            shortName: 'منابع',
            series: 'price',
            zLevel: 1,
            // Har taghir e extendData = mohasebe + rasm e dobare.
            shouldUpdate: () => ({ calc: true, draw: true }),
            figures: keys.map((key) => ({ key: `p_${key}`, title: `${this.overlayProviders[key]}: `, type: 'line' })),
            styles: { lines: keys.map((key) => ({ color: this.colorFor(key), size: 1.5, style: 'solid', smooth: false, dashedValue: [2, 2] })) },
            calc: (dataList, indicator) => {
                const series = indicator.extendData ?? {};
                return dataList.map((bar) => {
                    const row = {};
                    for (const key of keys) row[`p_${key}`] = series[key]?.[bar.timestamp];
                    return row;
                });
            },
            // Tooltip: faghat provider haye tik-khorde، ba rang e khodeshun، gheymat +
            // ekhtelaf e darsadi ba candle e manba' e asli ru hamun candle.
            createTooltipDataSource: ({ indicator, crosshair }) => {
                const index = crosshair?.dataIndex ?? -1;
                const row = index >= 0 ? indicator.result?.[index] ?? {} : {};
                const base = crosshair?.kLineData?.close ?? null;
                const legends = [];

                for (const key of Object.keys(indicator.extendData ?? {})) {
                    const value = row[`p_${key}`];
                    // Provider-i ke in symbol ro nadare tu tooltip ja nagire.
                    if (value === undefined && Object.keys(indicator.extendData[key] ?? {}).length === 0) continue;
                    legends.push({
                        title: { text: `${this.overlayProviders[key] ?? key}: `, color: this.colorFor(key) },
                        value: { text: value === undefined ? '—' : `${this.fmt(value)} (${this.diffPct(value, base)})`, color: this.colorFor(key) },
                    });
                }

                return { name: '', calcParamsText: '', features: [], legends };
            },
        });
    },

    /** Series e provider haye tik-khorde ro (ba cache) migire va indicator ro be-rooz mikone. */
    async refreshOverlays() {
        if (!this.chart || Object.keys(this.overlayProviders).length === 0) return;

        const scope = `${this.symbol}:${this.period}`;
        const wanted = this.activeOverlays;
        await Promise.all(wanted.filter((key) => !(this.cacheKey(key) in this.overlayCache)).map((key) => this.fetchOverlay(key)));

        // Vasat e fetch symbol/baze avaz shode — in natije mal e chart e fe'li nist.
        if (scope !== `${this.symbol}:${this.period}`) return;

        const extendData = {};
        for (const key of wanted) extendData[key] = this.overlayCache[this.cacheKey(key)] ?? {};

        const spec = { name: OVERLAY_INDICATOR, paneId: 'candle_pane', extendData, precision: this.precision };

        // 🔴 `overrideIndicator` ba extendData e jadid khat e ghabli ro az canvas
        // bar NEMIDARE (calc dobare ejra nemishe؛ ba pixel e canvas test shod).
        // Pas har bar hazf + sakht e dobare — arzan ast، yek indicator e sabok.
        if (this.overlayIndicatorId !== null) {
            this.chart.removeIndicator({ id: this.overlayIndicatorId });
            this.overlayIndicatorId = null;
        }

        if (wanted.length > 0) {
            this.overlayIndicatorId = this.chart.createIndicator(spec, true);
        }

        this.computeDiffs(wanted);
    },

    /** Chip haye «ekhtelaf»: akharin gheymat e har provider nesbat be akharin candle e manba' e asli. */
    computeDiffs(keys) {
        const base = this.lastBar?.close ?? null;
        const ts = this.lastBar?.timestamp ?? null;

        this.diffs = keys.map((key) => {
            const series = this.overlayCache[this.cacheKey(key)] ?? {};
            // Hamun candle؛ agar nabud، akharin candle e khod e provider.
            const stamps = Object.keys(series);
            const value = ts !== null && series[ts] !== undefined ? series[ts] : (stamps.length ? series[stamps[stamps.length - 1]] : null);

            return {
                key,
                name: this.overlayProviders[key] ?? key,
                color: this.colorFor(key),
                value: this.fmt(value),
                diff: this.diffPct(value, base),
                tone: !value || !base ? 'flat' : (value > base ? 'up' : (value < base ? 'down' : 'flat')),
            };
        });
    },

    async fetchOverlay(key) {
        const symbol = this.symbol;
        const period = this.period;
        const cacheKey = `${symbol}:${period}:${key}`;

        try {
            const params = new URLSearchParams({ symbol, period, provider: key });
            const res = await fetch(`${url}?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = res.ok ? await res.json() : null;
            const map = {};

            // Server provider e kharej az zanjire ro be avvalin provider bar
            // migardune — un data mal e in khat nist. Symbol/baze ham bayad
            // hamun bashe ke porsidim.
            if (data && data.provider === key && data.symbol === symbol && data.period === period && Array.isArray(data.data)) {
                const divisor = data.displayDivisor || 1;
                for (const c of data.data) map[c.timestamp] = c.close / divisor;
            }

            this.overlayCache[cacheKey] = map;
        } catch {
            this.overlayCache[cacheKey] = {};
        }
    },

    clearOverlayCache() {
        // Kelid ha symbol/baze daran، pas pak kardan faghat baraye hafeze-ast؛
        // fetch e dar-rah dige nemitune cache e chart e jadid ro aludeh kone.
        this.overlayCache = {};
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
        this.clearOverlayCache();
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
        this.clearOverlayCache();
        this.chart.setPeriod(PERIODS[next]);
    },

    setKind(next) {
        this.kind = next;
        this.writeQuery();
        this.chart.setStyles({ candle: { type: next === 'line' ? 'area' : 'candle_solid' } });
    },

    /**
     * Legend e tooltip e candle: tu halat e candle OHLC + taghir؛ tu halat e
     * khati faghat zaman + gheymat (OHLC e yek khat gomrah-konande-st).
     */
    candleLegends({ current }) {
        if (!current) return [];

        const withTime = ['m5', 'm15', 'h1', 'h4'].includes(this.period);
        const time = { title: 'زمان: ', value: jalaliTime(current.timestamp, withTime) };

        if (this.kind === 'line') {
            return [time, { title: 'قیمت: ', value: this.fmt(current.close) }];
        }

        const change = current.open ? this.diffPct(current.close, current.open) : '—';

        return [
            time,
            { title: 'باز: ', value: this.fmt(current.open) },
            { title: 'بیشینه: ', value: this.fmt(current.high) },
            { title: 'کمینه: ', value: this.fmt(current.low) },
            { title: 'بسته: ', value: this.fmt(current.close) },
            { title: 'تغییر: ', value: change },
        ];
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

        const bars = data.data.map((c) => ({
            timestamp: c.timestamp,
            open: c.open / divisor,
            high: c.high / divisor,
            low: c.low / divisor,
            close: c.close / divisor,
            volume: c.volume || 0,
        }));

        this.lastBar = bars[bars.length - 1] ?? null;

        return bars;
    },

    readQuery() {
        try {
            const q = new URLSearchParams(window.location.search);
            if (q.get('provider')) this.provider = q.get('provider');
            if (q.get('period') && PERIODS[q.get('period')]) this.period = q.get('period');
            if (q.get('kind') === 'line') this.kind = 'line';
        } catch {
            // URL e kharab — default ha mimunan.
        }
    },

    writeQuery() {
        try {
            const q = new URLSearchParams({ symbol: this.symbol, period: this.period });
            if (this.provider) q.set('provider', this.provider);
            if (this.kind === 'line') q.set('kind', 'line');
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
                // Tooltip hamishe (akharin candle vaghti mouse ru chart nist)؛
                // legend e sefareshi: Jalali + adad e format-shode، na OHLC e kham.
                tooltip: {
                    showRule: 'always',
                    showType: 'standard',
                    legend: { template: (data) => this.candleLegends(data) },
                },
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
            indicator: { tooltip: { showRule: 'always', showType: 'standard', showName: false, showParams: false } },
            crosshair: { horizontal: { text: { color: '#fff' } } },
            xAxis: { tickText: { color: text } },
            yAxis: { tickText: { color: text } },
        };
    },
});
