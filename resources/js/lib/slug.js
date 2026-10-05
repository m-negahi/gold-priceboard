/**
 * Ayene-ye samt-e client-e `App\Modules\Common\Support\SlugHelper::make()` — hamun tartib, hamun
 * ghavanin. `tests/Fixtures/slugs.json` har do ro rooye yek fixture negah midare
 * (PHP: SlugHelperTest، JS: e2e/slug-field.spec.ts). Yek taghir inja bedoone taghir-e un-var
 * yani form chizi neshun mide ke server zakhire nemikone.
 */
import { toAscii } from './digits.js';

const MAX_LENGTH = 120;

// PersianText::LETTERS
const LETTERS = {
    'ي': 'ی', 'ك': 'ک', 'ة': 'ه', 'ۀ': 'ه',
    'أ': 'ا', 'إ': 'ا', 'آ': 'آ', 'ؤ': 'و', 'ئ': 'ی',
};

// PersianText::DIACRITICS — harekat-haye arabi + kashide
const DIACRITICS = /[ً-ٰٟـ]/g;

// SlugHelper::PUNCTUATION
const PUNCTUATION = /[،؍؛؞؟٪٭۔۝۞«»]/g;

// SlugHelper::LIGATURES
const LIGATURES = { 'ß': 'ss', 'æ': 'ae', 'œ': 'oe', 'ø': 'o', 'đ': 'd', 'ł': 'l', 'ð': 'd', 'þ': 'th' };

const NON_ARABIC_RUN = /[^\p{Script=Arabic}]+/gu;
const KEEP = /[^\p{Script=Arabic}a-z0-9]+/gu;

/** PersianText::normalize(value, flattenZwnj: true) */
function persianText(value) {
    return value
        .replace(/[يكةۀأإآؤئ]/g, (c) => LETTERS[c])
        .replace(DIACRITICS, '')
        .replace(/‌/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** NFD + hazf-e mark ha, faghat rooye run-haye gheyr-e arabi (NFD «آ» ro tajziye mikone). */
function transliterate(run) {
    return run
        .replace(/[ßæœøđłðþ]/g, (c) => LIGATURES[c])
        .normalize('NFD')
        .replace(/\p{M}+/gu, '');
}

function truncate(slug) {
    if ([...slug].length <= MAX_LENGTH) return slug;

    const cut = [...slug].slice(0, MAX_LENGTH).join('');
    const lastDash = cut.lastIndexOf('-');

    return (lastDash === -1 ? cut : cut.slice(0, lastDash)).replace(/^-+|-+$/g, '');
}

export function slugify(title) {
    let value = persianText(String(title ?? ''));
    value = toAscii(value);
    value = value.toLowerCase();

    value = value.replace(PUNCTUATION, ' ');
    value = value.replace(NON_ARABIC_RUN, (run) => transliterate(run));
    value = value.replace(KEEP, '-');
    value = value.replace(/-+/g, '-').replace(/^-+|-+$/g, '');

    return truncate(value);
}

export default slugify;
