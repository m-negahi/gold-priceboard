/**
 * Field-e tarikh-e shamsi ba picker-e charkhi-ye iOS-style (docs/frontend/admin-panel.md §7).
 *
 * Gharardad-e meghdar: `value` hamishe ya '' ya yek tarikh-e ISO-ye miladi-e (`2026-09-26`) —
 * hamun chizi ke server zakhire mikone. Karbar faghat jalali mibine: tu box `۱۴۰۵/۰۷/۰۴` va tu
 * picker se charkh-e rooz / mah / sal. Parent ba `x-model` (az tarigh-e x-modelable) ya ba hidden
 * input-e `name` be value miresed.
 *
 * Converter-e paeen ayneh-ye `Common\Helpers\Jalali`-e PHP-e (algorithm-e Borkowski, hamun ke
 * morilog dare) va tests/Fixtures/jalali-dates.json har do ro rooye 40 tarikh yeki negah midare.
 */
import { toAscii, toPersian } from '../lib/digits.js';

const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// Sal-haye shekast-e cycle-e 33 sale (jalaali-js). Ta 3178 dorost-e; bishtar az un be dard-e ma nemikhore.
const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

const div = (a, b) => Math.trunc(a / b);
const mod = (a, b) => a - Math.trunc(a / b) * b;
const pad = (n) => String(n).padStart(2, '0');

/** Baraye yek sal-e jalali: kabise boodan, sal-e miladi-e motenazer va rooz-e Nowruz dar March. */
function jalCal(jy) {
    const gy = jy + 621;
    let leapJ = -14;
    let jp = BREAKS[0];
    let jump = 0;

    for (let i = 1; i < BREAKS.length; i++) {
        const jm = BREAKS[i];
        jump = jm - jp;
        if (jy < jm) break;
        leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
        jp = jm;
    }

    let n = jy - jp;
    leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;

    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;

    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    let leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;

    return { leap, gy, march };
}

/** Tarikh-e miladi → shomare-ye rooz-e Julian. */
function g2d(gy, gm, gd) {
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;

    return d;
}

/** Shomare-ye rooz-e Julian → tarikh-e miladi. */
function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div(mod(j, 1461), 4) * 5 + 308;
    const gd = div(mod(i, 153), 5) + 1;
    const gm = mod(div(i, 153), 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);

    return { gy, gm, gd };
}

