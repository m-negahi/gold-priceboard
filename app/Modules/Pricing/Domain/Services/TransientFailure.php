<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

use Throwable;

/**
 * Aya in shekast GOZARA-st (arzesh e yek talash e dobare dare)?
 *
 * ── 🔴 Chera in tasmim barik ast ─────────────────────────────────────
 * Retry e bi-ghaid budget e tick ro mikhore. `Http::pool` montazer e HAME
 * mimune، pas yek retry e eshtebah hame ye provider ha ro aghab mindaze —
 * ya'ni baraye jobran e yek surakh tu YEK series، yek surakh tu HAME ye
 * series misazim. Pas faghat chizi retry mishe ke (a) vaghe'an gozara bashe
 * va (b) SARI' shekast bokhore.
 *
 * ── Chi retry mishe ─────────────────────────────────────────────────
 * Faghat khata haye sath e ETTESAL ke tu kasri az saniye miterkan:
 *   6  — couldn't resolve host
 *   7  — couldn't connect
 *   35 — SSL/TLS connect error   ← hamun ke VAGHE'AN didim
 *   52 — empty reply from server
 *   56 — recv failure / reset
 *
 * Tu `P1` andaze-giri shod: `tgju` gah-gah ba `cURL error 35` miofte. Un
 * khata handshake e TLS ast va tu ~100-500ms miofte، pas yek talash e
 * dobare amalan majani-e.
 *
 * ── 🔴 Chi retry NEMISHE (va chera) ─────────────────────────────────
 * **28 (operation timed out)** — AMDAN birun ast. Timeout ya'ni ma budget e
 *   KAMEL e un provider ro ghablan kharj kardim (`estjt` 20 saniye!). Retry
 *   kardanesh un ro DO barabar mikone. Gerun tarin shekast، bad tarin
 *   kandida baraye takrar.
 * **5xx / 429** — gozara-and vali 300ms ba'd hamun javab ro midan، va
 *   hazine ash yek round-trip e kamel ast (6-9s baraye estjt). Agar ruzi
 *   data neshun dad ke arzesh dare، ba yek backoff e bozorgtar ezafe mishe.
 * **4xx** — eshtebah e CONFIG ast، na ghat'i. Retry faghat un ro penhan
 *   mikone.
 * **Khata ye parse / JSON nabudan** — markup e upstream avaz shode. Talash e
 *   dobare DAGHIGHAN hamun javab ro mide. In bayad DIDE she، na takrar.
 */
final readonly class TransientFailure
{
    /** @var array<int, int> */
    private const RETRYABLE_CURL_CODES = [6, 7, 35, 52, 56];

    public static function isRetryable(Throwable $e): bool
    {
        $code = self::curlCode($e->getMessage());

        if ($code === null) {
            return false;
        }

        return in_array($code, self::RETRYABLE_CURL_CODES, true);
    }

    /**
     * Keshidan e code e curl az matn e khata.
     *
     * ⚠️ Match ru MATN ast، na ru class e exception — `Domain/` nabayad
     * `Illuminate\Http\Client\ConnectionException` ro bebine. Matn e khata ye
     * curl («cURL error 35: ...») sabet ast va khod e libcurl un ro misaze.
     */
    public static function curlCode(string $message): ?int
    {
        if (preg_match('/cURL error (\d+)/i', $message, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
