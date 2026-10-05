/**
 * Yek field-e code-e yekbar-masraf-e standard, hamun-tor ke adam entezar dare raftar kone.
 *
 * Box ha tu DOM chap-be-rast hastand (parent unha ro tu yek jazire-ye LTR render mikone), pas index 0
 * box-e samt-e chap-e va "jelo" yani rast — baraye Tab, baraye arrow ha va baraye type kardan.
 *
 * Manba'-e haghighat `digits`-e; `code` az un moshtagh mishe va az tarigh-e `x-modelable` birun
 * miad, pas parent mitoone ba x-model bekhoonadesh, ba '' pakesh kone va ba yek string-e kamel poresh kone.
 */
import { digitsOnly } from '../lib/digits.js';

export default ({ length = 6 } = {}) => ({
    length,
    digits: Array.from({ length }, () => ''),
    code: '',

    init() {
        // Az biroon set shode (auto-fill, pak kardan moghe-ye khata): tu box ha mirror-esh kon, ba'd
        // avalin box-e khali ro focus kon — vali faghat vaghti field vaghean rooye safhe-ast.
        this.$watch('code', (value) => {
            if (value === this.digits.join('')) return;

            const clean = this.toDigits(value).slice(0, this.length);
            this.digits = Array.from({ length: this.length }, (_, i) => clean[i] ?? '');

            if (this.isVisible()) this.$nextTick(() => this.focus(this.firstEmpty() === -1 ? this.length - 1 : this.firstEmpty()));
        });
    },

    boxes() {
        return Array.from(this.$root.querySelectorAll('[data-otp-box]'));
    },

    digitAt(index) {
        return this.digits[index] ?? '';
    },

    firstEmpty() {
        return this.digits.findIndex((d) => d === '');
    },

    isVisible() {
        return this.$root.getClientRects().length > 0;
    },

    focus(index) {
        const box = this.boxes()[Math.max(0, Math.min(index, this.length - 1))];
        box?.focus();
        box?.select();
    },

    /**
     * Hich vaght ja-khali nazar: *click* ba'd az avalin box-e khali rooye hamun box mineshine. Faghat
     * baraye pointer — Tab va arrow ha bayad daghighan hamun ja beran ke karbar ferestade.
     */
    onPointerDown(index, event) {
        const gap = this.firstEmpty();

        if (gap !== -1 && index > gap) {
            event.preventDefault();
            this.focus(gap);
        }
    },

    /** Focus select mikone, pas type kardan ragham ro jaygozin mikone na inke behesh bechasbe. */
    onFocus(index, event) {
        event.target.select();
    },

    onInput(index, event) {
        const typed = this.toDigits(event.target.value);

        if (typed === '') {
            this.set(index, '');
            return;
        }

        // Chand character yekja = paste ya autofill-i ke rooye yek box neshaste.
        if (typed.length > 1) {
            this.fill(typed, typed.length >= this.length ? 0 : index);
            return;
        }

        this.set(index, typed);

        if (index < this.length - 1) this.focus(index + 1);

        this.announce();
    },

    onKeydown(index, event) {
        switch (event.key) {
            case 'Backspace':
                event.preventDefault();
                if (this.digits[index]) {
                    this.set(index, '');
                } else if (index > 0) {
                    this.set(index - 1, '');
                    this.focus(index - 1);
                }
                return;
            case 'Delete':
                event.preventDefault();
                this.set(index, '');
                return;
            case 'ArrowLeft':
                event.preventDefault();
                this.focus(index - 1);
                return;
            case 'ArrowRight':
                event.preventDefault();
                this.focus(index + 1);
                return;
            case 'Home':
                event.preventDefault();
                this.focus(0);
                return;
            case 'End':
                event.preventDefault();
                this.focus(this.length - 1);
                return;
            default:
                // Ragham (ba har khat), navigation va shortcut ha rad mishan; harf va symbol na.
                if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && this.toDigits(event.key) === '') {
                    event.preventDefault();
                }
        }
    },

    /** Code-e paste shode az box-e aval por mishe, har ja ke paste neshaste bashe. */
    onPaste(event) {
        const pasted = this.toDigits(event.clipboardData?.getData('text') ?? '');

        if (pasted) this.fill(pasted, 0);
    },

    fill(value, from) {
        const chars = value.slice(0, this.length - from);

        for (let i = 0; i < chars.length; i++) {
            this.digits[from + i] = chars[i];
        }

        this.sync();
        this.$nextTick(() => this.focus(from + chars.length));
        this.announce();
    },

    set(index, value) {
        this.digits[index] = value;
        this.sync();
    },

    sync() {
        this.code = this.digits.join('');
        this.boxes().forEach((box, i) => {
            if (box.value !== this.digits[i]) box.value = this.digits[i];
        });
    },

    announce() {
        if (this.digits.every((d) => d !== '')) {
            this.$dispatch('complete', this.code);
        }
    },

    toDigits(value) {
        return digitsOnly(value);
    },
});
