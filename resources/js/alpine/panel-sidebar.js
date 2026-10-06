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
    /**
     * Tooltip-e rail-e baste: `{ label, top, side, offset }` ya null.
     *
     * 🔴 Chera FIXED + teleport be body: tooltip-e `absolute` dakhel-e
     * `<nav class="overflow-x-hidden">` (va shell-e `overflow: hidden`)
     * boride mishod — tu rail-e baste HICH tooltip-i dide nemishod. Mokhtasat
     * az `getBoundingClientRect`-e item miad.
     */
    tip: null,

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
        this.tip = null;

        try {
            localStorage.setItem(STORAGE_KEY, this.expanded ? '1' : '0');
        } catch {
            // Saket: faghat tarjih-e in device az dast mire.
        }
    },

    show(key, el = null, label = '') {
        // Tooltip faghat tu rail-e BASTE ma'ni dare — halat-e baz (ya
        // drawer-e mobile) label dare.
        if (this.wide) return;

        this.hovered = key;

        if (!el || !label) return;

        const rect = el.getBoundingClientRect();
        const gap = 8;
        // RTL: rail samt-e RAST-e -> tooltip samt-e CHAP-e rail (az `right`)؛
        // LTR bar'aks. Hichvaght rooye icon-i ke tosif mikone nemishine.
        const rtl = document.documentElement.dir === 'rtl';

        this.tip = {
            key,
            label,
            top: rect.top + rect.height / 2,
            side: rtl ? 'right' : 'left',
            offset: rtl ? window.innerWidth - rect.left + gap : rect.right + gap,
        };
    },

    hide(key) {
        if (this.hovered === key) this.hovered = null;
        if (this.tip?.key === key) this.tip = null;
    },

    /** Scroll-e nav / resize mokhtasat ro kohne mikone — penhan, na jabeja. */
    dropTip() {
        this.hovered = null;
        this.tip = null;
    },

    /** Mokhtasat-e tooltip — tanha `style`-e pooya-ye panel (token momken nist). */
    tipStyle() {
        return this.tip ? `top: ${this.tip.top}px; ${this.tip.side}: ${this.tip.offset}px;` : '';
    },
});
