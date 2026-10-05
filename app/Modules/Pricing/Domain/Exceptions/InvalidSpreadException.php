<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Exceptions;

/** Tanzim e spread ghabl e ghabul nist. */
final class InvalidSpreadException extends PricingException
{
    /**
     * 🔴 Symbol e nashenas bad tarin halat ast: radif zakhire mishe va
     * HICHVAGHT resolve nemishe (chun `SpreadQuery::resolve()` ba
     * `$instrument->value` migarde). Admin spread ro «set karde» va tablo
     * taghiri nakarde — bedun e hich khata. Inja rad mishe.
     */
    public static function unknownSymbol(string $symbol): self
    {
        return new self(
            "نماد «{$symbol}» شناخته‌شده نیست؛ اسپرد برای آن ذخیره نمی‌شود.",
        );
    }

    /**
     * Darsad e <= -100 gheymat ro sefr ya manfi mikone.
     *
     * `SpreadPricer` (invariant C) tu in halat be gheymat e KHAM fallback
     * mikone، pas service nemimire — vali yek tanzim e zakhire-shode ke
     * hichvaght e'mal nemishe yek bomb e saket ast: admin fekr mikone spread
     * dare، tablo gheymat e kham neshun mide. Pas tu neveshtan rad mishe.
     */
    public static function percentOutOfRange(string $side, float $value): self
    {
        return new self(
            "اسپرد درصدی {$side} باید بزرگ‌تر از ‎-۱۰۰ و کوچک‌تر از ۱۰۰ باشد (مقدار داده‌شده: {$value}).",
        );
    }

    /**
     * Do radif baraye yek symbol tu yek submit.
     *
     * Last-wins e saket ya'ni admin yek meghdar ro tu form mibine va yek
     * meghdar e DIGE zakhire mishe.
     */
    public static function duplicateSymbol(string $symbol): self
    {
        return new self("نماد «{$symbol}» بیش از یک بار در این فرم آمده است.");
    }

    public static function notFinite(string $side): self
    {
        return new self("مقدار اسپرد {$side} عددی معتبر نیست.");
    }

    public static function freshnessOutOfRange(int $seconds): self
    {
        return new self("مهلت تازگی باید بین ۱ ثانیه و ۷ روز باشد (مقدار داده‌شده: {$seconds}).");
    }
}
