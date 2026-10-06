<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\BoardDto;
use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Domain\Enums\Instrument;

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
                    $prices[] = $row->sell->toFloat();
                }
            }

            $divisor = $instrument->quote()->displayDivisor();
            $precision = $instrument->quote()->displayPrecision();

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'metal' => $instrument->metal()?->value,
                'icon' => $instrument->icon(),
                'unit' => $instrument->unit()->value,
                'quote' => $instrument->quote()->value,
                'divisor' => $divisor,
                'precision' => $precision,
                'cells' => $cells,
                // Moghayese: kamtarin / bishtarin / miangin (major unit).
                'min' => $prices === [] ? null : min($prices) / $divisor,
                'max' => $prices === [] ? null : max($prices) / $divisor,
                'avg' => $prices === [] ? null : array_sum($prices) / count($prices) / $divisor,
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
            'changePercent' => $row->changePercent,
            'direction' => $row->direction->value,
            'isStale' => $row->isStale,
            'isDerived' => $row->isDerived,
            'ageSeconds' => $row->ageSeconds,
        ];
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
