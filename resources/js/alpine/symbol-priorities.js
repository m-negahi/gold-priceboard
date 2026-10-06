/**
 * Safhe ye «اولویت منابع» (P5-07): virayesh e zanjire ye har symbol ba
 * bala/paeen/hazf/ezafe — save ba AJAX، hich reload/scroll (hamun olgu ye
 * safhe ye gheymat e dasti). `manual` hichvaght tu editor nist: hamishe
 * akhar-e va server ham radesh mikone.
 */
const MANUAL = 'manual';

export default ({ route, rows, names }) => ({
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
            this.cancel();
        }
    },

    reset(symbol) {
        return this.save(symbol, []);
    },
});
