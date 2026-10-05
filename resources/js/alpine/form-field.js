/**
 * Wrapper-e yek field-e form: label, hint, khata.
 *
 * Khata az do ja miad: `error`-e server (Blade az `$errors` mide) ya object-e `errors`-e Alpine-e
 * parent (`{ title: 'پیام' }` ya shekl-e Laravel `{ title: ['پیام'] }`). `this.errors` az scope-e
 * parent be ers mirese; age parent-i nabashe undefined-e, na khata.
 */
export default ({ name = null, error = null, hint = null, id = null } = {}) => ({
    name,
    serverError: error,
    hint,
    fieldId: id,

    get message() {
        if (this.serverError) return this.serverError;
        if (!this.name) return null;

        const errors = this.errors;

        if (!errors || typeof errors !== 'object') return null;

        const found = errors[this.name] ?? errors[this.name.replace(/\[([^\]]+)\]/g, '.$1')];

        return Array.isArray(found) ? (found[0] ?? null) : (found ?? null);
    },

    get invalid() {
        return this.message !== null && this.message !== '';
    },

    /** id-haye paragraph-haye zir-e field, baraye aria-describedby-e control. */
    get describedBy() {
        if (!this.fieldId) return null;

        return this.invalid ? `${this.fieldId}-error` : this.hint ? `${this.fieldId}-hint` : null;
    },

    /** Vaghti karbar dobare type mikone, khata-ye server-e kohne dige ma'na nadare. */
    clearServerError() {
        this.serverError = null;
    },
});
