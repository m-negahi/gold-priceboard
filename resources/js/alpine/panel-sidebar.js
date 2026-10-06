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
    /** Kelid-e item-i ke tooltip-esh alan bayad dide beshe (faghat rail-e baste). */
    hovered: null,

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
        // Tooltip faghat tu rail-e BASTE ma'ni dare — halat-e baz label dare.
        if (!this.expanded) this.hovered = key;
    },

    hide(key) {
        if (this.hovered === key) this.hovered = null;
    },
});
