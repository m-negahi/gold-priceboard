/**
 * Dialog-e ta'yid-e sarasari (`$store.confirm`).
 *
 * Yek host (`x-panel.confirm`) yek bar tu layout render mishe; har ja-ye panel ba
 * `await $store.confirm.ask({ title, body, danger })` yek Promise<boolean> migire. Faghat yek
 * soal dar har lahze: age dovomi bepors-e, avali ba `false` baste mishe ta hich promise-i
 * moallagh namune.
 */
export const store = {
    open: false,
    title: '',
    body: '',
    confirmLabel: 'تأیید',
    cancelLabel: 'انصراف',
    danger: false,
    /** @type {((value: boolean) => void)|null} */
    resolver: null,

    /**
     * @param {{ title: string, body?: string, confirm?: string, cancel?: string, danger?: boolean }} options
     * @returns {Promise<boolean>}
     */
    ask({ title, body = '', confirm = 'تأیید', cancel = 'انصراف', danger = false }) {
        this.settle(false);

        this.title = title;
        this.body = body;
        this.confirmLabel = confirm;
        this.cancelLabel = cancel;
        this.danger = danger;
        this.open = true;

        return new Promise((resolve) => {
            this.resolver = resolve;
        });
    },

    accept() {
        this.settle(true);
    },

    cancel() {
        this.settle(false);
    },

    settle(value) {
        const resolve = this.resolver;

        this.resolver = null;
        this.open = false;

        if (resolve) resolve(value);
    },
};
