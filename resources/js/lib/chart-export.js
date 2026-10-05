/**
 * Export-e chart-e herfei (PLAN M17): screenshot-e PNG/JPG ba watermark, va fullscreen (Fullscreen API).
 *
 * - `downloadChartImage`: canvas-e khod-e lightweight-charts (`chart.takeScreenshot()`, hamun chizi
 *   ke rooye safhe keshide shode) rooye yek canvas-e movaghat kopi mishe va yek navar-e watermark
 *   (esm-e site + esm-e instrument + tarikh/sa'at-e shamsi) zir-esh ezafe mishe. Hich adad-e jadid-i
 *   inja neveshte nemishe — gheymat-e akhar hamun-i-e ke khod-e chart (legend/candle) ghablan keshide;
 *   in tabe' faghat pixel-haye canvas-e movjood ro migire va ye navar-e text-i be Farsi ezafe mikone.
 * - `enterFullscreen`/`exitFullscreen`: Fullscreen API rooye container-e chart (RTL, toolbar-e
 *   dakhelesh hamchenan didani mimune chon hamun DOM-e amadi-e); Escape khodesh az API miad, faghat
 *   listener-e taghir-e vaziat ro vasl mikonim ta UI (dokme-ye khorooj) sync bemune.
 */
const TEHRAN = 'Asia/Tehran';
const stamp = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { timeZone: TEHRAN, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });

/**
 * @param {{ takeScreenshot: () => HTMLCanvasElement }} chart  handle-e `priceChart()`
 * @param {{ siteName?: string, instrumentName?: string, code?: string, format?: 'png'|'jpg' }} options
 * @returns {boolean} false vaghti canvas-e 2d dar dastres nabashe (mohit-e ghadimi)
 */
export function downloadChartImage(chart, { siteName = '', instrumentName = '', code = '', format = 'png' } = {}) {
    const source = chart.takeScreenshot();
    const bar = Math.max(44, Math.round(source.height * 0.09));

    const canvas = document.createElement('canvas');
    canvas.width = source.width;
    canvas.height = source.height + bar;

    const ctx = canvas.getContext('2d');

    if (!ctx) return false;

    const isJpg = format === 'jpg';

    if (isJpg) {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    ctx.drawImage(source, 0, 0);

    ctx.fillStyle = '#1d1d1f';
    ctx.fillRect(0, source.height, canvas.width, bar);

    const title = [siteName, instrumentName].filter(Boolean).join(' — ');
    const fontSize = Math.max(12, Math.round(bar * 0.34));

    ctx.direction = 'rtl';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ededf0';
    ctx.font = `${fontSize}px Vazirmatn, sans-serif`;
    ctx.textAlign = 'right';
    ctx.fillText(title, canvas.width - 16, source.height + bar / 2);

    ctx.fillStyle = '#a7a7ad';
    ctx.font = `${Math.max(11, fontSize - 2)}px Vazirmatn, sans-serif`;
    ctx.textAlign = 'left';
    ctx.fillText(stamp.format(new Date()), 16, source.height + bar / 2);

    const filename = `${code || 'chart'}-${stamp.format(new Date()).replace(/[\s/:]/g, '-')}.${isJpg ? 'jpg' : 'png'}`;
    const mime = isJpg ? 'image/jpeg' : 'image/png';

    const link = document.createElement('a');
    link.href = canvas.toDataURL(mime, 0.92);
    link.download = filename;
    link.click();

    return true;
}

/** Fullscreen-e RTL rooye container-e chart; `document.exitFullscreen`/Escape khodesh kar mikone. */
export async function enterFullscreen(element) {
    if (document.fullscreenElement === element) return true;

    try {
        await element.requestFullscreen?.({ navigationUI: 'hide' });

        return true;
    } catch {
        return false;
    }
}

export async function exitFullscreen() {
    if (!document.fullscreenElement) return;

    try {
        await document.exitFullscreen?.();
    } catch {
        // Morurgar-i ke fullscreen-esh ghati'an bala nayumade — safe be nadide gereftan.
    }
}

export function isFullscreen(element) {
    return document.fullscreenElement === element;
}

/** `onChange(isFullscreen)` — Escape ham az hamin miad, chon khodesh `fullscreenchange` mizane. */
export function onFullscreenChange(element, onChange) {
    const handler = () => onChange(document.fullscreenElement === element);

    document.addEventListener('fullscreenchange', handler);

    return () => document.removeEventListener('fullscreenchange', handler);
}