function j2d(jy, jm, jd) {
    const r = jalCal(jy);

    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

function d2j(jdn) {
    const gy = d2g(jdn).gy;
    let jy = gy - 621;
    const r = jalCal(jy);
    let k = jdn - g2d(gy, 3, r.march);

    if (k >= 0) {
        if (k <= 185) return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
        k -= 186;
    } else {
        jy -= 1;
        k += 179;
        if (r.leap === 1) k += 1;
    }

    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

/** Converter-e khalis — bedoone DOM, ta spec-e Playwright betoone mostaghim import-esh kone. */
export const jalali = {
    months: MONTHS,

    isLeapYear: (jy) => jalCal(jy).leap === 0,

    /** Shesh mah-e aval 31, panj mah-e ba'd 30, Esfand 29 ya 30 dar sal-e kabise. */
    daysInMonth(jy, jm) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;

        return this.isLeapYear(jy) ? 30 : 29;
    },

    toJalali: (gy, gm, gd) => d2j(g2d(gy, gm, gd)),
    toGregorian: (jy, jm, jd) => d2g(j2d(jy, jm, jd)),

    // Ragham ha az lib/digits miad; in do esm baraye spec va callers-e ghadimi negah dashte shodan.
    toAsciiDigits: (value) => toAscii(value),

    toPersianDigits: (value) => toPersian(value),

    /** `2026-09-26` → {jy, jm, jd} ya null vaghti tarikh-e vaghei-ye ISO nabashe. */
    fromIso(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? '').trim());
        if (!m) return null;

        const [gy, gm, gd] = [+m[1], +m[2], +m[3]];
        const probe = new Date(Date.UTC(gy, gm - 1, gd));
        if (probe.getUTCFullYear() !== gy || probe.getUTCMonth() !== gm - 1 || probe.getUTCDate() !== gd) return null;

        return this.toJalali(gy, gm, gd);
    },

    /** {jy, jm, jd} → `2026-09-26`; rooz-e nadashte-ye mah null mishe. */
    toIso(j) {
        if (!this.isValid(j)) return null;

        const g = this.toGregorian(j.jy, j.jm, j.jd);

        return `${String(g.gy).padStart(4, '0')}-${pad(g.gm)}-${pad(g.gd)}`;
    },

    isValid(j) {
        return !!j && Number.isInteger(j.jy) && Number.isInteger(j.jm) && Number.isInteger(j.jd)
            && j.jm >= 1 && j.jm <= 12 && j.jd >= 1 && j.jd <= this.daysInMonth(j.jy, j.jm);
    },

    /** {jy, jm, jd} → `۱۴۰۵/۰۷/۰۴`. */
    format(j, persianDigits = true) {
        const text = `${String(j.jy).padStart(4, '0')}/${pad(j.jm)}/${pad(j.jd)}`;

        return persianDigits ? this.toPersianDigits(text) : text;
    },

    /** `۴ مهر ۱۴۰۵` — baraye screen reader va live region. */
    formatLong(j) {
        return `${this.toPersianDigits(j.jd)} ${MONTHS[j.jm - 1]} ${this.toPersianDigits(j.jy)}`;
    },

    /** Emrooz be vaght-e mahalli-ye browser. */
    today() {
        const now = new Date();

        return this.toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
    },
};

const DRUMS = ['day', 'month', 'year'];

