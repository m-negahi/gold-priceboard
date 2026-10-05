/**
 * Wrapper-e lightweight-charts (TradingView, MIT, bundle-e npm — hich origin-e sevvom) baraye
 * chart-haye gozaresh-e manabe'.
 *
 * - Rang ha az token-haye CSS (`--color-*`) dar lahze-ye sakht khunde mishan; hich hex-i inja nist.
 * - Zaman ha epoch-ms-e UTC miyan va ba offset-e Tehran shift mishan ta mehvar-e zamani-ye chart
 *   (ke faghat UTC mifahme) sa'at-e Tehran ro neshun bede; label ha ba Intl rooye zaman-e vaghei
 *   (bedoone shift) ba `timeZone: 'Asia/Tehran'` va taghvim-e shamsi.
 * - Font hamun font-e body-e (Vazirmatn) — canvas `inherit` nemifahme, pas az computed style miad;
 *   andaze-ash az token-haye `--bp-text-*` (lightweight-charts faghat adad-e px migire).
 * - Gap ha (ghat'i) ba yek area-e overlay-e tamam-ghad (0/1 rooye scale-e penhan) saye mikhoran; marker ha ba
 *   createSeriesMarkers rooye khat-e gheymat-e nahayi ya khat-e har source mishinan.
 */
import { AreaSeries, CandlestickSeries, ColorType, CrosshairMode, LineSeries, LineStyle, createChart, createSeriesMarkers } from 'lightweight-charts';

/** Rang-e sabet-e har source dar kol-e safhe (index → token). */
export const SOURCE_TOKENS = ['brand', 'gold', 'info', 'copper', 'silver', 'coin', 'brand-3', 'ok'];

export const token = (name) => `var(--color-${name})`;
export const resolved = (name) => getComputedStyle(document.documentElement).getPropertyValue(`--color-${name}`).trim();

/** Iran az 1401 DST nadare: offset-e Tehran sabet +03:30 = 12600 sanie. */
const TEHRAN_OFFSET_S = 12_600;

const TEHRAN = 'Asia/Tehran';
const number = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 0 });
const clock = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, hour: '2-digit', minute: '2-digit' });
const clockSeconds = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, hour: '2-digit', minute: '2-digit', second: '2-digit' });
const day = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, month: '2-digit', day: '2-digit' });
const monthName = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, month: 'long' });
const full = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });

/** epoch-ms (UTC) → sanie-ye shift-shode be Tehran, un chizi ke be chart midim. */
export const toChartTime = (ms) => Math.floor(ms / 1000) + TEHRAN_OFFSET_S;
export const fromChartTime = (time) => (time - TEHRAN_OFFSET_S) * 1000;

/**
 * Andaze-ye yek token-e matn be px: `--bp-text-*` yek `clamp()`-e, pas `getPropertyValue` khod-e
 * ebarat ro mide na adad; yek element-e movaghat ba un font-size andaze migire. Yek bar be azaye
 * har token (cache) — chart ha ziad sakhte mishan.
 *
 * @type {Map<string, number>}
 */
const tokenPx = new Map();

export function textToken(name) {
    if (tokenPx.has(name)) return tokenPx.get(name);

    const probe = document.createElement('span');
    probe.style.fontSize = `var(--bp-text-${name})`;
    probe.style.position = 'absolute';
    probe.style.visibility = 'hidden';
    document.body.append(probe);
    const px = Number.parseFloat(getComputedStyle(probe).fontSize) || 0;
    probe.remove();

    tokenPx.set(name, px);

    return px;
}

