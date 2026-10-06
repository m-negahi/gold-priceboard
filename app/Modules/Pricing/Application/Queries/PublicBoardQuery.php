<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\BoardDto;
use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Shekl dadan e tablo baraye site e OMUMI (Phase 5).
 *
 * Bar khalaf e panel، inja faghat provider haye `on_board` miyan va har
 * symbol yek radif ast ba khune ye har provider + kamtarin/bishtarin/
 * miangin (asle «moghayese»). Khoruji array e sade-ast — Blade Eloquent ya
 * mantegh nemibine.
 */
final readonly class PublicBoardQuery
{
    public function __construct(
        private BoardQuery $board,
        private ProviderQuery $providers,
    ) {}

    /**
     * @return array{
     *   providers: array<int, array{key: string, name: string}>,
     *   rows: array<int, array<string, mixed>>,
     *   degraded: bool, version: int, spreadVersion: int, etag: string,
     *   updatedAt: string|null,
     * }
     */
    public function present(): array
    {
        $configs = $this->providers->allActive();
        $board = $this->board->board($configs);

        $providerNames = $this->providerNames();
        $providerKeys = [];
        $rows = [];

        foreach ($board->bySymbol() as $symbol => $symbolRows) {
            $instrument = Instrument::tryFrom((string) $symbol);

            if ($instrument === null) {
                continue;
            }

            $cells = [];
            $prices = [];

            foreach ($symbolRows as $row) {
                $providerKeys[$row->providerKey] = true;
                $cells[$row->providerKey] = $this->cell($row);

                if ($row->sell->isPositive()) {
                    $prices[] = $row->sell->minor;
                }
            }

            $divisor = $instrument->quote()->displayDivisor();
            $precision = $instrument->quote()->displayPrecision();
            $minimum = $prices === [] ? null : new Price(min($prices), $instrument->quote());
            $maximum = $prices === [] ? null : new Price(max($prices), $instrument->quote());
            $sum = array_reduce($prices, static fn (string $total, int $minor): string => bcadd($total, (string) $minor, 0), '0');
            $average = $prices === [] ? null : bcdiv($sum, (string) count($prices), $instrument->quote()->scale() + 6);

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'metal' => $instrument->metal()?->value,
                'groupLabel' => $instrument->groupLabel(),
                'icon' => $instrument->icon(),
                'unit' => $instrument->unit()->value,
                'quote' => $instrument->quote()->value,
                'quoteLabel' => $instrument->quote()->displayLabel(),
                'divisor' => $divisor,
                'precision' => $precision,
                'cells' => $cells,
                // Moghayese: kamtarin / bishtarin / miangin (major unit).
                'min' => $minimum?->toFloat(),
                'max' => $maximum?->toFloat(),
                'avg' => $average === null ? null : (float) bcdiv($average, bcpow('10', (string) $instrument->quote()->scale()), $instrument->quote()->scale() + 6),
                'minDisplay' => $minimum?->display(),
                'maxDisplay' => $maximum?->display(),
                'avgDisplay' => $prices === [] ? null : $this->averageDisplay($sum, count($prices), $instrument->quote()),
                'count' => count($prices),
            ];
        }

        // Faghat provider hayi ke vaghe'an radif daran (tartib e sort).
        $providers = [];

        foreach ($configs as $key => $config) {
            if (isset($providerKeys[$key])) {
                $providers[] = ['key' => $key, 'name' => $providerNames[$key] ?? $key];
            }
        }

        return [
            'providers' => $providers,
            'rows' => $rows,
            'degraded' => $board->degraded,
            'version' => $board->version,
            'spreadVersion' => $board->spreadVersion,
            'etag' => $board->etag(),
            'updatedAt' => $this->latestQuotedAt($board),
        ];
    }

    /** @return array<string, mixed> */
    private function cell(BoardRowDto $row): array
    {
        return [
            'sell' => $row->sell->toFloat(),
            'buy' => $row->buy->toFloat(),
            'sellDisplay' => $row->sell->display(),
            'buyDisplay' => $row->buy->display(),
            'changePercent' => $row->changePercent,
            'direction' => $row->direction->value,
            'isStale' => $row->isStale,
            'isDerived' => $row->isDerived,
            'ageSeconds' => $row->ageSeconds,
        ];
    }

    /** Miangin e SSR/poll az jam' e minor, bedun float ya double-rounding. */
    private function averageDisplay(string $sumMinor, int $count, QuoteCurrency $quote): string
    {
        $precision = $quote->displayPrecision();
        $scaled = bcmul($sumMinor, bcpow('10', (string) $precision), 0);
        $divisor = bcmul((string) $count, bcpow('10', (string) $quote->scale()), 0);
        $rounded = bcdiv(bcadd(bcmul($scaled, '2', 0), $divisor, 0), bcmul($divisor, '2', 0), 0);
        $digits = str_pad($rounded, $precision + 1, '0', STR_PAD_LEFT);
        $integer = $precision > 0 ? substr($digits, 0, -$precision) : $digits;
        $fraction = $precision > 0 ? '.'.substr($digits, -$precision) : '';

        return strrev(implode(',', str_split(strrev($integer), 3))).$fraction;
    }

    private function latestQuotedAt(BoardDto $board): ?string
    {
        $latest = null;

        foreach ($board->rows as $row) {
            if ($latest === null || $row->quotedAt > $latest) {
                $latest = $row->quotedAt;
            }
        }

        return $latest;
    }

    /** @return array<string, string> */
    private function providerNames(): array
    {
        $names = [];

        foreach ($this->providers->panelRows() as $row) {
            $names[$row['key']] = $row['name'];
        }

        return $names;
    }
}
