/**
 * Tanha dar-e vorood (PRD F5, ADR-0021).
 *
 * Shomarande hich vaght mal-e ma nist: har javab sanie-haye mande-ye server ro miare va reload
 * dobare az server miporse. Timer-e samt-e client ba refresh az aval shoroo mishe va yek resend-e
 * majani mide — daghighan hamun sourakhi ke rate limit baraye bastanesh hast.
 */
import { digitsOnly } from '../lib/digits.js';

export default ({ routes, initial = {} }) => ({
    // Step ro server tasmim migire (ADR-0021): ba'd az refresh, age code-e zende ya cooldown
    // hast, mostaghim tu step-e code shoroo mikonim va shomarande az hamun ja edame mide.
    step: initial.step ?? 'phone',
    phone: initial.phone ?? '',
    code: '',
    resendIn: 0,
    expiresIn: 0,
    busy: false,
    verifying: false,
    error: '',
    revealedCode: initial.code ?? null,
    ticker: null,
    abort: null,

    get canResend() {
        return this.resendIn <= 0 && !this.busy;
    },

    /** `0912 345 6789` baraye cheshm; server faghat ragham migire. */
    get prettyPhone() {
        const d = this.digitsOnly(this.phone).replace(/^(98|0)/, '');

        return d ? `0${d.slice(0, 3)} ${d.slice(3, 6)} ${d.slice(6, 10)}`.trim() : '';
    },

    get phoneLooksComplete() {
        return /^9\d{9}$/.test(this.digitsOnly(this.phone).replace(/^(98|0)/, ''));
    },

    init() {
        this.$watch('step', (step) => {
            if (step === 'code') {
                this.focusOnceShown('[data-otp-box]');
                this.listenForSms();
            } else {
                this.abort?.abort();
                this.focusOnceShown('#phone');
            }
        });

        if (this.step === 'code') {
            this.expiresIn = initial.expiresIn ?? 0;
            this.startCountdown(initial.resendIn ?? 0);
            this.focusOnceShown('[data-otp-box]');
            this.listenForSms();
        } else {
            this.$nextTick(() => this.$refs.phone?.focus());
        }
    },

    /**
     * `x-show` element ro rooye yek timeout neshun mide (guard-e click-away-e Alpine), pas element-i
     * ke tazeh neshun dade shode tu hamun tick focus nemigire. Yek macrotask ba'd, migire.
     */
    focusOnceShown(selector) {
        const attempt = (left) => {
            const el = this.$root.querySelector(selector);

            if (el && el.getClientRects().length > 0) {
                el.focus();
                if (document.activeElement === el) return;
            }

            if (left > 0) setTimeout(() => attempt(left - 1), 16);
        };

        this.$nextTick(() => attempt(30));
    },

    onPhoneInput(event) {
        // Keyboard-e farsi ragham-e farsi mide; field ro ASCII negah dar ta autofill-e `type=tel`
        // va validator yek chiz bebinand.
        this.phone = this.digitsOnly(event.target.value).slice(0, 12);
    },

    async requestCode() {
        if (this.busy) return;

        this.busy = true;
        this.error = '';

        const response = await this.post(routes.code, { phone: this.phone });

        this.busy = false;

        if (response.status === 429) {
            // Dakhel-e cooldown: SMS-e dovom nist, vali safhe-ye code ba zaman-e vaghe'i-e mande.
            this.startCountdown(response.body.retry_after ?? 0);
            this.step = 'code';
            return;
        }

        if (!response.ok) {
            this.error = this.firstError(response.body) ?? 'ارسال کد ممکن نشد. دوباره تلاش کنید.';
            return;
        }

        this.code = '';
        this.revealedCode = response.body.code ?? null;
        this.expiresIn = response.body.expires_in ?? 0;
        this.startCountdown(response.body.resend_in ?? 0);
        this.step = 'code';
    },

    async verify() {
        if (this.verifying || this.code.length < 6) return;

        this.verifying = true;
        this.error = '';

        const response = await this.post(routes.verify, { phone: this.phone, code: this.code });

        if (!response.ok) {
            this.verifying = false;
            this.error = this.firstError(response.body) ?? 'کد درست نیست.';
            this.code = '';
            return;
        }

        window.location.href = response.body.redirect;
    },

    /** Dokme-ye bypass: code-e neshun dade shode ro daghighan mesle paste por mikone, ba'd submit. */
    autoFill() {
        if (!this.revealedCode) return;

        this.code = this.revealedCode;
        this.verify();
    },

    /** «تغییر شماره»: server ham faramoosh mikone, vagarna refresh dobare be code-step bar migarde. */
    changeNumber() {
        this.step = 'phone';
        this.error = '';
        this.code = '';
        this.revealedCode = null;
        this.post(routes.reset, {});
    },

    /**
     * Autofill-e Android. Faghat vaghti kar mikone ke SMS ba `@<origin> #<code>` tamum beshe; be
     * hamin dalil template-e payam ba yek test assert shode.
     */
    listenForSms() {
        if (!('OTPCredential' in window)) return;

        this.abort?.abort();
        this.abort = new AbortController();

        navigator.credentials
            .get({ otp: { transport: ['sms'] }, signal: this.abort.signal })
            .then((credential) => {
                if (credential?.code) {
                    this.code = credential.code.replace(/\D/g, '');
                    this.verify();
                }
            })
            .catch(() => {
                // Rad shode, poshtibani nashode, ya safhe avaz shode — karbar khodesh type mikone.
            });
    },

    startCountdown(seconds) {
        this.resendIn = Math.max(0, Math.round(seconds));

        clearInterval(this.ticker);
        this.ticker = setInterval(() => {
            this.resendIn = Math.max(0, this.resendIn - 1);
            this.expiresIn = Math.max(0, this.expiresIn - 1);
            if (this.resendIn === 0 && this.expiresIn === 0) clearInterval(this.ticker);
        }, 1000);
    },

    /**
     * m:ss ba raghame LATIN — policy e proje «raghame Latin hame ja» ast
     * (CLAUDE.md): server ham ba `LatinizeDigits` hame chiz ro Latin mikone،
     * pas shomarande ye Farsi vasat e safhe ye Latin nakhana bud.
     */
    clock(seconds) {
        const m = Math.floor(seconds / 60);
        const s = String(seconds % 60).padStart(2, '0');

        return `${m}:${s}`;
    },

    digitsOnly(value) {
        return digitsOnly(value);
    },

    firstError(body) {
        const errors = body?.errors ?? {};

        return Object.values(errors)[0]?.[0] ?? body?.message;
    },

    async post(url, payload) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(payload),
            });

            // 419 yani cookie/session pak shode va token-e CSRF-e safhe kohne-ast: reload yek
            // session-e tamiz mide; age cooldown-e server hanuz bashe, darkhast-e ba'di 429 migire
            // va be code-step mire. Hich khata-i be karbar neshun dade nemishe.
            if (response.status === 419) {
                window.location.reload();

                return { ok: false, status: 419, body: {} };
            }

            return { ok: response.ok, status: response.status, body: await response.json().catch(() => ({})) };
        } catch {
            return { ok: false, status: 0, body: { message: 'اتصال برقرار نشد. اینترنت خود را بررسی کنید.' } };
        }
    },
});
