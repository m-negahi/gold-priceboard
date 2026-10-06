<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\BoardDto;
use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Domain\Services\PriorityChain;
use App\Modules\Pricing\Infrastructure\Sources\ManualQuoteSource;

/**
 * Gheymat e MO'ASSER baraye API/feed-i ke mifrooshim (Phase 4/5 — khaste ye
 * sahebe proje).
 *
 * ── Mantegh ─────────────────────────────────────────────────────────
 * Baraye har symbol، yek gheymat entekhab mishe ba OLAVIAT:
 *   1. Zanjire ye `PriorityChain` e HAMUN symbol (P5-07): olaviat e
 *      sefareshi e admin، ya bedun esh tartib e `sort` e global.
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
        private SymbolPriorityQuery $priorities,
        private SymbolPriceSettingsQuery $settings,
        private ManualPriceQuery $manual,
        private SpreadQuery $spreads,
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
        [$bySymbol, $board] = $this->rankedRows();

        $symbols = [];
        $fallbackCount = 0;

        $settings = $this->settings->map();

        foreach ($bySymbol as $symbol => $rows) {
            $policy = $settings[$symbol] ?? SymbolPriceSettingsQuery::defaults();
            [$chosen, $usingFallback] = $this->choose($rows, $policy);

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
                'manualOnly' => $policy['manualOnly'],
                'quotedAt' => $chosen->quotedAt,
                // Chand manba' e VAGHEI (gheyr e manual) in symbol ro midan —
                // 0 ya 1 ya'ni ghat' e yek provider mostaghim mire ru backup.
                'sourceCount' => count(array_filter($rows, static fn (BoardRowDto $row): bool => $row->providerKey !== ManualQuoteSource::KEY)),
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
     * Avvalin radif e TAZE ye gheyr-manual e zanjire ye har symbol (P5-07).
     *
     * Masraf: «پر کردن بر اساس اولویت» e gheymat e dasti — «aval tgju، age
     * khata dad estjt، …». Symbol-i ke hich manba' e taze nadare TU KHORUJI
     * NIST: seed nabayad gheymat e dasti ro ba yek adad e bayat jaygozin kone.
     *
     * @return array<string, BoardRowDto> symbol -> radif
     */
    public function freshestByPriority(): array
    {
        [$bySymbol] = $this->rankedRows();
        $chosen = [];
        $settings = $this->settings->map();

        foreach ($bySymbol as $symbol => $rows) {
            if ($settings[$symbol]['manualOnly'] ?? false) {
                continue;
            }
            foreach ($rows as $row) {
                if (! $row->isStale && $row->providerKey !== ManualQuoteSource::KEY) {
                    $chosen[$symbol] = $row;

                    break;
                }
            }
        }

        return $chosen;
    }

    /**
     * Radif haye side e namayande (last/ask)، group be symbol، moratab ba
     * zanjire ye HAMUN symbol.
     *
     * @return array{0: array<string, list<BoardRowDto>>, 1: BoardDto}
     */
    private function rankedRows(): array
    {
        $configs = $this->providers->allActive();
        $board = $this->board->board($configs);

        // `allActive` be tartib e `sort` ast = tartib e global.
        $global = array_map('strval', array_keys($configs));
        $custom = $this->priorities->customMap();

        /** @var array<string, list<BoardRowDto>> $bySymbol */
        $bySymbol = [];

        foreach ($board->rows as $row) {
            // Faghat side e namayande (last/ask) baraye feed e mo'asser.
            if ($row->providerKey === ManualQuoteSource::KEY || ! isset($configs[$row->providerKey]) || ! in_array($row->side->value, ['last', 'ask'], true)) {
                continue;
            }

            $bySymbol[$row->instrument->value][] = $row;
        }

        $settings = $this->settings->map();
        $manualRows = $this->manual->effectiveRows($this->spreads->map());

        foreach ($manualRows as $symbol => $row) {
            if (! isset($configs['manual']) && ! ($settings[$symbol]['manualOnly'] ?? false)) {
                continue;
            }

            $bySymbol[$symbol] = array_values(array_filter($bySymbol[$symbol] ?? [], static fn (BoardRowDto $existing): bool => $existing->providerKey !== 'manual'));
            $bySymbol[$symbol][] = $row;
        }

        foreach ($bySymbol as $symbol => $rows) {
            $rank = PriorityChain::rank($global, $custom[$symbol] ?? []);
            usort($rows, static fn (BoardRowDto $a, BoardRowDto $b): int => ($rank[$a->providerKey] ?? PHP_INT_MAX) <=> ($rank[$b->providerKey] ?? PHP_INT_MAX));
            $bySymbol[$symbol] = $rows;
        }

        return [$bySymbol, $board];
    }

    /**
     * Entekhab e radif ba olaviat.
     *
     * @param  array<int, BoardRowDto>  $rows  ba olaviat sort-shode
     * @return array{0: BoardRowDto|null, 1: bool} [chosen, usingFallback]
     */
    private function choose(array $rows, array $policy): array
    {
        if ($policy['manualOnly']) {
            foreach ($rows as $row) {
                if ($row->providerKey === ManualQuoteSource::KEY) {
                    return [$row, false];
                }
            }

            // Admin manual-only ro entekhab karde; hich provider e jaygozin mojaz nist.
            return [null, false];
        }

        if (! $policy['manualFallbackEnabled']) {
            $rows = array_values(array_filter($rows, static fn (BoardRowDto $row): bool => $row->providerKey !== ManualQuoteSource::KEY));
        }

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
