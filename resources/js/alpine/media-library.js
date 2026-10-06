import { panelGet } from '../lib/request.js';

/** Yek modal e moshtarak baraye picker ha؛ javab e request e kohne state ro avaz nemikone. */
export default ({ url }) => ({
    url,
    libraryOpen: false,
    tab: 'library',
    q: '',
    rows: [],
    meta: { total: 0, page: 1, last_page: 1 },
    selected: null,
    loading: false,
    error: null,
    onPick: null,
    controller: null,
    debounce: null,

    init() {
        this.$watch('q', () => {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.load(1), 300);
        });
    },
    destroy() {
        clearTimeout(this.debounce);
        this.controller?.abort();
    },
    openFor({ onPick }) {
        this.onPick = onPick;
        this.selected = null;
        this.tab = 'library';
        this.libraryOpen = true;
        this.load(1);
    },
    closeLibrary() {
        this.libraryOpen = false;
        this.onPick = null;
        this.controller?.abort();
    },
    async load(page = 1) {
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        this.loading = true;
        this.error = null;
        this.selected = null;
        try {
            const params = new URLSearchParams({ q: this.q, page: String(page), per_page: '24' });
            const { ok, data } = await panelGet(`${this.url}?${params}`, { signal: controller.signal });
            if (controller.signal.aborted) return;
            if (!ok) throw new Error('دریافت تصویرها انجام نشد. دوباره تلاش کنید.');
            this.rows = data.data;
            this.meta = data.meta;
        } catch (error) {
            if (error.name !== 'AbortError') this.error = error.message;
        } finally {
            if (!controller.signal.aborted) this.loading = false;
        }
    },
    select(row) { this.selected = row; },
    confirm() { if (this.selected) this.pick(this.selected); },
    pick(row) {
        this.onPick?.(row);
        this.closeLibrary();
    },
    uploaded(row) { this.pick(row); },
    fa(value) { return new Intl.NumberFormat('fa-IR-u-nu-latn').format(value ?? 0); },
});
