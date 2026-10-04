<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use Carbon\CarbonImmutable;

/**
 * Zaman be onvan e yek vabastegi.
 *
 * Chera: `Domain/` hich chiz e Laravel nemibine، pas `now()` mamnu' ast. Va
 * mohem tar — bedun e in، test e staleness va test e «outlier e ta'eed-shode»
 * be sa'at e vaghei vabaste mishan va ba'zi vaghta random fail mikonan.
 */
interface ClockPort
{
    public function now(): CarbonImmutable;

    /** Zaman e fe'li، floor-shode ru grid e tick. */
    public function tick(int $gridSeconds = 15): TickTime;
}
