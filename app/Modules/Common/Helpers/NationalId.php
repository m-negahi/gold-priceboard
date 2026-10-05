<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Checksum-e shenase-haye ghanooni-ye Iran — yek ja, ta form-e forooshande (Setting), onboarding-e
 * moshtari (Merchant) va factor (Billing) hame yek javab bedan.
 *
 * - کد ملی (shakhs-e haghighi): 10 ragham; jam'-e (ragham × vazn 10..2) mod 11 → age kamtar az 2
 *   khodesh, vagarna 11 menha-ye un, bayad ba ragham-e dahom yeki bashe.
 * - شناسه ملی (shakhs-e hoghooghi): 11 ragham; ragham-e dahom + 2 be har ragham ezafe mishe, ba
 *   vazn-haye 29,27,23,19,17,29,27,23,19,17 jam' mishe, mod 11 (10 → 0) bayad ragham-e yazdahom bashe.
 *
 * Har do raghame Farsi ro ghabul mikonan (`StringHelper::numbersToEnglish`) va rishte ye yek-raghame tekrari
 * (0000000000) ro rad mikonan, chon checksum-esh dorost-e vali hich adami un ro nadare.
 */
final class NationalId
{
    private const COMPANY_WEIGHTS = [29, 27, 23, 19, 17, 29, 27, 23, 19, 17];

    /** Ragham-haye ASCII-e yek shenase, ya null age chizi joz ragham (ba'd az normal-sazi) dashte bashe. */
    public static function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = trim((string) StringHelper::numbersToEnglish($value));

        return preg_match('/^\d+$/', $digits) === 1 ? $digits : null;
    }

    public static function isValidPerson(?string $value): bool
    {
        $digits = self::digits($value);

        if ($digits === null || strlen($digits) !== 10 || self::isRepeated($digits)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = $remainder < 2 ? $remainder : 11 - $remainder;

        return $check === (int) $digits[9];
    }

    public static function isValidCompany(?string $value): bool
    {
        $digits = self::digits($value);

        if ($digits === null || strlen($digits) !== 11 || self::isRepeated($digits)) {
            return false;
        }

        $offset = (int) $digits[9] + 2;
        $sum = 0;

        foreach (self::COMPANY_WEIGHTS as $i => $weight) {
            $sum += ((int) $digits[$i] + $offset) * $weight;
        }

        $check = $sum % 11;

        return ($check === 10 ? 0 : $check) === (int) $digits[10];
    }

    private static function isRepeated(string $digits): bool
    {
        return count(array_unique(str_split($digits))) === 1;
    }
}
