/**
 * Field-e entekhab-e tasvir (`x-form.media-picker`): meghdar-esh id-e rasane-ast (x-modelable),
 * `media` object-e namayeshi (thumb + alt). Entekhab az modal-e mediaLibrary-e safhe.
 */
export default ({ value = null, media = null }) => ({
    value,
    media,

    init() {
        this.$watch('value', (v) => {
            if (v === null || v === '') this.media = null;
        });
    },

    choose() {
        window.dispatchEvent(new CustomEvent('media-library-open', {
            detail: { onPick: (row) => { this.media = row; this.value = row.id; } },
        }));
    },

    clear() {
        this.value = null;
        this.media = null;
    },
});
