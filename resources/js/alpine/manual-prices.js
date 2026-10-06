/**
 * Safhe ye gheymat e dasti (Phase 4/5): save/seed/remove ba AJAX — HICH
 * reload/scroll (khaste ye sahebe proje). panelRequest gharardad e toast ro
 * modiriat mikone؛ inja faghat rows va banner e alarm ro be-rooz mikonim.
 */
export default ({ routes, rows, effective }) => ({
    rows,
    alarm: effective.alarm,
    fallbackCount: effective.fallbackCount,
    seedProvider: '',
    busy: {},

    async save(symbol) {
        const row = this.rows.find((r) => r.symbol === symbol);
        if (!row || row.display === null || row.display === '') return;

        this.busy[symbol] = true;
        const res = await window.panelRequest(routes.update, { body: { symbol, value: row.display } });
        this.busy[symbol] = false;

        if (res.ok) this.apply(res.data);
    },

    async seed() {
        if (!this.seedProvider) return;

        this.busy.seed = true;
        const res = await window.panelRequest(routes.seed, { body: { provider: this.seedProvider } });
        this.busy.seed = false;

        if (res.ok) this.apply(res.data);
    },

    async remove(symbol) {
        this.busy[symbol] = true;
        const res = await window.panelRequest(routes.remove, { body: { symbol } });
        this.busy[symbol] = false;

        if (res.ok) this.apply(res.data);
    },

    /** Rows + banner ro ba pasokh e server jaygozin mikone (bedun e reload). */
    apply(data) {
        if (Array.isArray(data.rows)) this.rows = data.rows;
        if (data.effective) {
            this.alarm = data.effective.alarm;
            this.fallbackCount = data.effective.fallbackCount;
        }
    },

    /** Zaman e khana baraye namayesh. */
    when(iso) {
        if (!iso) return 'هرگز';
        try {
            return new Date(iso).toLocaleString('en-US', { timeZone: 'Asia/Tehran', hour12: false, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        } catch {
            return iso;
        }
    },
});
