/**
 * Menu-saz (`/panel/menus/{key}`). Model-e data amdan **flat**-e: yek araye ke har radif `depth`
 * dare, na parent_id. Ba in shekl «keshidan-e yek shakhe» faghat splice-e yek bolok-e payapey az
 * hamun araye-ast va «taghir-e valed» faghat kam/ziad kardan-e depth — pas na valed-e yatim momken-e
 * na halghe. Server (SaveMenuTreeAction) derakht-e tu-dar-tu migire; `toTree()` az ruye depth
 * misazad-esh va saghf-e omgh ro server dobare check mikone (422).
 *
 * Hich ghanoon-e domain inja nist (ADR-0020): URL-e nahayi-ye har item ro server (pish-namayesh)
 * mide; `hrefOf` faghat yek eshare-ye khandani baraye radif-e.
 */
import { panelRequest } from '../lib/request';

const PERSIAN = new Intl.NumberFormat('fa-IR');
let seq = 0;

export default ({ urls, items = [], maxDepth = 1, icons = [] }) => ({
    urls,
    maxDepth,
    icons,
    items: [],
    clean: '[]',
    selected: null,
    saving: false,

    // ── Keshidan ──
    drag: null,
    dragLen: 0,
    dragSnapshot: null,

    // ── Picker-e marja' ──
    picker: { open: false, q: '', results: [], searched: false, timer: null },

    // ── Pish-namayesh ──
    previewHtml: '',
    previewing: false,
    previewTimer: null,

    init() {
        this.items = this.flatten(items, 0);
        this.markClean();
        this.refreshPreview();

        this.$watch('items', () => this.schedulePreview());

        this.onBeforeUnload = (event) => {
            if (!this.dirty) return;

            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', this.onBeforeUnload);
    },

    destroy() {
        window.removeEventListener('beforeunload', this.onBeforeUnload);
        clearTimeout(this.previewTimer);
    },

    fa(n) {
        return PERSIAN.format(n);
    },

    // ═══ Vaz'iat-e zakhire ═══
    markClean() {
        this.clean = JSON.stringify(this.toTree());
    },

    get dirty() {
        return this.clean !== JSON.stringify(this.toTree());
    },

    get current() {
        return this.selected === null ? null : (this.items[this.selected] ?? null);
    },

    // ═══ Tabdil-e shekl ═══
    flatten(nodes, depth) {
        const out = [];

        for (const node of nodes) {
            const { children = [], ...rest } = node;
            out.push({ ...this.blank(depth), ...rest, depth, uid: `u${++seq}` });
            out.push(...this.flatten(children, depth + 1));
        }

        return out;
    },

    /** Az ruye depth derakht-e tu-dar-tu misaze — hamun shekli ke server migire. */
    toTree() {
        const root = [];
        const stack = [];

        for (const item of this.items) {
            const node = {
                id: item.id ?? null,
                label: item.label,
                description: item.description || null,
                icon: item.icon || null,
                type: item.type,
                reference: item.type === 'url' ? null : (item.reference || null),
                url: item.type === 'url' ? (item.url || null) : null,
                target: item.target,
                is_active: !!item.is_active,
                children: [],
            };
            const depth = Math.min(item.depth, stack.length);
            stack.length = depth;
            (depth === 0 ? root : stack[depth - 1].children).push(node);
            stack.push(node);
        }

        return root;
    },

    blank(depth) {
        return { id: null, uid: `u${++seq}`, depth, label: '', description: '', icon: '', type: 'url', reference: '', url: '', target: '_self', is_active: true, href: '#' };
    },

    hrefOf(item) {
        if (item.type === 'url') return item.url || '#';
        if (!item.reference) return '#';

        return { page: `/${item.reference}`, article: `/articles/${item.reference}`, instrument: `/price/${item.reference}`, route: item.href || '#' }[item.type] ?? '#';
    },

    // ═══ Derakht rooye araye-ye flat ═══
    subtreeEnd(i) {
        const d = this.items[i].depth;
        let j = i + 1;

        while (j < this.items.length && this.items[j].depth > d) j++;

        return j;
    },

    prevSibling(i) {
        const d = this.items[i].depth;

        for (let j = i - 1; j >= 0; j--) {
            if (this.items[j].depth === d) return j;
            if (this.items[j].depth < d) return null;
        }

        return null;
    },

    nextSibling(i) {
        const d = this.items[i].depth;

        for (let j = this.subtreeEnd(i); j < this.items.length; j++) {
            if (this.items[j].depth === d) return j;
            if (this.items[j].depth < d) return null;
        }

        return null;
    },

    deepestIn(i) {
        let deepest = this.items[i].depth;

        for (let j = i + 1; j < this.subtreeEnd(i); j++) deepest = Math.max(deepest, this.items[j].depth);

        return deepest;
    },

    inDragBlock(i) {
        return this.drag !== null && i >= this.drag && i < this.drag + this.dragLen;
    },

    // ═══ Entekhab / sakht / hazf ═══
    select(i) {
        this.selected = i;
        this.picker = { open: false, q: '', results: [], searched: false, timer: null };
    },

    /** `after === null` → yek rishe-ye jadid akhar-e list; vagarna zir-e un radif (akhar-e farzand-hash). */
    add(after) {
        if (after === null) {
            this.items.push(this.blank(0));
            this.select(this.items.length - 1);
        } else {
            const parent = this.items[after];

            if (parent.depth >= this.maxDepth) return;

            const at = this.subtreeEnd(after);
            this.items.splice(at, 0, this.blank(parent.depth + 1));
            this.select(at);
        }

        this.$nextTick(() => document.getElementById('f-label')?.focus());
    },

    async remove(i) {
        if (i === null || !this.items[i]) return;

        const item = this.items[i];
        const count = this.subtreeEnd(i) - i;
        const yes = await window.Alpine.store('confirm').ask({
            title: `«${item.label || 'بدون عنوان'}» حذف شود؟`,
            body: count > 1 ? `${this.fa(count - 1)} زیرگزینه هم با آن حذف می‌شود. تا ذخیره نکنید در سایت اثری ندارد.` : 'تا ذخیره نکنید در سایت اثری ندارد.',
            confirm: 'حذف',
            danger: true,
        });

        if (!yes) return;

        this.items.splice(i, count);
        this.selected = null;
    },

    // ═══ Tu-raftegi / birun-zadegi ═══
    canIndent(i) {
        if (this.prevSibling(i) === null) return false;

        return this.deepestIn(i) + 1 <= this.maxDepth;
    },

    canOutdent(i) {
        return this.items[i].depth > 0;
    },

    indent(i) {
        if (!this.canIndent(i)) return;

        const end = this.subtreeEnd(i);
        for (let j = i; j < end; j++) this.items[j].depth++;
    },

    /** Birun umadan: radif ba zir-derakht-esh ba'd az zir-derakht-e valed-esh mishine ta tartib ma'ni-dar bemune. */
    outdent(i) {
        if (!this.canOutdent(i)) return;

        const end = this.subtreeEnd(i);
        const block = this.items.slice(i, end);
        for (const row of block) row.depth--;

        // Valed: nazdik-tarin radif-e bala ba depth-e kamtar.
        let p = i - 1;
        while (p >= 0 && this.items[p].depth >= block[0].depth + 1) p--;

        const parentEnd = this.subtreeEnd(p);
        this.items.splice(i, block.length);
        const target = parentEnd - block.length;
        this.items.splice(target, 0, ...block);
        this.selected = target;
    },

    moveUp(i) {
        const p = this.prevSibling(i);
        if (p === null) return;

        const block = this.items.splice(i, this.subtreeEnd(i) - i);
        this.items.splice(p, 0, ...block);
        this.selected = p;
    },

    moveDown(i) {
        const n = this.nextSibling(i);
        if (n === null) return;

        const nEnd = this.subtreeEnd(n);
        const block = this.items.splice(i, this.subtreeEnd(i) - i);
        const target = nEnd - block.length;
        this.items.splice(target, 0, ...block);
        this.selected = target;
    },

    /** Alt+↑/↓ jabejayi, Alt+← zir-majmooe, Alt+→ birun; faghat vaghti focus tu yek input nist. */
    onKeydown(event) {
        if (this.selected === null || !event.altKey) return;
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(event.target?.tagName ?? '')) return;

        const map = { ArrowUp: 'moveUp', ArrowDown: 'moveDown', ArrowLeft: 'indent', ArrowRight: 'outdent' };
        const action = map[event.key];

        if (!action) return;

        event.preventDefault();
        const i = this.selected;
        this[action](i);
        this.$nextTick(() => this.$root.querySelectorAll('[data-menu-item]')[this.selected]?.focus());
    },

    // ═══ Keshidan (HTML5 DnD) ═══
    dragStart(i, event) {
        this.drag = i;
        this.dragLen = this.subtreeEnd(i) - i;
        this.dragSnapshot = JSON.stringify(this.items);
        event.dataTransfer?.setData('text/plain', String(i));
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    },

    dragOver(t) {
        if (this.drag === null || this.inDragBlock(t)) return;

        const block = this.items.splice(this.drag, this.dragLen);
        const target = t > this.drag ? t - this.dragLen + 1 : t;
        this.items.splice(target, 0, ...block);
        this.drag = target;
    },

    drop() {
        if (this.drag === null) return;

        const i = this.drag;
        const head = this.items[i];
        const prev = i > 0 ? this.items[i - 1] : null;
        const inner = this.deepestIn(i) - head.depth;
        // Omgh-e mojaz: hadeaksar yek pele az radif-e ghabli, va saghf-e menu ba zir-derakht.
        let d = Math.min(head.depth, prev ? prev.depth + 1 : 0);
        while (d > 0 && d + inner > this.maxDepth) d--;

        if (d + inner > this.maxDepth) {
            this.items = JSON.parse(this.dragSnapshot);
        } else {
            const delta = d - head.depth;
            const end = i + this.dragLen;
            for (let j = i; j < end; j++) this.items[j].depth += delta;
            this.selected = i;
        }

        this.drag = null;
        this.dragLen = 0;
        this.dragSnapshot = null;
    },

    // ═══ Picker-e marja' ═══
    onTypeChange() {
        if (!this.current) return;

        this.current.reference = '';
        this.picker = { open: false, q: '', results: [], searched: false, timer: null };
    },

    openPicker() {
        this.picker.open = true;
        if (!this.picker.searched) this.searchReferences();
    },

    closePickerLater(event) {
        if (event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) return;

        this.picker.open = false;
    },

    async searchReferences() {
        if (!this.current || this.current.type === 'url') return;

        const type = this.current.type;
        const q = this.picker.q;
        const url = `${this.urls.references}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`;
        const { ok, data } = await panelRequest(url, { method: 'GET', toast: false });

        // Javab-e yek jostoju-ye ghadimi-tar ro nadide begir.
        if (!this.current || this.current.type !== type || this.picker.q !== q) return;

        this.picker.results = ok ? data.data : [];
        this.picker.searched = true;
        this.picker.open = true;
    },

    pickReference(row) {
        if (!this.current) return;

        this.current.reference = row.reference;
        if (row.hint && row.exists !== false) this.current.href = row.hint;
        if (!this.current.label) this.current.label = row.label;
        this.picker.q = row.label;
        this.picker.open = false;
    },

    // ═══ Save + pish-namayesh ═══
    async save() {
        if (this.saving) return;

        this.saving = true;
        const { ok, data } = await panelRequest(this.urls.save, { method: 'PUT', body: { items: this.toTree() } });
        this.saving = false;

        if (!ok) return;

        // Server derakht-e zakhire-shode (ba id-haye jadid) ro pas mide; entekhab ba index hefz mishe.
        const selected = this.selected;
        this.items = this.flatten(data.data, 0);
        this.selected = selected !== null && selected < this.items.length ? selected : null;
        this.markClean();
    },

    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.refreshPreview(), 500);
    },

    async refreshPreview() {
        this.previewing = true;
        const { ok, data } = await panelRequest(this.urls.preview, { method: 'POST', body: { items: this.toTree() }, toast: false });
        this.previewing = false;

        if (ok) this.previewHtml = data.data.html;
    },
});
