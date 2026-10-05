/**
 * Ayene-ye samt-e client-e `App\Modules\Seo\Support\SeoAnalyzer` (ADR-0041) — hamun tartib, hamun
 * ghavanin, hamun payam ha. `tests/Fixtures/seo/cases.json` har do ro rooye yek fixture negah
 * midare (PHP: SeoAnalyzerTest، JS: e2e/seo.spec.ts). Yek taghir inja bedoone taghir-e un-var
 * yani ring-e nomre tu editor chizi neshun mide ke server zakhire nemikone.
 *
 * Bedoone `window`/DOM: rooye Node ham ejra mishe (fixture check) ham tu browser.
 * Hame-ye gerd-kardan ha ba hesab-e sahih (integer) — daghighan mesle PHP.
 */

export const MIN_WORDS = 300;

// SeoAnalyzer::WS — JS `\s` hamin-e vali sarih, ta ba PCRE yeki bashe.
const WS = /[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000\uFEFF]+/g;

// SeoAnalyzer::LETTERS
const LETTERS = { 'ي': 'ی', 'ى': 'ی', 'ك': 'ک', 'ة': 'ه', 'ۀ': 'ه', 'أ': 'ا', 'إ': 'ا', 'ؤ': 'و', 'ئ': 'ی' };
const LETTERS_RE = /[يىكةۀأإؤئ]/g;

// SeoAnalyzer::DIACRITICS
const DIACRITICS = /[\u064B-\u065F\u0670\u0640]/g;

const DIGITS = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
const DIGITS_RE = /[۰-۹٠-٩]/g;

