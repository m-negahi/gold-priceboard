<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * 🔴 Bug e vaghei (dide shod tu screenshot e TV — P5-03): `Instrument::icon()`
 * esm haye Tabler (`coin`، `world`، `currency-dollar`، …) mide vali
 * `<x-icon>` faghat yek set e mahdud dare va esm e nashenakhte SAKET be
 * `dot` miofte — tablo va TV hame ye icon ha ro yek noghte neshun midadan.
 */
final class InstrumentIconTest extends TestCase
{
    public function test_every_instrument_icon_exists_in_the_icon_set(): void
    {
        $dot = Blade::render('<x-icon name="dot" />');

        foreach (Instrument::cases() as $instrument) {
            $svg = Blade::render('<x-icon :name="$name" />', ['name' => $instrument->icon()]);

            self::assertNotSame($dot, $svg, "icon e «{$instrument->icon()}» ({$instrument->value}) tu <x-icon> nist.");
        }
    }
}
