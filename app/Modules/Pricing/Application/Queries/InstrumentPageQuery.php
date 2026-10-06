<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Carbon\CarbonImmutable;

/**
 * Safhe ye yek instrument (`/price/{symbol}`، P10-13) — hamun data ye
 * tablo ye omumi، faghat baraye YEK symbol + amar e moghayese + instrument
 * haye ham-gruh. Khoruji array e sade-ast؛ Blade hich mantegh nemibine.
 *
 * Gheymat ha mesle tablo TOMAN-and (`PublicBoardQuery` zarib ro mide).
 */
final readonly class InstrumentPageQuery
{
    public function __construct(private PublicBoardQuery $board) {}

    /** @return array<string, mixed> */
    public function present(Instrument $instrument): array
    {
        $board = $this->board->present();
        $row = null;

        foreach ($board['rows'] as $candidate) {
            if ($candidate['symbol'] === $instrument->value) {
                $row = $candidate;

                break;
            }
        }

        $cells = [];

        foreach ($board['providers'] as $provider) {
            $cell = $row['cells'][$provider['key']] ?? null;

            if ($cell === null) {
                continue;
            }

            $cells[] = ['key' => $provider['key'], 'name' => $provider['name']] + $cell;
        }

        // Ekhtelaf e manba' ha: (bishtarin − kamtarin) / kamtarin.
        $spreadPct = null;

        if ($row !== null && $row['min'] !== null && $row['max'] !== null && $row['min'] > 0) {
            $spreadPct = number_format(($row['max'] - $row['min']) / $row['min'] * 100, 2);
        }

        return [
            'symbol' => $instrument->value,
            'label' => $instrument->label(),
            'metal' => $instrument->metal()?->value,
            'groupLabel' => $instrument->groupLabel(),
            'icon' => $instrument->icon(),
            'unitLabel' => $instrument->unit()->label(),
            'quoteLabel' => $row['quoteLabel'] ?? ($instrument->quote()->value === 'IRR' ? 'تومان' : $instrument->quote()->displayLabel()),
            'divisor' => $row['divisor'] ?? ($instrument->quote()->displayDivisor() * Money::tomanDivisor($instrument->quote()->value)),
            'precision' => $row['precision'] ?? $instrument->quote()->displayPrecision(),
            'hasPrices' => $cells !== [],
            'cells' => $cells,
            'minDisplay' => $row['minDisplay'] ?? null,
            'maxDisplay' => $row['maxDisplay'] ?? null,
            'avgDisplay' => $row['avgDisplay'] ?? null,
            'count' => $row['count'] ?? 0,
            'spreadPct' => $spreadPct,
            'updatedAt' => $board['updatedAt'] === null ? null : Jalali::dateTime(CarbonImmutable::parse($board['updatedAt'])),
            'degraded' => $board['degraded'],
            'related' => $this->related($instrument),
        ];
    }

    /**
     * Instrument haye ham-gruh (hamun felez) — link e dakheli baraye SEO va
     * navigation.
     *
     * @return list<array{symbol: string, label: string}>
     */
    private function related(Instrument $instrument): array
    {
        $related = [];

        foreach (Instrument::cases() as $other) {
            if ($other === $instrument || $other->metal() !== $instrument->metal()) {
                continue;
            }

            $related[] = ['symbol' => $other->value, 'label' => $other->label()];
        }

        return $related;
    }
}
