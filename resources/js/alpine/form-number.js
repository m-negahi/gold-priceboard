/**
 * Field-e adadi. Keyboard-e farsi ragham-e farsi mide (va `٫` be jaye noghte); inja hamun
 * lahze ASCII mishe ta x-model-e parent va validator-e server yek chiz bebinand — hamun kari
 * ke middleware-e NormalizeInput samt-e server mikone, vali ghabl az inke karbar submit kone.
 *
 * x-model-e Alpine listener-esh ro ghabl az `@input`-e ma sabt mikone, pas value-ye kohne ro
 * khoonde; vaghti chizi avaz shod yek event-e input-e dovom mifrestim ta dobare bekhoone.
 */
import { toAscii } from '../lib/digits.js';

export default () => ({
    normalize(event) {
        const input = event.target;
        const clean = toAscii(input.value).replace(/[٬,]/g, '');

        if (clean === input.value) return;

        const caret = input.selectionStart;

        input.value = clean;
        if (caret !== null) input.setSelectionRange(caret, caret);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    },
});
