/**
 * Yek darkhast-e JSON az panel be yek controller-e dakheli (ADR-0020), ba gharardad-e toast:
 *
 * - 2xx: age javab `toast: { kind, title, message }` dare hamun neshun dade mishe; vagarna
 *   `message` be onvan-e toast-e movafagh.
 * - 422: avalin khata-ye validation be onvan-e toast-e khata; `errors` ham bar migarde ta form
 *   zir-e har field neshun bede.
 * - 419: session ya token-e CSRF kohne-ast; safhe reload mishe, hich chiz neshun dade nemishe.
 * - 401/403/404/429/5xx va ghat'i-e shabake: toast-e khata ba message-e server ya yek matn-e amn.
 *
 * Bar migarde: `{ ok, status, data, errors }` — hich vaght throw nemikone, ta caller faghat
 * `if (!ok) return` benevise. Tanha estesna `AbortError`-e: caller-i ke `signal` dade khodesh
 * mikhad bedoone ke darkhast-esh laghv shode, na inke yek khata-ye shabake bebine.
 *
 * `body` ya JSON-e (object → `JSON.stringify` + Content-Type) ya `FormData` (multipart; browser
 * khodesh boundary ro tu Content-Type mizare, pas hich header-i set nemishe).
 *
 * @param {string} url
 * @param {{ method?: string, body?: unknown, toast?: boolean, headers?: Record<string, string>, signal?: AbortSignal, cache?: RequestCache }} options
 */
export async function panelRequest(url, { method = 'POST', body = undefined, toast = true, headers = {}, signal = undefined, cache = undefined } = {}) {
    const toasts = () => window.Alpine?.store('toasts');
    const multipart = typeof FormData !== 'undefined' && body instanceof FormData;
    let response;

    try {
        response = await fetch(url, {
            method: method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                ...(body !== undefined && !multipart ? { 'Content-Type': 'application/json' } : {}),
                ...headers,
            },
            body: body === undefined ? undefined : multipart ? body : JSON.stringify(body),
            signal,
            cache,
        });
    } catch (error) {
        if (error?.name === 'AbortError') throw error;

        if (toast) toasts()?.error('اتصال برقرار نشد', 'اینترنت خود را بررسی کنید و دوباره تلاش کنید.');

        return { ok: false, status: 0, data: {}, errors: {} };
    }

    if (response.status === 419) {
        window.location.reload();

        return { ok: false, status: 419, data: {}, errors: {} };
    }

    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
    const errors = data && typeof data.errors === 'object' && data.errors !== null ? data.errors : {};

    if (toast) notify(toasts(), response.ok, response.status, data, errors);

    return { ok: response.ok, status: response.status, data, errors };
}

/**
 * Khandan-e JSON az yek endpoint-e panel ba hamun header ha va hamun raftar-e 419. Toast pish-farz
 * khamoosh-e: safhe-hayi ke poll mikonan (payesh, mizkar, datatable) khata ro khodeshun neshun
 * midan va backoff daran; yek toast be azaye har poll-e na-movafagh mozahem-e.
 *
 * @param {string} url
 * @param {{ toast?: boolean, headers?: Record<string, string>, signal?: AbortSignal, cache?: RequestCache }} options
 */
export function panelGet(url, { toast = false, ...options } = {}) {
    return panelRequest(url, { ...options, method: 'GET', toast });
}

/**
 * Miyanbor baraye code-i ke faghat yek payam dare (datatable, bulk action ha): `toast('ذخیره شد', 'ok')`.
 * Tone-haye UI (ok / warn / info / danger) be kind-e toast map mishan.
 *
 * @param {string} message
 * @param {'ok'|'success'|'warn'|'warning'|'info'|'danger'|'error'} tone
 */
export function toast(message, tone = 'info') {
    const kind = { ok: 'success', success: 'success', warn: 'warning', warning: 'warning', danger: 'error', error: 'error' }[tone] ?? 'info';

    window.Alpine?.store('toasts')?.push({ kind, title: message });
}

function notify(toasts, ok, status, data, errors) {
    if (!toasts) return;

    if (data?.toast && typeof data.toast === 'object') {
        toasts.push(data.toast);

        return;
    }

    if (ok) {
        if (data?.message) toasts.success(data.message);

        return;
    }

    const message = typeof data?.message === 'string' && data.message !== '' ? data.message : null;

    switch (status) {
        case 422:
            toasts.error(firstError(errors) ?? message ?? 'ورودی‌ها را بررسی کنید.');
            return;
        case 401:
            toasts.error('نشست شما به پایان رسیده است', 'دوباره وارد شوید.');
            return;
        case 403:
            toasts.error('دسترسی ندارید', message ?? 'اجازه‌ی انجام این کار را ندارید.');
            return;
        case 404:
            toasts.error('پیدا نشد', message ?? 'موردی که خواستید وجود ندارد.');
            return;
        case 429:
            toasts.warning('درخواست‌های زیاد', message ?? 'کمی صبر کنید و دوباره تلاش کنید.');
            return;
        default:
            toasts.error(status >= 500 ? 'خطای سرور' : 'خطا', message ?? 'مشکلی پیش آمد. دوباره تلاش کنید.');
    }
}

function firstError(errors) {
    const first = Object.values(errors)[0];

    return Array.isArray(first) ? first[0] : typeof first === 'string' ? first : null;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
