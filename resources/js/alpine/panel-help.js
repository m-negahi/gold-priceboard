/**
 * Drawer-e rahnama-ye safhe (`x-panel.help`).
 *
 * `seen` per-browser tu localStorage mimune (keyed be `key`-e safhe) ta
 * dot-e «jadid» faghat ta avvalin baz kardan dide beshe. Storage block
 * bashe، faghat dot hamishe mimune — hich chiz nemishkane.
 */
const STORAGE_PREFIX = 'pb:help:seen:';

export default ({ key = null } = {}) => ({
    open: false,
    seen: true,

    init() {
        if (!key) return;

        try {
            this.seen = localStorage.getItem(STORAGE_PREFIX + key) === '1';
        } catch {
            this.seen = true;
        }
    },

    show() {
        this.open = true;
        this.seen = true;

        if (!key) return;

        try {
            localStorage.setItem(STORAGE_PREFIX + key, '1');
        } catch {
            // Faghat yad-avari az dast mire.
        }
    },

    close() {
        this.open = false;
    },
});
