<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/** Jahat e harekat e gheymat nesbat be meghdar e ghabli. */
enum Direction: string
{
    use EnumTrait;

    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';

    public static function between(int $previous, int $current): self
    {
        return match (true) {
            $current > $previous => self::Up,
            $current < $previous => self::Down,
            default => self::Flat,
        };
    }

    /**
     * Token e rang. 🔴 `brand-*` NIST — tu bazaar sabz ya'ni so'ud va rang e
     * brand e ma ham sabz ast; agar yeki bashan tu board gij-konande mishe.
     */
    public function colorToken(): string
    {
        return match ($this) {
            self::Up => 'up',
            self::Down => 'down',
            self::Flat => 'flat',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Up => 'arrow-up-right',
            self::Down => 'arrow-down-right',
            self::Flat => 'minus',
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Up->value => 'صعودی',
            self::Down->value => 'نزولی',
            self::Flat->value => 'بدون تغییر',
        ];
    }
}
