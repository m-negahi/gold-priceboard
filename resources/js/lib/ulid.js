/**
 * ULID-e client-side (Crockford base32, 26 nevise, uppercase) — hamun shekli ke `SectionNormalizer`
 * ghabool mikone, ta id-e section az lahze-ye sakht tu studio sabet bemune va preview/swap-e iframe
 * rooye hamun id kar kone. Server bazham check mikone va id-e tekrari/na-mo'tabar ro taze mikone.
 */
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export function ulid(now = Date.now()) {
    let time = '';
    let t = now;

    for (let i = 0; i < 10; i++) {
        time = ALPHABET[t % 32] + time;
        t = Math.floor(t / 32);
    }

    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    let random = '';

    for (let i = 0; i < 16; i++) random += ALPHABET[bytes[i] % 32];

    return time + random;
}
