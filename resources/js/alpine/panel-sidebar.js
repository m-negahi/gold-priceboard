/**
 * Sidebar-e panel: rail-e baz/baste + tooltip-e halat-e baste.
 *
 * `expanded` tu localStorage mimune chon `wire:navigate`/reload state-e
 * Alpine ro sefr mikone va sidebar-i ke har safhe az no baz beshe azar
 * dahande ast. Khundan/neveshtan dakhel-e try/catch: private window ya
 * storage-e block-shode nabayad panel ro beshkane.
 */
const STORAGE_KEY = 'pb:panel:sidebar';

export default () => ({
    expanded: true,
    /**
     * Drawer-e MOBILE (P4-19) — joda az `expanded`: rail-e desktop yek
     * tarjih-e paydar-e, drawer yek halat-e gozara. Ba event-e window
     * (`toggle-sidebar` az hamburger-e topbar) baz mishe chon topbar kharej
     * az scope-e in component-e. Persist NEMISHE: har navigate bayad baste
     * shoroo beshe.
     */
    open: false,
    /** Kelid-e item-i ke tooltip-esh alan bayad dide beshe (faghat rail-e baste). */
    hovered: null,

    /** Label-ha dide beshan? Desktop: rail-e baz. Mobile: drawer-e baz. */
    get wide() {
        return this.expanded || this.open;
    },

    init() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);

            if (saved !== null) this.expanded = saved === '1';
        } catch {
            // Storage dar dastres nist — default-e baz mimune.
        }
    },

    toggle() {
        this.expanded = !this.expanded;
        this.hovered = null;

        try {
            localStorage.setItem(STORAGE_KEY, this.expanded ? '1' : '0');
        } catch {
            // Saket: faghat tarjih-e in device az dast mire.
        }
    },

    show(key) {
        // Tooltip faghat tu rail-e BASTE ma'ni dare — halat-e baz (ya
        // drawer-e mobile) label dare.
        if (!this.wide) this.hovered = key;
    },

    hide(key) {
        if (this.hovered === key) this.hovered = null;
    },
});
