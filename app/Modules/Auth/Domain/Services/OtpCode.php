<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Services;

/**
 * Chareh-ye OTP: tolid، hash، va verify. PURE — hich Laravel.
 *
 * ── 🔴 Chera hash، va chera HMAC (na sha256 e khali) ────────────────
 * Code HICHVAGHT plaintext zakhire nemishe: yek dump e Redis (ya yek
 * `MONITOR` e baz-munde، ya yek backup e eshtebah) nabayad code haye ZENDE
 * ro lo bede.
 *
 * Vali yek code e 6 raghami faghat 10^6 halat dare — sha256 e khali tu
 * chand millisecond offline brute mishe، ya'ni «hash» e un amalan hamun
 * plaintext ast. Pas HMAC ba KEY e app: bedun e key، dump e Redis be tanha-i
 * bi-estefade-ast. (Model e tahdid: lo raftan e Redis BEDUN e lo raftan e
 * app. Agar har do lo beran، hich hash-i nejat nemide.)
 *
 * Phone ham tu HMAC hast: hash e code e yek shomare baraye shomare ye digar
 * ghabel e replay nist.
 */
final readonly class OtpCode
{
    public function __construct(
        /** Kelid e HMAC — az `APP_KEY` tu ServiceProvider tazrigh mishe. */
        private string $key,
    ) {
        if ($key === '') {
            // Kelid e khali ya'ni HMAC amalan sha256 e khali-e — hamun
            // chizi ke in class baraye jologiri azash vojud dare.
            throw new \InvalidArgumentException('kelid e HMAC e OTP nemitune khali bashe.');
        }
    }

    /**
     * Code e `$digits` raghami — ba sefr e avval e MOJAZ.
     *
     * ⚠️ `random_int(100000, 999999)` ghalat e ma'mul ast: sefr e avval ro
     * hazf mikone va fazaye kelid ro 10% kuchik mikone. `0 ta 10^d - 1` +
     * pad dorost ast.
     */
    public function generate(int $digits = 6): string
    {
        return str_pad(
            (string) random_int(0, (10 ** $digits) - 1),
            $digits,
            '0',
            STR_PAD_LEFT,
        );
    }

    public function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, $this->key);
    }

    /**
     * Moghayese ba `hash_equals` — timing-safe.
     *
     * Moghayese ye `===` tool e ejrash be tedad e byte haye barabar e avval
     * bastegi dare؛ baraye secret ha hamishe `hash_equals`.
     */
    public function verify(string $phone, string $code, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hash($phone, $code));
    }
}
