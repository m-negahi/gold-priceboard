/**
 * Modal-e panel (`x-panel.modal`). Halat-e baz/baste mal-e PARENT-e
 * (`x-modelable="open"` + `x-model` ru component) — in faghat bastan va
 * siasat-e `dismissible` ro midune.
 *
 * `close()` az backdrop (`@click.self`)، dokme-ye X va `onEscape()` seda
 * mishe. Ba `:dismissible="false"` HICH kodum nemibandan (dokme-ye X aslan
 * render nemishe) — faghat parent az tarigh-e x-model mibande (masalan
 * ba'd az submit-e movafagh).
 */
export default ({ dismissible = true } = {}) => ({
    open: false,

    close() {
        if (dismissible) this.open = false;
    },

    onEscape() {
        this.close();
    },
});
