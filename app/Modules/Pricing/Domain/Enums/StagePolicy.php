<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/**
 * Policy e shekast e yek marhale ye pipeline.
 *
 * ── Chera sarih ─────────────────────────────────────────────────────
 * Tu projeh ye marja' hame ye in tasmim ha be sorat e try/catch haye
 * parakande tu yek command e 150 khati bud, va baraye fahmidan e «agar
 * Influx bimofte chi mishe» bayad kol e un file ro mikhundi.
 *
 * Inja har Stage policy esh ro E'LAM mikone va pipeline e'mal esh mikone.
 */
enum StagePolicy: string
{
    use EnumTrait;

    /** Shekast = tick motevaghef mishe. */
    case Critical = 'critical';

    /** Shekast = log + edame. Baghi ye marhale ha ejra mishan. */
    case Isolated = 'isolated';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Critical->value => 'بحرانی',
            self::Isolated->value => 'ایزوله',
        ];
    }
}
