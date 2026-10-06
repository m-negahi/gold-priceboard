/**
 * Datatable-e server-driven-e panel (`x-panel.datatable`).
 *
 * Hich manteghi inja nist joz render: search, sort, filter va safhe-bandi hame be endpoint-e JSON
 * miran va server tasmim migire (ADR-0020). Client state-e URL ro hamgam negah midare ta yek
 * reload ya yek link-e copy-shode hamun safhe ro bede, va ba'd az har taghir dobare az server
 * miporse — hich vaght khodesh radif ha ro sort ya filter nemikone.
 */

import { panelGet, panelRequest, toast } from '../lib/request.js';
import { format } from '../lib/digits.js';

export default (config) => ({
    endpoint: config.endpoint,
    columns: config.columns,
    filterDefs: config.filters ?? [],
    perPageOptions: config.perPageOptions ?? [10, 25, 50, 100],
    selectable: Boolean(config.selectable),
    bulkActions: config.bulkActions ?? [],
    rowActions: config.rowActions ?? [],
    rowKey: config.rowKey ?? 'id',
    syncUrl: config.syncUrl !== false,
    empty: config.empty ?? {},
    debounceMs: config.debounceMs ?? 350,

    // ── State-e request ─────────────────────────────────────────────────────
    q: '',
    sort: null,
    direction: 'asc',
    page: 1,
    perPage: config.perPage ?? 25,
    filters: {},

    // ── State-e javab ───────────────────────────────────────────────────────
    rows: [],
    meta: { total: 0, page: 1, per_page: config.perPage ?? 25, last_page: 1 },
    loading: true,
    loaded: false,
    error: null,
    selected: [],
    busy: false,

    controller: null,
    debounce: null,

    init() {
        this.filterDefs.forEach((f) => (this.filters[f.key] = ''));
        this.readUrl();

        this.$watch('q', () => this.reload({ debounce: true }));
        this.$watch('perPage', () => this.reload());
        this.$watch('filters', () => this.reload());

        this.load();
    },

    destroy() {
        this.controller?.abort();
        clearTimeout(this.debounce);
    },

    // ── Getter ha ───────────────────────────────────────────────────────────

    get isFiltered() {
        return this.q !== '' || Object.values(this.filters).some((v) => v !== '');
    },

    get showSkeleton() {
        return this.loading && !this.loaded;
    },

    get showEmpty() {
        return !this.loading && !this.error && this.rows.length === 0;
    },

    get allSelected() {
        return this.rows.length > 0 && this.rows.every((row) => this.selected.includes(row[this.rowKey]));
    },

    get someSelected() {
        return !this.allSelected && this.selected.length > 0;
    },

    /** Az … ta … az kol — baraye khat-e zir-e jadval. */
    get rangeText() {
        if (this.meta.total === 0) {
            return 'بدون ردیف';
        }

        const from = (this.meta.page - 1) * this.meta.per_page + 1;
        const to = Math.min(this.meta.page * this.meta.per_page, this.meta.total);

        return `نمایش ${this.fa(from)} تا ${this.fa(to)} از ${this.faGrouped(this.meta.total)} ردیف`;
    },

    /** Shomare-safhe ha ba se-noghte: 1 … 4 5 6 … 12. */
    get pages() {
        const last = this.meta.last_page;
        const current = this.meta.page;
        const wanted = new Set([1, last, current - 1, current, current + 1]);
        const list = [];
        let previous = 0;

        for (let n = 1; n <= last; n++) {
            if (!wanted.has(n)) {
                continue;
            }

            if (n - previous > 1) {
                list.push({ gap: true, key: `gap-${n}` });
            }

            list.push({ n, key: `p-${n}` });
            previous = n;
        }

        return list;
    },

    // ── Load ────────────────────────────────────────────────────────────────

    reload({ debounce = false } = {}) {
        this.page = 1;
        clearTimeout(this.debounce);

        if (debounce) {
            this.debounce = setTimeout(() => this.load(), this.debounceMs);
        } else {
            this.load();
        }
    },

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        this.error = null;
        this.writeUrl();

        try {
            // AbortError az panelGet rad mishe (catch-e paeen); 419 ro khodesh reload mikone.
            const { ok, status, data: body } = await panelGet(`${this.endpoint}?${this.params()}`, { signal: this.controller.signal });

            if (status === 419 || status === 401) {
                window.location.reload();

                return;
            }

            if (!ok) {
                throw new Error(this.firstMessage(body) ?? `خطای ${this.fa(status)} از سرور`);
            }

            this.rows = body.data ?? [];
            this.meta = body.meta ?? this.meta;
            this.sort = this.meta.sort ?? this.sort;
            this.direction = this.meta.direction ?? this.direction;
            this.page = this.meta.page;
            // Entekhab faghat rooye radif-hayi ke hanuz dide mishan ma'ni dare.
            this.selected = this.selected.filter((id) => this.rows.some((row) => row[this.rowKey] === id));
            this.loaded = true;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            this.error = error.message || 'اتصال برقرار نشد. اینترنت خود را بررسی کنید.';
        } finally {
            if (!this.controller?.signal.aborted) {
                this.loading = false;
            }
        }
    },

    params() {
        const params = new URLSearchParams();

        if (this.q !== '') params.set('q', this.q);
        if (this.sort) params.set('sort', this.sort);
        if (this.sort && this.direction === 'desc') params.set('direction', 'desc');
        if (this.page > 1) params.set('page', String(this.page));
        if (this.perPage !== (config.perPage ?? 25)) params.set('per_page', String(this.perPage));

        Object.entries(this.filters).forEach(([key, value]) => {
            if (value !== '') params.set(`filters[${key}]`, value);
        });

        return params;
    },

    // ── URL ─────────────────────────────────────────────────────────────────

    readUrl() {
        if (!this.syncUrl) {
            return;
        }

        const url = new URLSearchParams(window.location.search);
        this.q = url.get('q') ?? '';
        this.sort = url.get('sort') || null;
        this.direction = url.get('direction') === 'desc' ? 'desc' : 'asc';
        this.page = Math.max(1, Number.parseInt(url.get('page') ?? '1', 10) || 1);

        const perPage = Number.parseInt(url.get('per_page') ?? '', 10);
        if (this.perPageOptions.includes(perPage)) this.perPage = perPage;

        this.filterDefs.forEach((f) => {
            const value = url.get(`filters[${f.key}]`);
            if (value !== null && f.options.some((o) => String(o.value) === value)) this.filters[f.key] = value;
        });
    },

    writeUrl() {
        if (!this.syncUrl) {
            return;
        }

        const query = this.params().toString();
        const next = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;

        if (next !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
            window.history.replaceState(window.history.state, '', next);
        }
    },

    // ── Sort / safhe ────────────────────────────────────────────────────────

    sortKey(column) {
        return column.sort ?? column.key;
    },

    isSorted(column) {
        return column.sortable && this.sort === this.sortKey(column);
    },

    ariaSort(column) {
        if (!this.isSorted(column)) return column.sortable ? 'none' : null;

        return this.direction === 'asc' ? 'ascending' : 'descending';
    },

    toggleSort(column) {
        if (!column.sortable) {
            return;
        }

        const key = this.sortKey(column);
        this.direction = this.sort === key && this.direction === 'asc' ? 'desc' : 'asc';
        this.sort = key;
        this.reload();
    },

    goTo(n) {
        if (n < 1 || n > this.meta.last_page || n === this.page || this.loading) {
            return;
        }

        this.page = n;
        this.load();
    },

    clearFilters() {
        this.q = '';
        Object.keys(this.filters).forEach((key) => (this.filters[key] = ''));
    },

    // ── Entekhab va bulk ────────────────────────────────────────────────────

    toggleAll(checked) {
        const ids = this.rows.map((row) => row[this.rowKey]);
        this.selected = checked ? [...new Set([...this.selected, ...ids])] : this.selected.filter((id) => !ids.includes(id));
    },

    isSelected(row) {
        return this.selected.includes(row[this.rowKey]);
    },

    toggleRow(row) {
        const id = row[this.rowKey];
        this.selected = this.isSelected(row) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
    },

    async runBulk(action) {
        if (this.busy || this.selected.length === 0) {
            return;
        }

        // Ta'yid az dialog-e sarasari-ye panel (admin-panel.md §8), na window.confirm; `tone: 'danger'` dokme ro ghermez mikone.
        if (action.confirm && !(await this.$store.confirm.ask({ title: action.confirm, danger: action.tone === 'danger' }))) {
            return;
        }

        this.busy = true;

        try {
            // panelRequest khata ha ro khodesh toast mikone (422 / 403 / 5xx / ghat'i); movafaghiat payam-e ma ro dare.
            const { ok, data } = await panelRequest(action.url, {
                method: action.method ?? 'POST',
                body: { ids: this.selected, ...(action.payload ?? {}) },
            });

            if (!ok) {
                return;
            }

            const count = data?.data?.updated;
            toast(count === undefined ? action.done ?? 'انجام شد.' : `${this.fa(count)} ردیف به‌روز شد.`, 'ok');
            this.selected = [];
            await this.load();
        } finally {
            this.busy = false;
        }
    },

    // ── Render-e cell ───────────────────────────────────────────────────────

    /** Matn-e nahayi-ye yek cell; formatting-e adad/pool inja-st, na tu server. */
    cellText(row, column) {
        const value = row[column.key];

        if (value === null || value === undefined || value === '') {
            return column.empty ?? '—';
        }

        switch (column.type) {
            case 'badge':
                return this.badge(column, value).label;
            case 'date-jalali':
                // Server tarikh ro ba Common\Helpers\Jalali format karde; inja faghat num-e.
                return String(value);
            default:
                return column.num && typeof value === 'number' ? this.faGrouped(value) : String(value);
        }
    },

    badge(column, value) {
        return column.badges?.[String(value)] ?? { label: String(value), tone: 'muted' };
    },

    rowHref(template, row) {
        return template.replace(/\{(\w+)\}/g, (_, key) => encodeURIComponent(row[key] ?? ''));
    },

    firstMessage(body) {
        const errors = body?.errors ?? {};

        return Object.values(errors)[0]?.[0] ?? body?.message ?? body?.error?.message ?? null;
    },

    fa(n) {
        return format(n, { useGrouping: false });
    },

    faGrouped(n) {
        return format(n);
    },
});