/** Yek hex-e 6 raghami + alpha (00–ff); har chiz-e dige dast-nakhorde bar migarde. */
export const alpha = (color, hex2) => (/^#[0-9a-f]{6}$/i.test(color) ? `${color}${hex2}` : color);

/**
 * Label-e tick-haye mehvar-e zaman: rooz vaghti tick-e rooz-e, vagarna sa'at (ba sanie baraye
 * bucket-haye kutah — jadval-e range-be-bucket: D1/W1 < 60s → ba sanie, M1/M3/Y1/All ≥ 60s → bedoone
 * sanie). Export-e amdi (bedoone DOM, faghat Intl) baraye tests/js/chart-bucket.test.js.
 */
export function tickMarkFormatter(bucketSeconds) {
    return (time, type) => {
        // Zaman-e chart shift-shode-ast; Intl zaman-e vaghei ro mikhad va khodesh be Tehran mibare.
        const date = new Date(fromChartTime(time));

        // TickMarkType: 0 sal, 1 mah, 2 rooz, 3 zaman, 4 zaman ba sanie.
        if (type === 0 || type === 1) return monthName.format(date);
        if (type === 2) return day.format(date);

        return (bucketSeconds < 60 ? clockSeconds : clock).format(date);
    };
}

export const formatChartTime = (time) => full.format(new Date(fromChartTime(time)));

function baseOptions({ height, compact, bucketSeconds, priceFormatter }) {
    const line = resolved('line');
    const fontFamily = getComputedStyle(document.body).fontFamily;

    return {
        autoSize: true,
        height,
        layout: {
            background: { type: ColorType.Solid, color: resolved('surface') },
            textColor: resolved('muted'),
            fontFamily,
            // Kuchik-tarin token-e matn; mini chart-e grid mehvar nadare, pas hamun kafi-e.
            fontSize: textToken('xs'),
            attributionLogo: false,
        },
        grid: {
            vertLines: { color: line, visible: !compact },
            horzLines: { color: line, visible: !compact },
        },
        rightPriceScale: { borderColor: line, visible: !compact, scaleMargins: { top: 0.12, bottom: 0.12 } },
        timeScale: {
            borderColor: line,
            visible: !compact,
            timeVisible: true,
            secondsVisible: bucketSeconds < 60,
            tickMarkFormatter: tickMarkFormatter(bucketSeconds),
            rightOffset: 2,
            fixLeftEdge: true,
            fixRightEdge: true,
        },
        crosshair: {
            mode: CrosshairMode.Normal,
            vertLine: { color: resolved('muted-2'), labelBackgroundColor: resolved('ink'), visible: !compact, labelVisible: !compact },
            horzLine: { color: resolved('muted-2'), labelBackgroundColor: resolved('ink'), visible: !compact, labelVisible: !compact },
        },
        localization: {
            locale: 'fa-IR',
            timeFormatter: formatChartTime,
            priceFormatter: priceFormatter ?? ((value) => number.format(Math.round(value))),
        },
        handleScroll: !compact,
        handleScale: !compact,
    };
}

/**
 * Yek chart-e khatti ba yek khat-e asli (`final`) va n khat-e source, marker ha va saye-ye gap ha.
 * `compact` = mini chart-e grid: bedoone mehvar, bedoone crosshair, ghabel-e scroll nist.
 */
export function lineChart(element, { height = 320, compact = false, bucketSeconds = 300, priceFormatter = null, onCrosshair = null } = {}) {
    const chart = createChart(element, baseOptions({ height, compact, bucketSeconds, priceFormatter }));
    const series = new Map();
    const markers = new Map();
    let gaps = null;
    let bucket = bucketSeconds;

    const toData = (points) => points.map(([ms, value]) => ({ time: toChartTime(ms), value }));

    const ensure = (key, options) => {
        if (series.has(key)) return series.get(key);

        const line = chart.addSeries(LineSeries, {
            priceLineVisible: false,
            lastValueVisible: !compact && key === 'final',
            crosshairMarkerVisible: !compact,
            lineWidth: key === 'final' ? 2 : 1,
            ...options,
        });
        series.set(key, line);

        return line;
    };

    if (onCrosshair && !compact) {
        chart.subscribeCrosshairMove((param) => {
            if (!param.time) {
                onCrosshair(null);

                return;
            }

            const values = {};

            for (const [key, line] of series) {
                const point = param.seriesData.get(line);

                if (point && point.value !== undefined) values[key] = point.value;
            }

            onCrosshair({ time: fromChartTime(param.time), values });
        });
    }

    return {
        chart,

        /** Khat-e asli (gheymat-e nahayi) — rang-e brand. */
        setFinal(points, color = resolved('brand')) {
            ensure('final', { color }).setData(toData(points));
        },

        setSource(key, points, color, visible = true) {
            const line = ensure(key, { color, lineStyle: LineStyle.Solid });
            line.setData(toData(points));
            line.applyOptions({ visible });
        },

        removeSource(key) {
            const line = series.get(key);

            if (!line) return;

            markers.get(key)?.setMarkers([]);
            markers.delete(key);
            chart.removeSeries(line);
            series.delete(key);
        },

        /** Source-hayi ke dige tu dade nistand pak mishan (instrument avaz shode). */
        keepOnly(keys) {
            for (const key of [...series.keys()]) {
                if (key !== 'final' && !keys.includes(key)) this.removeSource(key);
            }
        },

        setVisible(key, visible) {
            series.get(key)?.applyOptions({ visible });
        },

        has(key) {
            return series.has(key);
        },

        /** Noghte-ye jadid (tab-e zende): time bayad ≥ akharin time bashe. */
        update(key, [ms, value]) {
            series.get(key)?.update({ time: toChartTime(ms), value });
        },

        /**
         * Marker ha rooye yek khat: `{at, position, shape, color, text, size, price}` (at = epoch-ms).
         * Lightweight-charts marker ha ro be tartib-e zaman mikhad.
         */
        setMarkers(key, list) {
            const line = series.get(key);

            if (!line) return;

            const sorted = [...list]
                .sort((a, b) => a.at - b.at)
                .map(({ at, ...rest }) => ({ time: toChartTime(at), ...rest }));

            if (!markers.has(key)) markers.set(key, createSeriesMarkers(line, sorted, { autoScale: false }));
            else markers.get(key).setMarkers(sorted);
        },

        /**
         * Saye-ye gap ha (ghat'i): `{at, until}`; until null = ta akhar-e baze (`to`). Yek area-e overlay
         * rooye scale-e penhan ba margin-e sefr: 1 dakhel-e gap, 0 yek bucket ghabl va ba'd, pas
         * yek navar-e peyvaste-ye tamam-ghad mishe (histogram beyn-e bar ha fasele mindakht).
         */
        setGaps(list, to, color = alpha(resolved('danger'), '22')) {
            if (!gaps) {
                gaps = chart.addSeries(AreaSeries, {
                    priceScaleId: 'gaps',
                    priceLineVisible: false,
                    lastValueVisible: false,
                    crosshairMarkerVisible: false,
                    lineWidth: 1,
                    lineColor: color,
                    topColor: color,
                    bottomColor: color,
                });
                chart.priceScale('gaps').applyOptions({ scaleMargins: { top: 0, bottom: 0 }, visible: false });
            }

            const step = bucket * 1000;
            const values = new Map();
            const put = (ms, value) => values.set(toChartTime(ms), Math.max(values.get(toChartTime(ms)) ?? 0, value));

            for (const gap of list) {
                const end = gap.until ?? to;

                put(gap.at - step, 0);

                for (let ms = gap.at; ms < end; ms += step) put(ms, 1);

                put(end, 0);
            }

            gaps.setData([...values.entries()].sort((a, b) => a[0] - b[0]).map(([time, value]) => ({ time, value })));
        },

        setBucket(seconds) {
            bucket = seconds;
            chart.applyOptions({ timeScale: { secondsVisible: seconds < 60, tickMarkFormatter: tickMarkFormatter(seconds) } });
        },

        fit() {
            chart.timeScale().fitContent();
        },

        /** Ba'd az yek update-e zende: age karbar scroll nakarde, akharin noghte tu did bemune. */
        scrollToEnd() {
            chart.timeScale().scrollToRealTime();
        },

        dispose() {
            for (const plugin of markers.values()) plugin.detach?.();
            markers.clear();
            series.clear();
            gaps = null;
            chart.remove();
        },
    };
}

/**
 * Chart-e herfei-ye safhe-ye gheymat (PLAN M17, `resources/js/alpine/price-chart.js`): candle ya
 * khatti/area, savich-e halat bedoone az-no-sakhtan-e chart (`setMode`), crosshair-e ba OHLC
 * (`onCrosshair` legend-e biroon ro por mikone), `takeScreenshot()`-e native-e lightweight-charts
 * (`resources/js/lib/chart-export.js` rooy-esh watermark mizane), saye-ye tire (`setDark` — faghat
 * rang-haye khod-e chart, theme-e site hamishe roshan mimune).
 *
 * Vorudi hamishe candle-e kamel-e (`{time, open, high, low, close}`, epoch-ms); halat-e khatti faghat
 * `close`-e har candle ro khat mikeshe — yek endpoint, do namayesh.
 */
export function priceChart(element, { height = 360, mode = 'candle', bucketSeconds = 60, priceFormatter = null, onCrosshair = null } = {}) {
    const chart = createChart(element, baseOptions({ height, compact: false, bucketSeconds, priceFormatter }));

    let currentMode = mode === 'line' ? 'line' : 'candle';
    let bucket = bucketSeconds;
    let points = [];
    let candles = null;
    let line = null;

    const ensureCandles = () => (candles ??= chart.addSeries(CandlestickSeries, {
        upColor: resolved('up-strong'),
        downColor: resolved('down-strong'),
        borderUpColor: resolved('up'),
        borderDownColor: resolved('down'),
        wickUpColor: resolved('up'),
        wickDownColor: resolved('down'),
        priceLineVisible: false,
    }));

    const ensureLine = () => (line ??= chart.addSeries(AreaSeries, {
        lineColor: resolved('brand'),
        topColor: alpha(resolved('brand'), '26'),
        bottomColor: alpha(resolved('brand'), '00'),
        lineWidth: 2,
        priceLineVisible: false,
        lastValueVisible: true,
        crosshairMarkerVisible: true,
    }));

    function paint() {
        const data = points.map((p) => ({ time: toChartTime(p.time), open: p.open, high: p.high, low: p.low, close: p.close }));

        if (currentMode === 'candle') {
            if (line) {
                chart.removeSeries(line);
                line = null;
            }
            ensureCandles().setData(data);
        } else {
            if (candles) {
                chart.removeSeries(candles);
                candles = null;
            }
            ensureLine().setData(data.map((d) => ({ time: d.time, value: d.close })));
        }
    }

    if (onCrosshair) {
        chart.subscribeCrosshairMove((param) => {
            const series = candles ?? line;

            if (!param.time || !series) {
                onCrosshair(null);

                return;
            }

            const point = param.seriesData.get(series);

            if (!point) {
                onCrosshair(null);

                return;
            }

            const ohlc = 'open' in point
                ? { open: point.open, high: point.high, low: point.low, close: point.close }
                : { open: point.value, high: point.value, low: point.value, close: point.value };

            // chartTime khode-e param.time-e (shift-shode-ye Tehran) — mostaghim be formatChartTime bede.
            onCrosshair({ chartTime: param.time, ...ohlc });
        });
    }

    return {
        chart,

        /** @param {list<{time: number, open: number, high: number, low: number, close: number}>} buckets */
        setData(buckets) {
            points = buckets;
            paint();
        },

        setMode(next) {
            const wanted = next === 'line' ? 'line' : 'candle';

            if (wanted === currentMode) return;

            currentMode = wanted;
            paint();
        },

        getMode() {
            return currentMode;
        },

        setBucket(seconds) {
            bucket = seconds;
            chart.applyOptions({ timeScale: { secondsVisible: seconds < 60, tickMarkFormatter: tickMarkFormatter(seconds) } });
        },

        /** Saye-ye tire-ye faghat-khod-e chart — theme-e site hamishe roshan mimune (rules/frontend.md). */
        setDark(isDark) {
            element.dataset.theme = isDark ? 'dark' : 'light';
            chart.applyOptions({
                layout: {
                    background: { type: ColorType.Solid, color: resolved(isDark ? 'surface-dark' : 'surface') },
                    textColor: resolved(isDark ? 'muted-invert' : 'muted'),
                },
                grid: {
                    vertLines: { color: resolved(isDark ? 'line-invert' : 'line') },
                    horzLines: { color: resolved(isDark ? 'line-invert' : 'line') },
                },
                rightPriceScale: { borderColor: resolved(isDark ? 'line-invert' : 'line') },
                timeScale: { borderColor: resolved(isDark ? 'line-invert' : 'line') },
                crosshair: {
                    vertLine: { labelBackgroundColor: resolved(isDark ? 'surface-dark-2' : 'ink') },
                    horzLine: { labelBackgroundColor: resolved(isDark ? 'surface-dark-2' : 'ink') },
                },
            });
        },

        fit() {
            chart.timeScale().fitContent();
        },

        /** Akharin candle — legend-e avvalie (ghabl az avvalin crosshair-move) va export-e watermark. */
        lastPoint() {
            return points.length === 0 ? null : points[points.length - 1];
        },

        /** Canvas-e khod-e lightweight-charts, bedoone hich chizi-e ezafi (`chart-export.js` watermark mizane rooش). */
        takeScreenshot() {
            return chart.takeScreenshot();
        },

        dispose() {
            chart.remove();
        },
    };
}

/** Marker-haye estandard-e rooydad ha (yek ja, ta chart-e asli, grid va zende yek zaban dashte bashan). */
export function eventMarker(event) {
    const shapes = {
        failover: { position: 'aboveBar', shape: 'arrowDown', color: resolved('stale-strong'), size: 1.2 },
        recovery: { position: 'belowBar', shape: 'arrowUp', color: resolved('ok'), size: 1 },
        deviation: { position: 'aboveBar', shape: 'circle', color: resolved('warn'), size: 0.9 },
        error: { position: 'belowBar', shape: 'circle', color: resolved('danger'), size: 0.8 },
        outage: { position: 'belowBar', shape: 'square', color: resolved('danger'), size: 0.8 },
    };

    return { at: event.at, ...(shapes[event.type] ?? shapes.error) };
}
