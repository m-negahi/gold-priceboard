/**
 * Store-e toast-haye panel (`$store.toasts`).
 *
 * Har toast yek kind (success / error / warning / info), yek title va yek message-e ekhtiari
 * dare. Khodesh ba'd az `duration` mire (0 yani mimune ta karbar bebandadesh), rooye hover
 * negah dashte mishe va ba'd edame mide, va bishtar az `MAX` ta rooye ham namimune —
 * ghadimi-tarin ha miran ta safhe zir-e yek regbar-e khata ghayb nashe.
 *
 * Timer ha kharej az state-e reactive negah dashte mishan: yek setTimeout chizi nist ke Alpine
 * bekhad proxy-esh kone.
 */
const MAX = 5;

const DEFAULT_DURATION = 5000;

/** id ⇒ { handle, endsAt, remaining } */
const timers = new Map();

export const store = {
    items: [],
    seq: 0,

    /**
     * @param {{ kind?: string, title: string, message?: string|null, duration?: number }} toast
     * @returns {number} id-e toast, baraye dismiss-e dasti
     */
    push({ kind = 'info', title, message = null, duration = DEFAULT_DURATION }) {
        const id = ++this.seq;

        this.items.push({ id, kind: normalizeKind(kind), title: String(title ?? ''), message: message ? String(message) : null, duration });

        while (this.items.length > MAX) {
            this.dismiss(this.items[0].id);
        }

        if (duration > 0) {
            timers.set(id, { handle: null, endsAt: 0, remaining: duration });
            this.resume(id);
        }

        return id;
    },

    success(title, message = null, options = {}) {
        return this.push({ kind: 'success', title, message, ...options });
    },

    error(title, message = null, options = {}) {
        return this.push({ kind: 'error', title, message, ...options });
    },

    warning(title, message = null, options = {}) {
        return this.push({ kind: 'warning', title, message, ...options });
    },

    info(title, message = null, options = {}) {
        return this.push({ kind: 'info', title, message, ...options });
    },

    dismiss(id) {
        const timer = timers.get(id);

        if (timer) {
            clearTimeout(timer.handle);
            timers.delete(id);
        }

        this.items = this.items.filter((item) => item.id !== id);
    },

    clear() {
        [...this.items].forEach((item) => this.dismiss(item.id));
    },

    /** Mouse rooye toast-e: zaman-e mande ro negah dar, timer ro motevaghef kon. */
    pause(id) {
        const timer = timers.get(id);

        if (!timer || timer.handle === null) return;

        clearTimeout(timer.handle);
        timer.handle = null;
        timer.remaining = Math.max(0, timer.endsAt - Date.now());
    },

    /** Mouse raft: az hamun ja ke moande bood edame bede, na az aval. */
    resume(id) {
        const timer = timers.get(id);

        if (!timer || timer.handle !== null) return;

        timer.endsAt = Date.now() + timer.remaining;
        timer.handle = setTimeout(() => this.dismiss(id), timer.remaining);
    },
};

function normalizeKind(kind) {
    return ['success', 'error', 'warning', 'info'].includes(kind) ? kind : 'info';
}
