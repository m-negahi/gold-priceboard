<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Felez — ba kod haye standard e jahani (ISO 4217: `X` + element).
 * XAU = tala, XAG = noghre, XCU = mes.
 */
enum Metal: string
{
    use EnumTrait;

    case Gold = 'xau';
    case Silver = 'xag';
    case Copper = 'xcu';

    /** Token e rang tu design system (faghat icon va chip). */
    public function colorToken(): string
    {
        return match ($this) {
            self::Gold => 'metal-gold',
            self::Silver => 'metal-silver',
            self::Copper => 'metal-copper',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gold => 'coin',
            self::Silver => 'stack-2',
            self::Copper => 'circle-dot',
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Gold->value => 'طلا',
            self::Silver->value => 'نقره',
            self::Copper->value => 'مس',
        ];
    }
}
