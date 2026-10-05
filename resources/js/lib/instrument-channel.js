/**
 * Kanal-e mohr-shode-ye safhe-ye instrument (ADR-0009), rooye hamun primitive-haye `board-channel.js`
 * (eshtighagh-e kelid, decrypt, format-e adad) vali baraye URL-haye delkhah (feed + series-e yek
 * instrument) — singleton-e tablo faghat beacon/feed-e khodesh ro mizane.
 *
 * - Har darkhast `X-Board-Nonce` mibare va nonce-e ba'di ro az javab bar midare (yek-bar-masraf),
 *   pas darkhast ha pusht-e sar-e ham saf mishan — do fetch-e hamzaman nonce ro misuzunand.
 * - 403 (nonce-e monghazi ba'd az tab-e penhan, charkhesh-e kelid): yek bar `GET handshake`-e tablo
 *   (seed + nonce-e tazeh) va tekrar. Hich vaght reload-e safhe.
 */
import { decrypt, signals } from './board-channel.js';

const CONTEXT = 'price-board-channel-v1|';
export const NONCE_HEADER = 'X-Board-Nonce';

const encoder = new TextEncoder();

/** Kelid-e AES az seed — daghighan hamun eshtighagh-e server (ChannelCipher::key). */
export async function channelKey(seed) {
    const digest = await crypto.subtle.digest('SHA-256', encoder.encode(CONTEXT + seed));

    return crypto.subtle.importKey('raw', digest, { name: 'AES-GCM' }, false, ['decrypt']);
}

/**
 * @param {{ seed: string, nonce: string, handshake?: string }} options  handshake = URL-e `board.handshake`
 */
export function createChannel({ seed, nonce, handshake = '', beacon = '' }) {
    let currentSeed = seed;
    let currentNonce = nonce;
    let key = null;
    let queue = Promise.resolve();

    const ensureKey = async () => (key ??= await channelKey(currentSeed));

    async function refresh() {
        if (!handshake) return false;

        try {
            const response = await fetch(handshake, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || typeof data.seed !== 'string' || typeof data.nonce !== 'string') return false;

            if (data.seed !== currentSeed) {
                currentSeed = data.seed;
                key = null;
            }

            currentNonce = data.nonce;

            return true;
        } catch {
            return false;
        }
    }

    /** Server ta beacon naresad ciphertext nemide (ADR-0040): yek bar neshane-haye morurgar ro miferestim. */
    async function sendBeacon() {
        if (!beacon) return false;

        try {
            const response = await fetch(beacon, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', [NONCE_HEADER]: currentNonce },
                body: JSON.stringify(signals()),
            });
            const data = await response.json().catch(() => ({}));

            if (typeof data.nonce === 'string' && data.nonce !== '') currentNonce = data.nonce;

            return response.ok;
        } catch {
            return false;
        }
    }

    async function request(url, retried = false) {
        let response;

        try {
            response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', [NONCE_HEADER]: currentNonce },
            });
        } catch {
            return { ok: false, status: 0, data: null };
        }

        const json = await response.json().catch(() => ({}));

        if (response.status === 403 && !retried && json?.error?.code === 'board.beacon_required' && (await sendBeacon())) return request(url, true);

        if (response.status === 403 && !retried && (await refresh())) return request(url, true);

        if (typeof json.nonce === 'string' && json.nonce !== '') currentNonce = json.nonce;

        if (!response.ok || typeof json.payload !== 'string') return { ok: false, status: response.status, data: null };

        const data = await decrypt(await ensureKey(), json.payload);

        // Tag nakhoond = kelid charkhide: yek handshake va tekrar.
        if (data === null && !retried && (await refresh())) return request(url, true);

        return { ok: data !== null, status: response.status, data };
    }

    return {
        /** Darkhast-haye kanal yeki-yeki miran ta nonce-e yek-bar-masraf nasuze. */
        get(url) {
            const run = queue.then(() => request(url));
            queue = run.catch(() => {});

            return run;
        },

        /** Baz kardan-e chizi ke ba seed-e safhe mohr shode (naghshe-ye selector ha); null vaghti nakhoone. */
        async open(ciphertext) {
            return decrypt(await ensureKey(), ciphertext);
        },
    };
}
