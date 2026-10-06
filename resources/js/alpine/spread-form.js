/**
 * Form-e spread (P3-07): preview-e zende-ye SERVER-side.
 *
 * Hich mohasebe-ye gheymat tu JS nist — har taghir ba debounce be endpoint-e
 * preview mire ke az HAMUN `SpreadPricer`-e board estefade mikone (PLAN:
 * copy-e JS saket vagera mishe). In faghat transport + format-e namayesh-e.
 */
export default ({ previewUrl, rows = [] }) => ({
    rows: rows.map((row) => ({ ...row, busy: false })),
    timers: {},

    /** Taghir-e har field-e yek radif -> preview-e un radif ba 400ms debounce. */
    schedule(index) {
        clearTimeout(this.timers[index]);
        this.timers[index] = setTimeout(() => this.refresh(index), 400);
    },

    async refresh(index) {
        const row = this.rows[index];

        // Wildcard gheymat-e paye nadare; radif-e bi-gheymat ham preview nadare.
        if (!row || row.symbol === '*' || row.raw === null) return;

        row.busy = true;

        const response = await window.panelRequest(previewUrl, {
            body: {
                symbol: row.symbol,
                buy_mode: row.buy_mode,
                buy_value: Number(row.buy_value) || 0,
                sell_mode: row.sell_mode,
                sell_value: Number(row.sell_value) || 0,
            },
            // Khata-ye preview toast nemikhad — faghat preview-e kohne mimune.
            toast: false,
        }).catch(() => null);

        row.busy = false;

        const preview = response?.ok ? (response.data?.preview ?? null) : null;

        if (preview) {
            row.raw = preview.raw;
            row.buy = preview.buy;
            row.sell = preview.sell;
        }
    },

    /** Namayesh ba divisor-e namad (rial -> toman) va ragham-e Latin. */
    fmt(row, value) {
        if (value === null || value === undefined || row.divisor === null) return '—';

        const major = value / (row.divisor || 1);

        return major.toLocaleString('en-US', {
            minimumFractionDigits: row.precision ?? 0,
            maximumFractionDigits: row.precision ?? 0,
        });
    },
});
