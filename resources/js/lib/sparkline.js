/**
 * Rasm-e sparkline-e inline-SVG az yek list-e adad (M16, sotoon-e «روند»). Khalis va bedoone DOM —
 * faghat yek `d`-e SVG path misaze; caller (`alpine/board-table.js`, `alpine/board-treemap.js`)
 * khodesh SVG ro innerHTML mikone chon adad ha faghat az kanal-e ramz-shode miyan (hich adadi inja
 * render-e server nadare — hamun DOM-e placeholder ta ghabl az avalin payload khali mimune).
 */

/**
 * @param {number[]} points
 * @param {{ width?: number, height?: number, pad?: number }} [opts]
 * @returns {string} `d`-e yek `<path>` — reshte-ye khali agar kamtar az do noghte bashe.
 */
export function sparklinePath(points, { width = 64, height = 24, pad = 2 } = {}) {
    const values = (points ?? []).filter((v) => typeof v === 'number' && Number.isFinite(v));

    if (values.length < 2) return '';

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const innerW = width - pad * 2;
    const innerH = height - pad * 2;
    const step = innerW / (values.length - 1);

    return values
        .map((v, i) => {
            const x = pad + i * step;
            const y = pad + innerH - ((v - min) / range) * innerH;

            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

/**
 * SVG-e amade baraye `innerHTML` — rang az `currentColor` (element-e valed class-e up/down/flat
 * migire, hamun ravesh-e SelectorMap-e token-only, hich hex-i inja nist).
 *
 * @param {number[]} points
 * @param {{ width?: number, height?: number }} [opts]
 * @returns {string}
 */
export function sparklineSvg(points, opts = {}) {
    const { width = 64, height = 24 } = opts;
    const d = sparklinePath(points, opts);

    if (d === '') return '';

    return `<svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${d}"></path></svg>`;
}
