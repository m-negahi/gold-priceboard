/**
 * Form-e yek abzar (`/tools/{slug}`, `x-data="toolForm(...)"` roo-ye `<main>`). Gheymat-e paye hich
 * vaght dar mahasebe az client nemiad: har «محاسبه» yek `POST .../compute` mizane va faghat
 * natije-ye moshtagh migire (ADR-0051). Ghatar-e «قیمت پایه» ke bala-ye form neshun dade mishe
 * faghat ettela'i-e va az hamun kanal-e ramz-shode-ye Board por mishe ke ticker/tablo estefade
 * mikonand (`board-channel.js` → `subscribe`/`writeQuote`, `data-channel-map` rooye `<main>`).
 *
 * Voroodi-ha tu query-string-e URL mirand ta link shareable bashe (`syncQuery`) va moghe'-e load
 * dobare az roosh khunde mishan (`restoreFromQuery`) — bedoone in card-e «کپی» faghat yek adad ro
 * migereft na tanzimat-esh ro.
 */
import { toAscii, format } from '../lib/digits.js';
import { subscribe, writeQuote } from '../lib/board-channel.js';

/**
 * @param {{ slug: string, inputs: Array<Record<string, unknown>>, computeUrl: string, csrf: string, initial?: Record<string, string>, syncUrl?: boolean }} config
 * `initial` meghdar-haye pishfarz ro override mikone (masalan block-e «ابزار این نماد» ke ayar-e
 * instrument-e mizban ro az ghabl por mikone); `syncUrl: false` baraye hamun block (query-string-e
 * safhe-ye mizban male-ye in form nist).
 */
export default ({ slug, inputs, computeUrl, csrf, initial = {}, syncUrl = true }) => ({
    slug,
    inputs,
    computeUrl,
    csrf,
    syncUrl,

    values: {},
    result: null,
    breakdown: [],
    unit: 'rial',
    asOf: null,
    isStale: false,
    busy: false,
    copied: false,
    errors: {},

    init() {
        for (const input of this.inputs) this.values[input.key] = String(input.default ?? '');
        for (const [key, value] of Object.entries(initial)) this.values[key] = String(value);
        if (this.syncUrl) this.restoreFromQuery();
        this.compute();

        const mapCipher = this.$el.dataset.channelMap;

        if (mapCipher) {
            subscribe({
                mapCipher,
                apply: (payload, map) => {
                    for (const quote of payload.quotes ?? []) writeQuote(map, quote);
                },
            });
        }
    },

    restoreFromQuery() {
        const params = new URLSearchParams(window.location.search);

        for (const input of this.inputs) {
            const value = params.get(input.key);

            if (value !== null) this.values[input.key] = value;
        }
    },

    syncQuery() {
        if (!this.syncUrl) return;

        const params = new URLSearchParams();

        for (const input of this.inputs) {
            const value = this.values[input.key];

            if (value !== undefined && value !== '') params.set(input.key, value);
        }

        const query = params.toString();
        const url = query ? `${window.location.pathname}?${query}` : window.location.pathname;

        window.history.replaceState({}, '', url);
    },

    directionInput() {
        return this.inputs.find((input) => input.key === 'direction') ?? null;
    },

    canSwap() {
        const direction = this.directionInput();

        return direction !== null && Object.keys(direction.options ?? {}).length === 2;
    },

    swap() {
        const direction = this.directionInput();

        if (!direction) return;

        const keys = Object.keys(direction.options ?? {});
        const other = keys.find((key) => key !== this.values.direction) ?? keys[0];

        this.values.direction = other;
        this.compute();
    },

    async compute() {
        this.busy = true;
        this.errors = {};

        /** @type {Record<string, string>} */
        const body = {};

        for (const input of this.inputs) {
            const raw = this.values[input.key] ?? '';

            body[input.key] = input.type === 'number' ? toAscii(raw) : String(raw);
        }

        try {
            const response = await fetch(this.computeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                this.errors = data.errors ?? {};
                this.result = null;

                return;
            }

            if (response.status === 429) {
                this.errors = { _global: ['درخواست‌های زیاد؛ کمی صبر کنید و دوباره تلاش کنید.'] };

                return;
            }

            if (!response.ok) {
                this.errors = { _global: [typeof data.message === 'string' && data.message !== '' ? data.message : 'مشکلی پیش آمد؛ دوباره تلاش کنید.'] };

                return;
            }

            this.result = data.result;
            this.breakdown = Array.isArray(data.breakdown) ? data.breakdown : [];
            this.unit = data.unit ?? 'rial';
            this.asOf = data.as_of ?? null;
            this.isStale = Boolean(data.is_stale);
            this.syncQuery();
        } catch {
            this.errors = { _global: ['اتصال برقرار نشد؛ اینترنت خود را بررسی کنید.'] };
        } finally {
            this.busy = false;
        }
    },

    async copy() {
        if (this.result === null) return;

        try {
            await navigator.clipboard.writeText(this.formatted(this.result, this.unit));
            this.copied = true;
            setTimeout(() => (this.copied = false), 1500);
        } catch {
            // Clipboard-e mojaz nabashe saket rad mishe — dokme faghat yek convenience-e.
        }
    },

    /** Adad-e format-shode-ye fa-IR (argham-e farsi khodkar); `null` → «—». */
    formatted(value, unit) {
        if (value === null || value === undefined || value === '') return '—';

        const digits = unit === 'percent' ? 2 : unit === 'rial' ? 0 : 4;
        const text = format(value, { maximumFractionDigits: digits });

        return unit === 'percent' ? text + '٪' : text;
    },

    unitLabel(unit) {
        return { rial: 'ریال', gram: 'گرم', ounce: 'انس', mesghal: 'مثقال', usd: 'دلار', percent: 'درصد' }[unit] ?? '';
    },

    /** Rial → Toman (تقسیم بر ده، بدون اعشار) baraye radif-e dovom-e kart-e natije. */
    toman(value) {
        if (value === null || value === undefined) return '—';

        return format(Math.trunc(Number(value) / 10), { maximumFractionDigits: 0 });
    },
});
