<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Jens e provider — ta'in mikone kharid/foroosh esh VAGHEI-e ya MOSHTAGH.
 *
 * 🔴 In farq faghat barchasb nist.
 *
 *   `reference` -> site e nerkh (tgju، estjt). YEK gheymat mide. Kharid va
 *                  foroosh-i ke ma neshun midim arithmetic e KHODEMUN ru un
 *                  yek adad ast، pas bayad `is_derived` bokhore.
 *   `platform`  -> maghaze/platform e vaghei (wallgold، taline، …) ke khodesh
 *                  nerkh e kharid va foroosh e vaghei e'lam mikone. In ha
 *                  nabayad marker e «محاسبه‌شده» begiran.
 *
 * Ru site-i ke kol e arzesh esh E'TEMAD ast، neshun dadan e arithmetic e
 * khodemun ba label e «nerkh e folan maghaze» daghighan chizi-e ke shekayat
 * misaze. Ghablan in meghdar yek string e sade bud va `BoardQuery` ba
 * `!== 'platform'` moghayese mikard — yek ghalat e tuype tu panel hame ye
 * radif ha ro «moshtagh» mikard bedun e hich khata.
 */
enum ProviderKind: string
{
    use EnumTrait;

    case Reference = 'reference';
    case Platform = 'platform';

    /** Aya kharid/foroosh e in provider khodesh vaghei-e? */
    public function hasRealSides(): bool
    {
        return $this === self::Platform;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Reference->value => 'سایت نرخ',
            self::Platform->value => 'پلتفرم معاملاتی',
        ];
    }
}
