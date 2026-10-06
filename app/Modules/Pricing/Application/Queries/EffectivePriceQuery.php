<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Infrastructure\Sources\ManualQuoteSource;

/**
 * Gheymat e MO'ASSER baraye API/feed-i ke mifrooshim (Phase 4/5 — khaste ye
 * sahebe proje).
 *
 * ── Mantegh ─────────────────────────────────────────────────────────
 * Baraye har symbol، yek gheymat entekhab mishe ba OLAVIAT:
 *   1. Provider ha be tartib e `sort` (kamtar = olaviat e balatar).
 *   2. Avvalin provider-i ke gheymat esh TAZE (gheyr e stale) ast.
 *   3. Age HAME ye provider haye vaghei stale/ghat' budan -> MANUAL.
 *   4. Age manual ham nabud -> taze tarin e mojud (ba flag e stale).
 *
 * ── 🔴 ALARM ────────────────────────────────────────────────────────
 * Vaghti feed majbur she be `manual` ya be yek gheymat e `stale` tekye
 * kone، `usingFallback` روشن mishe — admin bayad befahme ke provider haye
 * asli ghat'-and va gheymat e dasti/ghadimi serve mishe.
 */
final readonly class EffectivePriceQuery
{
    public function __construct(
        private BoardQuery $board,
        private ProviderQuery $providers,
    ) {}

    /**
     * @return array{
     *   symbols: array<int, array<string, mixed>>,
     *   alarm: bool, fallbackCount: int,
     *   version: int, spreadVersion: int, degraded: bool,
     * }
     */
    public function feed(): array
    {
        $configs = $this->providers->allActive();
        $board = $this->board->board($configs);

        // Olaviat: sort e provider (kamtar = balatar).
        $priority = [];
        $i = 0;

        foreach ($configs as $key => $config) {
            $priority[$key] = $i++;
        }

        /** @var array<string, array<int, BoardRowDto>> $bySymbol */
        $bySymbol = [];

        foreach ($board->rows as $row) {
            // Faghat side e namayande (last/ask) baraye feed e mo'asser.
            if (! in_array($row->side->value, ['last', 'ask'], true)) {
                continue;
            }

            $bySymbol[$row->instrument->value][] = $row;
        }

        $symbols = [];
        $fallbackCount = 0;

        foreach ($bySymbol as $symbol => $rows) {
            // Tartib ba olaviat e provider.
            usort($rows, static fn (BoardRowDto $a, BoardRowDto $b): int => ($priority[$a->providerKey] ?? 99) <=> ($priority[$b->providerKey] ?? 99));

            [$chosen, $usingFallback] = $this->choose($rows);

            if ($chosen === null) {
                continue;
            }

            if ($usingFallback) {
                $fallbackCount++;
            }

            $symbols[] = [
                'symbol' => $chosen->instrument->value,
                'label' => $chosen->instrument->label(),
                'provider' => $chosen->providerKey,
                'sell' => $chosen->sell->minor,
                'buy' => $chosen->buy->minor,
                'scale' => $chosen->instrument->quote()->scale(),
                'quote' => $chosen->instrument->quote()->value,
                'displayDivisor' => $chosen->instrument->quote()->displayDivisor(),
                'changePercent' => $chosen->changePercent,
                'direction' => $chosen->direction->value,
                'isStale' => $chosen->isStale,
                'isDerived' => $chosen->isDerived,
                'usingFallback' => $usingFallback,
                'quotedAt' => $chosen->quotedAt,
            ];
        }

        return [
            'symbols' => $symbols,
            'alarm' => $fallbackCount > 0,
            'fallbackCount' => $fallbackCount,
            'version' => $board->version,
            'spreadVersion' => $board->spreadVersion,
            'degraded' => $board->degraded,
        ];
    }

    /**
     * Entekhab e radif ba olaviat.
     *
     * @param  array<int, BoardRowDto>  $rows  ba olaviat sort-shode
     * @return array{0: BoardRowDto|null, 1: bool} [chosen, usingFallback]
     */
    private function choose(array $rows): array
    {
        // 1. Avvalin TAZE ye gheyr-manual.
        foreach ($rows as $row) {
            if (! $row->isStale && $row->providerKey !== ManualQuoteSource::KEY) {
                return [$row, false];
            }
        }

        // 2. Manual (taze ya na — fallback e amdi).
        foreach ($rows as $row) {
            if ($row->providerKey === ManualQuoteSource::KEY) {
                return [$row, true];
            }
        }

        // 3. Taze tarin e mojud (hame stale budan، manual ham nabud).
        return [$rows[0] ?? null, true];
    }
}
