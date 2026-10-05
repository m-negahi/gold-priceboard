<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Sms;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Kavenegar — REST، **kelid DAKHEL e masir e URL** (na header).
 *
 * Do dam ke tu projeh ye marja' har kodum yek bar vaght gereftan:
 *
 * 1. 🔴 **Khata ha ba HTTP 200 barmigardan.** Code e vaghei tu
 *    `return.status` e body ast (200 = movafagh). Faghat `successful()` ro
 *    check koni، shomare ye namotabar va e'tebar e tamam-shode har do
 *    «movafagh» dide mishan va SMS hargez nemirese — bedun e hich khata.
 * 2. 🔴 **Kelid tu URL ast** — pas HICH exception/log-i nabayad URL ro echo
 *    kone. `ConnectionException` e Laravel URL e kamel ro tu payam esh
 *    dare؛ hamin ke un ro wrap nakoni، kelid tu log mishine.
 *
 * `verify/lookup` jaye `sms/send`: payam e template-i (tarakoneshi) ast،
 * khat e ekhtesasi nemikhad va tahvil esh balast.
 */
final readonly class KavenegarSms implements SmsPort
{
    private const DRIVER = 'kavenegar';

    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
        private int $timeout = 10,
    ) {}

    public function sendOtp(string $phone, string $code): void
    {
        $key = trim((string) ($this->config['api_key'] ?? ''));

        if ($key === '') {
            throw SmsException::missingApiKey(self::DRIVER);
        }

        // Kavenegar format e mahalli mikhad (09XXXXXXXXX)، na E.164.
        $receptor = PhoneHelper::toLocal($phone);

        if ($receptor === null) {
            throw SmsException::invalidReceptor(self::DRIVER, PhoneHelper::mask($phone));
        }

        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.kavenegar.com/v1'), '/');

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->post("{$base}/{$key}/verify/lookup.json", [
                    'receptor' => $receptor,
                    'token' => $code,
                    'template' => (string) ($this->config['template'] ?? 'verify'),
                ]);
        } catch (Throwable) {
            // AMDAN hich chiz az exception e asli pass nemishe — payam esh
            // URL (va kelid) ro dare.
            throw SmsException::connection(self::DRIVER);
        }

        if (! $response->successful()) {
            throw SmsException::httpStatus(self::DRIVER, $response->status());
        }

        // Dam e 1: status e VAGHEI tu body.
        $apiStatus = (int) $response->json('return.status', 0);

        if ($apiStatus !== 200) {
            throw SmsException::apiStatus(
                self::DRIVER,
                $apiStatus,
                (string) $response->json('return.message', 'bedun e payam'),
            );
        }
    }
}
