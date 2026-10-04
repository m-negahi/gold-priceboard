<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Iranian mobile number helpers. Storage format is E.164 (+989XXXXXXXXX).
 */
final class PhoneHelper
{
    /**
     * Normalize common Iranian mobile inputs to E.164 (+989XXXXXXXXX).
     * Accepts: 09121234567, 9121234567, 989121234567, +989121234567,
     * with spaces/dashes/parentheses and Persian/Arabic digits.
     * Returns null if it is not a valid mobile.
     */
    public static function normalize(?string $input): ?string
    {
        $core = self::core($input);

        return $core === null ? null : '+98'.$core;
    }

    /**
     * Normalize to the local Iranian format (09XXXXXXXXX) — the shape used by
     * the auth/OTP API and shown in the UI. Same lenient parsing as normalize().
     * Returns null if it is not a valid mobile.
     */
    public static function toLocal(?string $input): ?string
    {
        $core = self::core($input);

        return $core === null ? null : '0'.$core;
    }

    public static function isValid(?string $input): bool
    {
        return self::core($input) !== null;
    }

    /**
     * The 10-digit 9XXXXXXXXX core of any accepted input, or null if invalid.
     * Strips separators, Persian/Arabic digits, and leading country/zero prefixes.
     */
    private static function core(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = self::toLatinDigits($input);
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        // Strip leading country/zero prefixes down to the 10-digit 9XXXXXXXXX core.
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^9\d{9}$/', $digits) === 1 ? $digits : null;
    }

    /**
     * A `tel:` href from any Iranian number — **including landlines**, which the
     * mobile-only helpers above reject (021… is not a 9XXXXXXXXX core).
     * Local `0XX…` becomes `+98XX…`. Empty input → ''.
     *
     * The `tel:` scheme is required: without it the browser reads the href as a
     * relative path and the link never dials.
     */
    public static function telLink(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', self::toLatinDigits((string) $phone)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '98'.substr($digits, 1);
        }

        return 'tel:+'.$digits;
    }

    /**
     * Readable grouping for display: `021-28421130`. Anything that is not a
     * Tehran landline is returned as bare digits.
     */
    public static function pretty(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', self::toLatinDigits((string) $phone)) ?? '';

        return str_starts_with($digits, '021') && strlen($digits) > 3
            ? '021-'.substr($digits, 3)
            : $digits;
    }

    /**
     * Mask a stored phone for logs/UI: +98912***4567
     */
    public static function mask(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        return preg_replace('/(\+98\d{3})\d{3}(\d{2})/', '$1***$2', $phone) ?? $phone;
    }

    private static function toLatinDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$persian, ...$arabic], [...$latin, ...$latin], $value);
    }
}
