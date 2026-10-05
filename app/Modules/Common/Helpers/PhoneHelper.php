<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

/**
 * Shomare ye mobile e Irani. Format e ZAKHIRE: E.164 (`+989XXXXXXXXX`).
 *
 * ⚠️ Yek format baraye zakhire، yek format baraye namayesh. Ghati kardan
 * eshun ya'ni yek karbar ba `09121234567` sabt mishe va yeki ba
 * `+989121234567` — va un vaght `unique` ru soton e `phone` do radif baraye
 * YEK nafar ejaze mide va login e un ha ba ham ghati mishe.
 */
final class PhoneHelper
{
    /**
     * Har shekl e ma'mul e vorudi -> E.164 (`+989XXXXXXXXX`).
     *
     * Ghabul mikone: `09121234567`، `9121234567`، `989121234567`،
     * `+989121234567`، ba fasele/khat-tire/parantez va raghamha ye
     * Farsi/Arabi.
     *
     * `null` ya'ni mobile e motabar nist.
     */
    public static function normalize(?string $input): ?string
    {
        $core = self::core($input);

        return $core === null ? null : '+98'.$core;
    }

    /**
     * Format e mahalli (`09XXXXXXXXX`) — chizi ke tu UI neshun dade mishe va
     * API e OTP migire. Hamun parse e narm e `normalize()`.
     *
     * `null` ya'ni mobile e motabar nist.
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
     * Maghz e 10-raghami (`9XXXXXXXXX`) — ya `null`.
     *
     * Separator ha، raghamha ye Farsi/Arabi va pishvand haye keshvar/sefr ro
     * bar midare.
     */
    private static function core(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = self::toLatinDigits($input);
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        // Pishvand haye keshvar/sefr bardashte mishan ta be maghz e
        // 10-raghami ye `9XXXXXXXXX` beresim.
        //
        // ⚠️ Tartib MOHEM ast: `0098` bayad GHABL az `98` check beshe،
        // vagarna `0098…` be `98…` match mishe، do raghame avval bardashte
        // mishe va `98…` e baghi-munde yek maghz e ghalat misaze.
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
     * `tel:` href az har shomare ye Irani — **shamel e khat e sabet**، ke
     * helper haye mobile-only e bala rad mikonan (`021…` maghz e
     * `9XXXXXXXXX` nist).
     *
     * `0XX…` mishe `+98XX…`. Vorudi e khali -> `''`.
     *
     * ⚠️ Scheme e `tel:` ejbari-e: bedun esh browser href ro yek masir e
     * nesbi mikhune va link HARGEZ zang nemizane.
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
     * Group-bandi baraye namayesh: `021-28421130`. Har chizi ke khat e sabet
     * e Tehran nist، khali (faghat ragham) bar migarde.
     */
    public static function pretty(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', self::toLatinDigits((string) $phone)) ?? '';

        return str_starts_with($digits, '021') && strlen($digits) > 3
            ? '021-'.substr($digits, 3)
            : $digits;
    }

    /**
     * Mask kardan baraye LOG va UI: `+98912***4567`.
     *
     * 🔴 Shomare ye mobile data ye shakhsi-e. Log e khali ye yek shomare ye
     * kamel ya'ni har kasi ke be log dastresi dare، lista ye moshtari ha ro
     * dare. `SendOtpAction` bayad az hamin estefade kone.
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
