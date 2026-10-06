/**
 * Safhe ye «اولویت منابع» (P5-07): virayesh e zanjire ye har symbol ba
 * bala/paeen/hazf/ezafe — save ba AJAX، hich reload/scroll (hamun olgu ye
 * safhe ye gheymat e dasti). `manual` hichvaght tu editor nist: hamishe
 * akhar-e va server ham radesh mikone.
 */
const MANUAL = 'manual';

export default ({ route, rows, names, routes = {}, effective = {}, manualActive = false, autoNames = {} }) => ({
    routes,
    effective,
    manualActive,
    autoNames,
    savedRows: JSON.parse(JSON.stringify(rows)),
    preview: null,
    importToken: null,
    rows,
    names,
    /** Symbol-i ke alan dar hal e virayesh-e (faghat yeki ta UI sade bemune). */
    editing: null,
    /** Zanjire ye dar hal e virayesh (bedun e manual). */
    draft: [],
    busy: false,

    name(key) {
        return this.names[key] ?? key;
    },

    /** Provider haye fa'al ke tu draft nistan — baraye select e «افزودن». */
    get addable() {
        return Object.keys(this.names).filter((k) => k !== MANUAL && !this.draft.includes(k));
    },

    edit(symbol) {
        const row = this.rows.find((r) => r.symbol === symbol);
        if (!row) return;

        this.editing = symbol;
        // Az zanjire ye NAHAYI shoru' mishe (na faghat sefareshi) ta admin
        // daghighan hamun tartib e fe'li ro bebine va jabeja kone.
        this.draft = row.chain.filter((k) => k !== MANUAL);
    },

    cancel() {
        this.editing = null;
        this.draft = [];
    },

    move(index, delta) {
        const target = index + delta;
        if (target < 0 || target >= this.draft.length) return;

        const next = [...this.draft];
        [next[index], next[target]] = [next[target], next[index]];
        this.draft = next;
    },

    drop(index) {
        this.draft = this.draft.filter((_, i) => i !== index);
    },

    add(key) {
        if (key && !this.draft.includes(key)) this.draft = [...this.draft, key];
    },

    async save(symbol, providers = this.draft) {
        this.busy = true;
        const res = await window.panelRequest(route, { body: { symbol, providers } });
        this.busy = false;

        if (res.ok) {
            if (Array.isArray(res.data.rows)) this.rows = res.data.rows;
            this.savedRows = JSON.parse(JSON.stringify(this.rows));
            this.cancel();
        }
    },

    apply(data) {
        if (Array.isArray(data.unifiedRows)) this.rows = data.unifiedRows;
        else if (Array.isArray(data.rows)) this.rows = data.rows;
        if (data.effective) this.effective = data.effective;
        this.savedRows = JSON.parse(JSON.stringify(this.rows));
    },

    async savePrice(row) {
        if (this.busy) return;
        this.busy = true;
        const res = await window.panelRequest(this.routes.manual, { body: { symbol: row.symbol, value: row.display } });
        this.busy = false;
        if (res.ok) this.apply(res.data);
    },

    async savePolicy(row) {
        if (this.busy) return;
        this.busy = true;
        const res = await window.panelRequest(this.routes.settings, { body: {
            symbol: row.symbol, manualOnly: row.manualOnly,
            manualFallbackEnabled: row.manualFallbackEnabled, manualAutoSource: row.manualAutoSource,
        } });
        this.busy = false;
        if (res.ok) this.apply(res.data);
        else this.rows = JSON.parse(JSON.stringify(this.savedRows));
    },

    async removePrice(row) {
        if (this.busy) return;
        this.busy = true;
        const res = await window.panelRequest(this.routes.remove, { body: { symbol: row.symbol } });
        this.busy = false;
        if (res.ok) this.apply(res.data);
    },

    async seedPriority() {
        if (this.busy) return;
        this.busy = true;
        const res = await window.panelRequest(this.routes.seedPriority);
        this.busy = false;
        if (res.ok) this.apply(res.data);
    },

    async previewWorkbook(event) {
        const file = event.target.files?.[0];
        this.preview = null;
        this.importToken = null;
        if (!file || this.busy) return;
        const body = new FormData();
        body.append('workbook', file);
        this.busy = true;
        const res = await window.panelRequest(this.routes.preview, { body });
        this.busy = false;
        if (res.ok) {
            this.preview = res.data.preview;
            this.importToken = res.data.token;
        }
    },

    async confirmWorkbook() {
        if (!this.importToken || this.preview?.hasErrors || this.busy) return;
        this.busy = true;
        const res = await window.panelRequest(this.routes.confirm, { body: { token: this.importToken } });
        this.busy = false;
        if (res.ok) {
            this.apply(res.data);
            this.preview = null;
            this.importToken = null;
        }
    },

    reset(symbol) {
        return this.save(symbol, []);
    },
});
