/**
 * Ragham-e farsi/arabi ↔ ASCII, yek ja baraye kol-e front (ayene-ye `Common\Support\Digits`).
 *
 * - `toAscii`: ۰-۹ va ٠-٩ → 0-9، `٫` → `.`، `٬`-e beyn-e do ragham hazf — hamun kari ke middleware-e
 *   NormalizeInput samt-e server mikone, vali ghabl az submit.
 * - `toPersian`: 0-9 → ۰-۹ baraye matn-i ke be karbar neshun dade mishe.
 * - `format`: adad ba khana-bandi-ye fa-IR (Intl.NumberFormat-e cache shode — sakhtanesh geroon-e,
 *   pas hich vaght dakhel-e state-e reactive-e Alpine nemishine).
 */
const PERSIAN = '۰۱۲۳۴۵۶۷۸۹';
const ARABIC = '٠١٢٣٤٥٦٧٨٩';

/** @type {Map<string, Intl.NumberFormat>} */
const formatters = new Map();

/**
 * @param {unknown} value
 * @returns {string}
 */
export function toAscii(value) {
    return String(value ?? '')
        .replace(/[۰-۹]/g, (d) => String(PERSIAN.indexOf(d)))
        .replace(/[٠-٩]/g, (d) => String(ARABIC.indexOf(d)))
        .replace(/(?<=\d)٬(?=\d)/g, '')
        .replace(/٫/g, '.');
}

/**
 * @param {unknown} value
 * @returns {string}
 */
export function toPersian(value) {
    return String(value ?? '').replace(/\d/g, (d) => PERSIAN[Number(d)]);
}

/**
 * Faghat ragham ha, ASCII — baraye code-e OTP va shomare-ye mobile.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function digitsOnly(value) {
    return toAscii(value).replace(/\D/g, '');
}

/**
 * @param {number|string|null|undefined} value
 * @param {Intl.NumberFormatOptions} [options]
 * @returns {string}
 */
export function format(value, options = {}) {
    const key = JSON.stringify(options);
    let formatter = formatters.get(key);

    if (!formatter) {
        formatter = new Intl.NumberFormat('fa-IR', options);
        formatters.set(key, formatter);
    }

    return formatter.format(Number(value ?? 0));
}
