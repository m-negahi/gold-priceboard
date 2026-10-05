<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Sms;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use Illuminate\Contracts\Foundation\Application;

/**
 * Switcher e driver — hamun olgu ye `SourceManager` e Pricing.
 *
 * Driver e nashenas hamin ja miterke (na saket) — `SMS_DRIVER=kavengar` e
 * typo-dar nabayad ta avvalin login e vaghei penhan bemune.
 */
final readonly class SmsManager
{
    /** @return array<int, string> */
    public static function drivers(): array
    {
        // `log` e joda HAZF shod: `fake` khodesh log mizane، pas do driver
        // baraye yek kar budan. (2026-10-05)
        return ['kavenegar', 'fake'];
    }

    public static function make(string $driver, Application $app): SmsPort
    {
        return match ($driver) {
            'kavenegar' => new KavenegarSms(
                (array) config('sms.kavenegar', []),
                (int) config('sms.timeout', 10),
            ),
            // ⚠️ Az container — bayad haman singleton-i bashe ke test ha
            // resolve mikonan.
            'fake' => $app->make(FakeSms::class),
            default => throw SmsException::unknownDriver($driver),
        };
    }
}
