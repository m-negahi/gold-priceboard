/**
 * Tab-haye panel (`x-panel.tabs` + `x-panel.tab-panel`) — WAI-ARIA.
 *
 * Arrow ha RTL-aware-and: tu safhe-ye RTL «chap» yani tab-e BA'DI. Home/End
 * aval/akhar. `sync` key-e tab-e fa'al ro tu hash-e URL negah midare ta
 * reload/share hamun tab ro baz kone.
 */
export default ({ tabs = [], initial = null, sync = false } = {}) => ({
    tabs,
    active: null,
    /** Id-e yekta-ye in instance — blade `x-id` nadare, pas khodemun misazim. */
    uid: 'tabs-' + Math.random().toString(36).slice(2, 8),

    init() {
        const fromHash = sync ? window.location.hash.replace(/^#/, '') : '';

        this.active = this.tabs.includes(fromHash)
            ? fromHash
            : (initial !== null && this.tabs.includes(String(initial)) ? String(initial) : this.tabs[0] ?? null);
    },

    select(key) {
        if (!this.tabs.includes(key)) return;

        this.active = key;

        if (sync) {
            // `replaceState` na assignment: avaz kardan-e tab nabayad
            // history-ye browser ro por kone.
            try {
                history.replaceState(null, '', '#' + key);
            } catch {
                // Sandbox/iframe — hash sync az dast mire, tab kar mikone.
            }
        }

        // Focus rooye tab-e fa'al bemune (keyboard navigation).
        this.$nextTick(() => {
            this.$root.querySelector(`[data-tab="${key}"]`)?.focus();
        });
    },

    onKeydown(event) {
        const index = this.tabs.indexOf(this.active);

        if (index === -1) return;

        // RTL: ArrowLeft = jolo (tab-e ba'di), ArrowRight = aghab.
        const next = {
            ArrowLeft: index + 1,
            ArrowRight: index - 1,
            Home: 0,
            End: this.tabs.length - 1,
        }[event.key];

        if (next === undefined) return;

        event.preventDefault();
        this.select(this.tabs[Math.max(0, Math.min(this.tabs.length - 1, next))]);
    },

    tabId(key) {
        return `${this.uid}-tab-${key}`;
    },

    panelId(key) {
        return `${this.uid}-panel-${key}`;
    },
});
