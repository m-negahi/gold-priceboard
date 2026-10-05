<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Tabdil e field haye shomare-manand be E.164 (`+989XXXXXXXXX`).
 *
 * BA'D az `NormalizeStringMiddleware` ejra mishe، ta raghamha ghablan
 * English shode bashan.
 *
 * ── ⚠️ Shekast = meghdar e ASLI hefz mishe ──────────────────────────
 * Agar normalize nashod، meghdar e vorudi dast NAKHORDE bar migarde — AMDAN.
 * Rad kardan e yek shomare ye na-motabar kar e VALIDATION ast، na kar e
 * middleware: agar inja `null` bezarim، payam e khata ye form mishe «field
 * ejbari-e» jaye «shomare motabar nist»، va karbar nemifahme chi ghalat bud.
 */
final class NormalizePhoneMiddleware extends TransformsRequest
{
    /**
     * Esm e field hayi (ya `*_suffix`) ke be onvan e shomare telefon raftar
     * mishan.
     *
     * @var array<int, string>
     */
    protected const PHONE_FIELDS = [
        'phone',
        'mobile',
        'phone_number',
        'mobile_number',
        'tel',
        'telephone',
    ];

    protected function transform($key, $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (! $this->isPhoneField($key)) {
            return $value;
        }

        return PhoneHelper::normalize($value) ?? $value;
    }

    private function isPhoneField(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::PHONE_FIELDS as $field) {
            if ($key === $field || str_ends_with($key, '_'.$field)) {
                return true;
            }
        }

        return false;
    }
}