export default ({ value = '', min = null, max = null, disabled = false } = {}) => ({
    value: value ?? '',
    text: '',
    error: '',
    open: false,
    openUp: false,
    disabled,
    min: jalali.fromIso(min),
    max: jalali.fromIso(max),
    /** Chizi ke charkh ha neshoon midan — vaghti field khali-e, emrooz (ya nazdik-tarin rooz-e mojaz). */
    sel: { jy: 0, jm: 0, jd: 0 },
    /** Meghdar-e lahze-ye baz shodan, ta Escape betoone bargardoone. */
    before: '',
    itemHeight: 0,
    focusedDrum: 'day',
    dragging: null,
    /** Focus-e barnamei rooye box nabayad picker ro dobare baz kone. */
    quiet: false,
    scrollTimers: {},
    // Maghsad-e scroll-e barnameyi-ye har charkh (px). Ta be maghsad narese, `settle` nabayad
    // scrollTop-e miyane-rah ro be onvan-e entekhab-e karbar commit kone (race-e smooth scroll).
    scrollTargets: {},
    months: MONTHS,

    init() {
        const today = jalali.today();
        this.minYear = this.min ? this.min.jy : today.jy - 100;
        this.maxYear = this.max ? this.max.jy : today.jy + 20;

        this.applyValue(this.value);

        // Az biroon (x-model-e parent) taghir kard: box va charkh ha ro hamrah kon.
        this.$watch('value', (v) => {
            if (v !== this.isoOfText()) this.applyValue(v);
        });
    },

    // ── Meghdar ────────────────────────────────────────────────────────────────────────

    /** Yek ISO (ya '') ro be text-e box va charkh ha mibare; ISO-ye kharab mesle khali raftar mikone. */
    applyValue(iso) {
        const j = jalali.fromIso(iso);

        if (j) {
            this.value = iso;
            this.text = jalali.format(j);
            this.sel = { ...j };
            this.error = '';
            return;
        }

        this.value = '';
        this.text = '';
        this.error = '';
        this.sel = this.clamp(jalali.today());
    },

    /** ISO-i ke text-e fe'li-ye box neshoon mide, ya '' vaghti kamel ya mo'tabar nist. */
    isoOfText() {
        const j = this.parseText(this.text);

        return j && jalali.isValid(j) && this.inRange(j) ? jalali.toIso(j) : '';
    },

    /** Charkh ha ro rooye `j` mizare, meghdar ro commit mikone va box ro ham-khan mikone. */
    commit(j) {
        j = this.clamp(j);
        this.sel = { ...j };
        this.text = jalali.format(j);
        this.error = '';

        const iso = jalali.toIso(j);
        if (iso !== this.value) {
            this.value = iso;
            this.$dispatch('change', iso);
        }

        if (this.open) this.$nextTick(() => this.scrollAll());
    },

    clear() {
        this.text = '';
        this.error = '';
        this.sel = this.clamp(jalali.today());

        if (this.value !== '') {
            this.value = '';
            this.$dispatch('change', '');
        }

        if (this.open) this.$nextTick(() => this.scrollAll());
        this.focusInput();
    },

    /** Box ro focus mikone bedoone inke `@focus` picker ro baz kone. */
    focusInput() {
        this.quiet = true;
        this.$refs.input?.focus();
        this.quiet = false;
    },

    onFocus() {
        if (!this.quiet) this.show();
    },

    pickToday() {
        this.commit(jalali.today());
    },

    inRange(j) {
        const key = (d) => d.jy * 10000 + d.jm * 100 + d.jd;

        return (!this.min || key(j) >= key(this.min)) && (!this.max || key(j) <= key(this.max));
    },

    /** Rooz ro be tool-e mah va tarikh ro be min/max mahdood mikone. */
    clamp(j) {
        const out = { ...j };
        out.jd = Math.min(out.jd, jalali.daysInMonth(out.jy, out.jm));

        const key = (d) => d.jy * 10000 + d.jm * 100 + d.jd;
        if (this.min && key(out) < key(this.min)) return { ...this.min };
        if (this.max && key(out) > key(this.max)) return { ...this.max };

        return out;
    },

    // ── Type kardan ────────────────────────────────────────────────────────────────────

    /** `۱۴۰۵۰۷۰۴`, `1405/7/4`, `1405-07-04` — har chi ke bashe be {jy, jm, jd} ya null. */
    parseText(text) {
        const m = /^(\d{4})\/(\d{2})\/(\d{2})$/.exec(this.formatTyping(jalali.toAsciiDigits(text), false));

        return m ? { jy: +m[1], jm: +m[2], jd: +m[3] } : null;
    },

    /** Faghat ragham ha ro negah midare va `/` ha ro sar-e ja-ye khodeshun mizare: 4/2/2. */
    formatTyping(raw, persian = true) {
        const digits = jalali.toAsciiDigits(raw).replace(/\D/g, '').slice(0, 8);
        let out = digits.slice(0, 4);
        if (digits.length > 4) out += '/' + digits.slice(4, 6);
        if (digits.length > 6) out += '/' + digits.slice(6, 8);

        return persian ? jalali.toPersianDigits(out) : out;
    },

    onInput(event) {
        const formatted = this.formatTyping(event.target.value);
        this.text = formatted;
        event.target.value = formatted;

        const digits = jalali.toAsciiDigits(formatted).replace(/\D/g, '');

        if (digits.length < 8) {
            // Hanooz kamel nist: meghdar-e ghabli dige mo'tabar nist, vali khata ham hanooz zood-e.
            this.error = '';
            this.setValue('');
            return;
        }

        const j = this.parseText(formatted);

        if (!jalali.isValid(j)) {
            this.error = j && j.jm >= 1 && j.jm <= 12 ? `${jalali.months[j.jm - 1]} ${jalali.toPersianDigits(j.jy)} فقط ${jalali.toPersianDigits(jalali.daysInMonth(j.jy, j.jm))} روز دارد.` : 'تاریخ معتبر نیست.';
            this.setValue('');
            return;
        }

        if (!this.inRange(j)) {
            this.error = this.rangeMessage(j);
            this.setValue('');
            return;
        }

        this.error = '';
        this.sel = { ...j };
        this.setValue(jalali.toIso(j));
        if (this.open) this.$nextTick(() => this.scrollAll());
    },

    onBlur() {
        if (this.text !== '' && this.value === '' && this.error === '') this.error = 'تاریخ کامل نیست.';
    },

    rangeMessage(j) {
        const key = (d) => d.jy * 10000 + d.jm * 100 + d.jd;

        if (this.min && key(j) < key(this.min)) return `تاریخ باید از ${jalali.format(this.min)} به بعد باشد.`;

        return `تاریخ باید تا ${jalali.format(this.max)} باشد.`;
    },

    setValue(iso) {
        if (iso === this.value) return;

        this.value = iso;
        this.$dispatch('change', iso);
    },

    onInputKeydown(event) {
        switch (event.key) {
            // Paeen: bere rooye charkh-e rooz (agar baste bood aval baz mishe).
            case 'ArrowDown':
                event.preventDefault();
                if (!this.open) this.show();
                this.$nextTick(() => this.focusDrum('day'));
                return;
            // Enter: baste → baz; baz → hamin ke hast ro negah dar va beband (form submit nashe).
            case 'Enter':
                event.preventDefault();
                this.open ? this.close() : this.show();
                return;
            case 'Escape':
                if (this.open) {
                    event.preventDefault();
                    this.cancel();
                }
                return;
            default:
                // Ragham (ba har khat), joda-konande, navigation va shortcut ha rad mishan; harf na.
                if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey
                    && !/[0-9۰-۹٠-٩\/\-]/.test(event.key)) {
                    event.preventDefault();
                }
        }
    },

    // ── Baz/baste shodan ───────────────────────────────────────────────────────────────

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        if (this.disabled || this.open) return;

        this.before = this.value;
        if (this.value === '') this.sel = this.clamp(jalali.today());

        // Zir-e box ja nist va bala hast → bala baz sho.
        const rect = this.$refs.input.getBoundingClientRect();
        const need = 320;
        this.openUp = rect.bottom + need > window.innerHeight && rect.top > need;

        this.open = true;
        this.$nextTick(() => {
            this.measure();
            this.scrollAll(false);
        });
    },

    close() {
        if (!this.open) return;

        this.open = false;
        this.dragging = null;
    },

    /** Escape: har chi tu picker avaz shode bargarde. */
    cancel() {
        const before = this.before;
        this.close();
        if (before !== this.value) this.applyValue(before);
        this.focusInput();
    },

    /** Enter ya dokme-ye ta'yid: harchi charkh ha migan hamun-e. */
    confirm() {
        this.commit(this.sel);
        this.close();
        this.focusInput();
    },

    // ── Charkh ha ──────────────────────────────────────────────────────────────────────

    /** Ertefa-e yek radif az DOM khoonde mishe, na hard-code (token ha faghat tu app.css-and). */
    measure() {
        const option = this.$root.querySelector('[data-drum-option]');
        this.itemHeight = option ? option.offsetHeight : 40;
    },

    drumEl(drum) {
        return this.$root.querySelector(`[data-drum="${drum}"]`);
    },

    options(drum) {
        if (drum === 'day') return Array.from({ length: jalali.daysInMonth(this.sel.jy, this.sel.jm) }, (_, i) => i + 1);
        if (drum === 'month') return Array.from({ length: 12 }, (_, i) => i + 1);

        return Array.from({ length: this.maxYear - this.minYear + 1 }, (_, i) => this.minYear + i);
    },

    label(drum, n) {
        if (drum === 'month') return MONTHS[n - 1];

        return jalali.toPersianDigits(n);
    },

    index(drum) {
        if (drum === 'day') return this.sel.jd - 1;
        if (drum === 'month') return this.sel.jm - 1;

        return this.sel.jy - this.minYear;
    },

    /** Yek option kharej az min/max-e? (namayesh-e kam-rang va aria-disabled.) */
    isOut(drum, n) {
        const probe = { ...this.sel };
        if (drum === 'day') probe.jd = n;
        if (drum === 'month') probe.jm = n;
        if (drum === 'year') probe.jy = n;
        probe.jd = Math.min(probe.jd, jalali.daysInMonth(probe.jy, probe.jm));

        return !this.inRange(probe);
    },

    optionId(drum, n) {
        return `${this.$id('jalali-date')}-${drum}-${n}`;
    },

    /** id-e option-e entekhab shode baraye aria-activedescendant. */
    activeId(drum) {
        return this.optionId(drum, this.options(drum)[this.index(drum)]);
    },

    /** Charkh ro be radif-e index mibare; `animate=false` baraye bar-e aval ke nabayad harekat dide beshe. */
    scrollTo(drum, index, animate = true) {
        const el = this.drumEl(drum);
        if (!el) return;

        const top = index * this.itemHeight;
        if (Math.abs(el.scrollTop - top) < 1) {
            delete this.scrollTargets[drum];
            return;
        }

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        this.scrollTargets[drum] = top;
        el.scrollTo({ top, behavior: animate && !reduced ? 'smooth' : 'auto' });
    },

    scrollAll(animate = true) {
        DRUMS.forEach((drum) => this.scrollTo(drum, this.index(drum), animate));
    },

    /** Index-e yek charkh ro set mikone, tarikh ro clamp mikone va commit mikone (iOS: entekhab zende-ast). */
    setIndex(drum, index, scroll = true) {
        const list = this.options(drum);
        const n = list[Math.max(0, Math.min(list.length - 1, index))];
        const next = { ...this.sel };

        if (drum === 'day') next.jd = n;
        if (drum === 'month') next.jm = n;
        if (drum === 'year') next.jy = n;

        const clamped = this.clamp(next);
        const changed = clamped.jy !== this.sel.jy || clamped.jm !== this.sel.jm || clamped.jd !== this.sel.jd;

        this.sel = clamped;
        this.text = jalali.format(clamped);
        this.error = '';
        this.setValue(jalali.toIso(clamped));

        if (scroll || changed) this.$nextTick(() => this.scrollAll());
    },

    step(drum, delta) {
        this.setIndex(drum, this.index(drum) + delta);
    },

    /** Scroll-e native (touch, trackpad, wheel): vaghti vaysad, nazdik-tarin radif entekhab mishe. */
    onScroll(drum) {
        if (this.dragging === drum) return;

        clearTimeout(this.scrollTimers[drum]);

        // Scroll-e barnameyi dar parvaz: event-haye miyane-rah ro nadide begir; vaghti be maghsad resid
        // (akharin event-e smooth scroll) hadaf pak mishe va settle-e adi edame mikone.
        const target = this.scrollTargets[drum];
        if (target !== undefined) {
            const el = this.drumEl(drum);
            if (el && Math.abs(el.scrollTop - target) >= 1) return;
            delete this.scrollTargets[drum];
        }

        this.scrollTimers[drum] = setTimeout(() => this.settle(drum), 90);
    },

    /** Harekat-e khod-e karbar (wheel/touch) scroll-e barnameyi ro ghat' mikone; hadaf digar mo'tabar nist. */
    onUserScroll(drum) {
        delete this.scrollTargets[drum];
    },

    settle(drum) {
        const el = this.drumEl(drum);
        if (!el || !this.itemHeight) return;

        const index = Math.round(el.scrollTop / this.itemHeight);
        if (index !== this.index(drum)) {
            this.setIndex(drum, index, false);
        } else {
            this.scrollTo(drum, index);
        }
    },

    // ── Drag ba mouse (touch ro khod-e browser scroll mikone) ───────────────────────────

    onPointerDown(drum, event) {
        if (event.pointerType === 'touch') return;

        const el = this.drumEl(drum);
        this.dragging = drum;
        // Drag ba mouse ham harekat-e karbar-e: scroll-e barnameyi-ye dar parvaz digar mo'tabar nist.
        delete this.scrollTargets[drum];
        this.drag = { startY: event.clientY, startTop: el.scrollTop, lastY: event.clientY, lastT: performance.now(), velocity: 0 };
        el.setPointerCapture(event.pointerId);
    },

    onPointerMove(drum, event) {
        if (this.dragging !== drum) return;

        const el = this.drumEl(drum);
        const now = performance.now();
        const dt = now - this.drag.lastT;
        if (dt > 0) this.drag.velocity = (event.clientY - this.drag.lastY) / dt;
        this.drag.lastY = event.clientY;
        this.drag.lastT = now;

        el.scrollTop = this.drag.startTop - (event.clientY - this.drag.startY);
    },

    onPointerUp(drum, event) {
        if (this.dragging !== drum) return;

        const el = this.drumEl(drum);
        const moved = Math.abs(event.clientY - this.drag.startY) > 3;
        this.dragging = null;
        el.releasePointerCapture?.(event.pointerId);

        if (!moved) {
            // Click-e sade rooye yek radif: hamun radif entekhab mishe.
            const option = event.target.closest?.('[data-drum-option]');
            if (option) this.setIndex(drum, Number(option.dataset.index));
            return;
        }

        // Momentum: sor'at-e lahze-ye raha kardan chand radif jolotar mibare, ba'd snap.
        const momentum = -this.drag.velocity * 120;
        this.setIndex(drum, Math.round((el.scrollTop + momentum) / this.itemHeight));
    },

    // ── Keyboard rooye charkh ha ───────────────────────────────────────────────────────

    focusDrum(drum) {
        const el = this.drumEl(drum);
        if (!el || !this.open) return;

        // x-transition display ro yek frame dirtar set mikone; focus rooye display:none bi-asar-e.
        if (el.getClientRects().length === 0) {
            requestAnimationFrame(() => this.focusDrum(drum));
            return;
        }

        this.focusedDrum = drum;
        el.focus();
    },

    onDrumKeydown(drum, event) {
        const at = DRUMS.indexOf(drum);
        const bigStep = drum === 'year' ? 10 : 5;

        switch (event.key) {
            case 'ArrowUp': event.preventDefault(); this.step(drum, -1); return;
            case 'ArrowDown': event.preventDefault(); this.step(drum, 1); return;
            case 'PageUp': event.preventDefault(); this.step(drum, -bigStep); return;
            case 'PageDown': event.preventDefault(); this.step(drum, bigStep); return;
            case 'Home': event.preventDefault(); this.setIndex(drum, 0); return;
            case 'End': event.preventDefault(); this.setIndex(drum, this.options(drum).length - 1); return;
            // RTL: rast yani charkh-e ghabli tu DOM (rooz samt-e rast-e), chap yani ba'di.
            case 'ArrowRight': event.preventDefault(); this.focusDrum(DRUMS[Math.max(0, at - 1)]); return;
            case 'ArrowLeft': event.preventDefault(); this.focusDrum(DRUMS[Math.min(DRUMS.length - 1, at + 1)]); return;
            case 'Enter': event.preventDefault(); this.confirm(); return;
            case 'Escape': event.preventDefault(); this.cancel(); return;
            case 'Tab': this.close(); return;
        }
    },

    /** Matn-e live region: `۴ مهر ۱۴۰۵`. */
    announcement() {
        return this.value ? jalali.formatLong(this.sel) : 'تاریخی انتخاب نشده است';
    },
});
