/**
 * Field-e mablagh ba jodakonande-ye hezargan-e ZENDE (`1,250,000`).
 *
 * Karbar ragham-e farsi ya latin type mikone; hamun lahze ASCII mishe va har 3
 * ragham yek `,` migire. Meghdar-e submit-shode HAMIN reshte-ye comma-dar-e —
 * server ba `Money::rialFromInput()` comma ro bar midare (hamun gharardad-e
 * `users/show` ke `placeholder="125,000"` dasht vali zende format nemikard).
 *
 * Caret: tedad-e ragham-e samt-e chap-e caret hefz mishe, na index-e kham —
 * vagarna ba har comma-ye jadid caret yek khane mipare.
 */
import { toAscii } from '../lib/digits.js';

const group = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

export default () => ({
    init() {
        // Meghdar-e avaliye (old()/model) adad-e kham-e; az hamun aval group she.
        const input = this.$el;
        const clean = toAscii(input.value ?? '').replace(/\D/g, '');
        if (clean !== '' && group(clean) !== input.value) input.value = group(clean);
    },

    format(event) {
        const input = event.target;
        const raw = input.value;
        const caret = input.selectionStart ?? raw.length;
        const digitsBeforeCaret = toAscii(raw.slice(0, caret)).replace(/\D/g, '').length;

        const clean = toAscii(raw).replace(/\D/g, '');
        const next = clean === '' ? '' : group(clean);

        if (next === raw) return;

        input.value = next;

        // Caret ro ba'd az haman tedad ragham bezar.
        let pos = 0;
        let seen = 0;
        while (pos < next.length && seen < digitsBeforeCaret) {
            if (/\d/.test(next[pos])) seen++;
            pos++;
        }
        input.setSelectionRange(pos, pos);

        // x-model-e parent (age bud) value-ye kohne ro khoonde; dobare bekhoone.
        input.dispatchEvent(new Event('input', { bubbles: true }));
    },
});