// SeoAnalyzer::PUNCTUATION
const PUNCTUATION = /[.,،؛;:!؟?()«»"'‘’“”\-–—_/\\[\]{}|*#+=<>~`%&$^@]/g;

const SENTENCE_END = /[.!?؟۔\n]+/;

// SeoAnalyzer::TRANSITIONS
const TRANSITIONS = [
    'اما', 'ولی', 'اگرچه', 'هرچند', 'بنابراین', 'در نتیجه', 'در نهایت', 'پس از', 'سپس', 'همچنین',
    'علاوه بر این', 'به علاوه', 'از این رو', 'زیرا', 'چون', 'به همین دلیل', 'برای مثال', 'مثلا',
    'به عنوان مثال', 'یعنی', 'در واقع', 'به بیان دیگر', 'از سوی دیگر', 'در مقابل', 'برعکس',
    'نخست', 'دوم', 'سرانجام', 'خلاصه', 'به طور کلی', 'در مجموع', 'با این حال', 'با وجود این',
    'تا اینکه', 'در حالی که', 'همان طور که', 'به طوری که', 'در ادامه', 'پیش از این', 'افزون بر آن',
];

// SeoAnalyzer::PASSIVE
const PASSIVE = [
    'شد', 'شدند', 'شده', 'شده است', 'شده اند', 'شده بود', 'شده بودند', 'می شود', 'می شوند', 'می شد', 'می شدند',
    'نمی شود', 'نمی شوند', 'خواهد شد', 'خواهند شد', 'گردید', 'گردیدند', 'می گردد', 'می گردند',
];

const ENTITIES = { '&nbsp;': ' ', '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'", '&apos;': "'", '&zwnj;': '\u200C' };

const POINTS = { ok: 2, warn: 1, fail: 0 };

// ── Normal-sazi ─────────────────────────────────────────────────────────────

/** SeoAnalyzer::norm */
export function norm(value) {
    return collapse(
        String(value ?? '')
            .toLowerCase()
            .replace(/\u200C/g, ' ')
            .replace(LETTERS_RE, (c) => LETTERS[c])
            .replace(DIACRITICS, '')
            .replace(DIGITS_RE, (d) => DIGITS[d])
            .replace(PUNCTUATION, ' '),
    );
}

function collapse(value) {
    return String(value).replace(WS, ' ').trim();
}

function tokens(text) {
    const n = norm(text);

    return n === '' ? [] : n.split(' ');
}

function has(hay, needle) {
    const n = norm(needle);

    return n !== '' && norm(hay).includes(n);
}

function hasPhrase(normalizedSentence, phrase) {
    return ` ${normalizedSentence} `.includes(` ${phrase} `);
}

function occurrences(hay, needle) {
    const n = norm(needle);
    if (n === '') return 0;

    const h = norm(hay);
    let i = 0;
    let c = 0;

    while ((i = h.indexOf(n, i)) !== -1) {
        c++;
        i += n.length;
    }

    return c;
}

/** Grapheme-ha (emoji va harf-e tarkibi yek nevise) — hamun `preg_match_all('/./us')`-e PHP. */
function length(value) {
    return [...String(value ?? '')].length;
}

/** round(x * 100 / y) ba hesab-e sahih. */
function percent(x, y) {
    return y === 0 ? 0 : Math.floor((x * 200 + y) / (2 * y));
}

/** round(x / y) ba hesab-e sahih. */
function ratio(x, y) {
    return y === 0 ? 0 : Math.floor((x * 2 + y) / (2 * y));
}

function fa(n) {
    return String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

function faPermille(permille) {
    return `${fa(Math.floor(permille / 10))}٫${fa(permille % 10)}`;
}

// ── Estekhraj az HTML ───────────────────────────────────────────────────────

function plain(fragment) {
    return collapse(
        String(fragment)
            .replace(/<[^>]*>/g, ' ')
            .replace(/&(nbsp|amp|lt|gt|quot|#39|apos|zwnj);/g, (m) => ENTITIES[m])
            .replace(/&#(x[0-9a-f]+|\d+);/gi, (_, code) => {
                const point = code.toLowerCase().startsWith('x') ? Number.parseInt(code.slice(1), 16) : Number.parseInt(code, 10);

                return Number.isInteger(point) && point > 0 && point < 0x110000 ? String.fromCodePoint(point) : ' ';
            }),
    );
}

function attr(attrs, name) {
    const m = new RegExp(`${name}\\s*=\\s*["']([^"']*)["']`, 'i').exec(attrs);

    return m ? m[1] : null;
}

function document_(html, siteHost) {
    html = String(html ?? '').replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, ' ');

    const headings = [];
    for (const m of html.matchAll(/<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/gi)) {
        headings.push({ level: Number(m[1]), text: plain(m[2]) });
    }

    const links = [];
    for (const m of html.matchAll(/<a\b([^>]*)>[\s\S]*?<\/a>/gi)) {
        const href = (attr(m[1], 'href') ?? '').trim();
        if (href === '' || href.startsWith('#')) continue;

        const host = /^https?:\/\/([^/:?#]+)/i.exec(href);
        links.push({ href, external: host !== null && host[1].toLowerCase() !== String(siteHost ?? '').toLowerCase() });
    }

    const images = [];
    for (const m of html.matchAll(/<img\b([^>]*)>/gi)) {
        const alt = attr(m[1], 'alt');
        images.push({ src: attr(m[1], 'src') ?? '', alt: alt === null ? '' : plain(alt) });
    }

    const paragraphs = [];
    for (const m of html.matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi)) {
        const p = plain(m[1]);
        if (p !== '') paragraphs.push(p);
    }

    // Baste shodan-e block → noghte, ta jomle-haye do paragraph ya yek titr be ham nachasban.
    const text = plain(html.replace(/<\/(p|h[1-6]|li|blockquote|div|pre|tr|td|th)>|<(br|hr)\b[^>]*>/gi, ' . '));
    const toks = tokens(text);

    if (paragraphs.length === 0 && text !== '') paragraphs.push(text);

    const sentences = [];
    for (const raw of text.split(SENTENCE_END)) {
        const sentence = collapse(raw);
        if (tokens(sentence).length > 1) sentences.push(sentence);
    }

    return {
        text,
        words: toks.length,
        tokens: toks,
        sentences,
        paragraphs,
        first: paragraphs[0] ?? text.split(' ').slice(0, 60).join(' '),
        headings,
        links,
        images,
    };
}

// ── Checklist-e سئو ─────────────────────────────────────────────────────────

function seoChecks(subject, doc) {
    const out = [];
    const add = (key, status, message) => out.push({ key, status, message });

    const title = subject.seo_title.trim() !== '' ? subject.seo_title.trim() : subject.title.trim();
    const description = subject.description.trim();
    const t = length(title);
    const d = length(description);
    const { words } = doc;

    if (t === 0) add('title_length', 'fail', 'عنوان سئو خالی است.');
    else if (t < 30) add('title_length', 'warn', 'عنوان سئو کوتاه است (کمتر از ۳۰ نویسه).');
    else if (t <= 60) add('title_length', 'ok', 'طول عنوان سئو مناسب است.');
    else add('title_length', 'fail', 'عنوان سئو بلند است (بیش از ۶۰ نویسه؛ در گوگل بریده می‌شود).');

    if (d === 0) add('description_length', 'fail', 'توضیحات متا خالی است.');
    else if (d < 120) add('description_length', 'warn', 'توضیحات متا کوتاه است (بهتر ۱۲۰ تا ۱۶۰ نویسه).');
    else if (d <= 160) add('description_length', 'ok', 'طول توضیحات متا مناسب است.');
    else add('description_length', 'fail', 'توضیحات متا بلند است (بیش از ۱۶۰ نویسه).');

    if (words >= MIN_WORDS) add('content_length', 'ok', `حجم محتوا کافی است (${fa(words)} کلمه).`);
    else if (words >= 120) add('content_length', 'warn', `محتوا کمی کوتاه است (${fa(words)} کلمه؛ هدف ۳۰۰+).`);
    else add('content_length', 'fail', `محتوا بسیار کوتاه است (${fa(words)} کلمه).`);

    const images = doc.images.length;
    const noAlt = doc.images.filter((i) => i.alt === '').length;

    if (images === 0) add('images_alt', 'warn', 'هیچ تصویری در محتوا نیست (افزودن تصویر توصیه می‌شود).');
    else if (noAlt === 0) add('images_alt', 'ok', `همه‌ی ${fa(images)} تصویر متن جایگزین (alt) دارند.`);
    else add('images_alt', 'fail', `${fa(noAlt)} تصویر بدون متن جایگزین (alt) است.`);

    const internal = doc.links.filter((l) => !l.external).length;
    const external = doc.links.length - internal;
    if (internal > 0) add('internal_links', 'ok', `${fa(internal)} پیوند داخلی دارد.`);
    else add('internal_links', 'fail', 'پیوند داخلی ندارد (به صفحه‌های مرتبط خودتان پیوند بدهید).');
    if (external > 0) add('external_links', 'ok', `${fa(external)} پیوند خارجی دارد.`);
    else add('external_links', 'warn', 'پیوند خارجی ندارد (ارجاع به یک منبع معتبر مفید است).');

    if (doc.headings.some((h) => h.level === 1)) add('h1_in_body', 'fail', 'متن نباید H1 داشته باشد؛ عنوان صفحه خودش H1 است.');

    const subs = doc.headings.filter((h) => h.level >= 2);
    if (subs.length === 0) {
        if (words >= MIN_WORDS) add('subheadings', 'fail', 'متن طولانی است و هیچ زیرعنوانی (H2–H4) ندارد.');
        else add('subheadings', 'warn', 'محتوا زیرعنوان (H2–H4) ندارد.');
    } else {
        add('subheadings', 'ok', `${fa(subs.length)} زیرعنوان دارد.`);
    }

    const kw = subject.focus_keyword.trim();
    if (norm(kw) === '') {
        add('focus_keyword_set', 'na', 'کلمه‌ی کلیدی کانونی تعیین نشده — برای تحلیل دقیق‌تر آن را وارد کنید.');

        return out;
    }

    const kwWords = tokens(kw).length;
    if (kwWords <= 4) add('keyword_length', 'ok', `طول کلمه‌ی کلیدی مناسب است (${fa(kwWords)} کلمه).`);
    else add('keyword_length', 'warn', `کلمه‌ی کلیدی طولانی است (${fa(kwWords)} کلمه؛ ۱ تا ۴ کلمه بهتر است).`);

    if (subject.used_keywords.length > 0) {
        if (subject.used_keywords.map(norm).includes(norm(kw))) add('keyword_unique', 'fail', 'این کلمه‌ی کلیدی قبلاً برای محتوای دیگری استفاده شده (رقابت دو صفحه‌ی خودتان).');
        else add('keyword_unique', 'ok', 'کلمه‌ی کلیدی در محتوای دیگری استفاده نشده است.');
    }

    if (has(title, kw)) add('keyword_in_title', 'ok', 'کلمه‌ی کلیدی در عنوان سئو آمده است.');
    else add('keyword_in_title', 'fail', 'کلمه‌ی کلیدی در عنوان سئو نیست.');
    if (has(description, kw)) add('keyword_in_description', 'ok', 'کلمه‌ی کلیدی در توضیحات متا آمده است.');
    else add('keyword_in_description', 'fail', 'کلمه‌ی کلیدی در توضیحات متا نیست.');
    if (has(subject.slug, kw)) add('keyword_in_slug', 'ok', 'کلمه‌ی کلیدی در نشانی (slug) آمده است.');
    else add('keyword_in_slug', 'warn', 'کلمه‌ی کلیدی در نشانی (slug) نیست.');
    if (has(doc.text, kw)) add('keyword_in_body', 'ok', 'کلمه‌ی کلیدی در متن محتوا آمده است.');
    else add('keyword_in_body', 'fail', 'کلمه‌ی کلیدی در متن محتوا پیدا نشد.');
    if (has(doc.first, kw)) add('keyword_in_first_paragraph', 'ok', 'کلمه‌ی کلیدی در پاراگراف ابتدایی آمده است.');
    else add('keyword_in_first_paragraph', 'warn', 'کلمه‌ی کلیدی در پاراگراف ابتدایی نیست.');

    if (subs.length > 0) {
        const hit = subs.some((h) => has(h.text, kw) || subject.related_keywords.some((r) => has(h.text, r)));
        if (hit) add('keyword_in_subheading', 'ok', 'کلمه‌ی کلیدی در زیرعنوان‌ها آمده است.');
        else add('keyword_in_subheading', 'fail', 'هیچ زیرعنوانی شامل کلمه‌ی کلیدی نیست.');
    }

    if (images > 0) {
        if (doc.images.some((i) => has(i.alt, kw))) add('keyword_in_alt', 'ok', 'کلمه‌ی کلیدی در متن جایگزین تصویر آمده است.');
        else add('keyword_in_alt', 'warn', 'کلمه‌ی کلیدی در alt هیچ تصویری نیست.');
    }

    if (words > 0) {
        const permille = Math.floor((occurrences(doc.text, kw) * 2000 + words) / (2 * words));
        if (permille < 5) add('keyword_density', 'warn', `تراکم کلمه‌ی کلیدی کم است (${faPermille(permille)}٪).`);
        else if (permille <= 30) add('keyword_density', 'ok', `تراکم کلمه‌ی کلیدی مناسب است (${faPermille(permille)}٪).`);
        else add('keyword_density', 'fail', `تراکم کلمه‌ی کلیدی زیاد است (${faPermille(permille)}٪ — خطر تکرار بیش از حد).`);
    }

    if (words >= 60) {
        const third = Math.floor((words + 2) / 3);
        const needle = norm(kw);
        const parts = [doc.tokens.slice(0, third), doc.tokens.slice(third, third * 2), doc.tokens.slice(third * 2)];
        const spread = parts.filter((p) => p.join(' ').includes(needle)).length;
        if (spread === 3) add('keyword_spread', 'ok', 'کلمه‌ی کلیدی در سراسر متن پخش شده است.');
        else if (spread === 2) add('keyword_spread', 'warn', 'کلمه‌ی کلیدی در بخشی از متن دیده نمی‌شود.');
        else add('keyword_spread', 'fail', 'کلمه‌ی کلیدی فقط در یک بخش متن تکرار شده است.');
    }

    if (subject.related_keywords.length > 0) {
        const hits = subject.related_keywords.filter((r) => has(doc.text, r)).length;
        if (hits > 0) add('related_keywords', 'ok', `${fa(hits)} کلیدواژه‌ی مرتبط در متن آمده است.`);
        else add('related_keywords', 'warn', 'کلیدواژه‌های مرتبط در متن استفاده نشده‌اند.');
    }

    return out;
}

// ── Checklist-e خوانایی ─────────────────────────────────────────────────────

function readabilityChecks(doc) {
    const out = [];
    const add = (key, status, message) => out.push({ key, status, message });
    const { words } = doc;

    if (words === 0) {
        add('no_content', 'na', 'محتوایی برای سنجش خوانایی نیست.');

        return out;
    }

    const { sentences } = doc;
    const count = sentences.length;
    const avg = count > 0 ? ratio(words, count) : words;
    if (avg <= 20) add('sentence_length', 'ok', `میانگین طول جمله مناسب است (${fa(avg)} کلمه).`);
    else if (avg <= 30) add('sentence_length', 'warn', `جمله‌ها کمی بلندند (${fa(avg)} کلمه).`);
    else add('sentence_length', 'fail', `جمله‌ها بسیار بلندند (${fa(avg)} کلمه) — کوتاه‌ترشان کنید.`);

    const normalized = sentences.map(norm);

    if (count >= 3) {
        const long = normalized.filter((s) => s.split(' ').length > 25).length;
        const r = percent(long, count);
        if (r <= 25) add('long_sentences', 'ok', `نسبت جمله‌های بلند مناسب است (${fa(r)}٪).`);
        else add('long_sentences', 'fail', `${fa(r)}٪ جمله‌ها بلندند (سقف پیشنهادی ۲۵٪).`);
    }

    const longParagraphs = doc.paragraphs.filter((p) => tokens(p).length > 150).length;
    if (longParagraphs === 0) add('paragraph_length', 'ok', 'طول پاراگراف‌ها مناسب است.');
    else add('paragraph_length', 'fail', `${fa(longParagraphs)} پاراگراف بیش از ۱۵۰ کلمه دارد.`);

    if (words >= MIN_WORDS) {
        const need = Math.floor(words / MIN_WORDS);
        const subs = doc.headings.filter((h) => h.level >= 2).length;
        if (subs >= need) add('heading_distribution', 'ok', 'زیرعنوان‌ها به‌خوبی متن را تقسیم کرده‌اند.');
        else add('heading_distribution', 'fail', `متن طولانی است و زیرعنوان کافی ندارد (حداقل ${fa(need)} زیرعنوان).`);
    }

    if (count >= 3) {
        const normalizedTransitions = TRANSITIONS.map(norm);
        const withTransition = normalized.filter((s) => normalizedTransitions.some((t) => hasPhrase(s, t))).length;
        let r = percent(withTransition, count);
        if (r >= 30) add('transitions', 'ok', `کلمات ربط به‌اندازه استفاده شده‌اند (${fa(r)}٪ جمله‌ها).`);
        else if (r >= 15) add('transitions', 'warn', `کلمات ربط کم‌اند (${fa(r)}٪ جمله‌ها؛ هدف ۳۰٪).`);
        else add('transitions', 'fail', `کلمات ربط بسیار کم‌اند (${fa(r)}٪) — متن بریده‌بریده خوانده می‌شود.`);

        const passive = normalized.filter((s) => PASSIVE.some((p) => hasPhrase(s, p))).length;
        r = percent(passive, count);
        if (r <= 10) add('passive', 'ok', `جمله‌های مجهول کم‌اند (${fa(r)}٪).`);
        else if (r <= 25) add('passive', 'warn', `${fa(r)}٪ جمله‌ها مجهول‌اند (سقف پیشنهادی ۱۰٪).`);
        else add('passive', 'fail', `${fa(r)}٪ جمله‌ها مجهول‌اند — جمله‌ها را معلوم بنویسید.`);

        let repeats = 0;
        let streak = 1;
        for (let i = 1; i < count; i++) {
            const a = normalized[i - 1].split(' ')[0];
            const b = normalized[i].split(' ')[0];
            streak = a !== '' && a === b ? streak + 1 : 1;
            if (streak >= 3) repeats++;
        }
        if (repeats === 0) add('sentence_starts', 'ok', 'شروع جمله‌ها متنوع است.');
        else add('sentence_starts', 'warn', 'چند جمله‌ی پشت‌سرهم با یک کلمه شروع شده‌اند.');
    }

    return out;
}

function score(checks) {
    let applicable = 0;
    let got = 0;

    for (const check of checks) {
        if (check.status === 'na') continue;
        applicable++;
        got += POINTS[check.status];
    }

    return applicable === 0 ? 0 : Math.floor((got * 100 + applicable) / (2 * applicable));
}

/**
 * Vorudi hamun shekl-e `SeoSubjectDto` (snake_case): title, seo_title, description, slug, body_html,
 * focus_keyword, related_keywords[], used_keywords[], site_host. Khorooji hamun `SeoReportDto::toArray()`.
 */
export function analyze(input) {
    const subject = {
        title: String(input.title ?? ''),
        seo_title: String(input.seo_title ?? ''),
        description: String(input.description ?? ''),
        slug: String(input.slug ?? ''),
        body_html: String(input.body_html ?? ''),
        focus_keyword: String(input.focus_keyword ?? ''),
        related_keywords: (input.related_keywords ?? []).map(String),
        used_keywords: (input.used_keywords ?? []).map(String),
        site_host: String(input.site_host ?? ''),
    };
    const doc = document_(subject.body_html, subject.site_host);
    const checks = seoChecks(subject, doc);
    const readability = readabilityChecks(doc);

    return { seo_score: score(checks), readability_score: score(readability), words: doc.words, checks, readability };
}
