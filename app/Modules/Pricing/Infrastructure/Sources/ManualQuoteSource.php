<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Sources;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SourceRequestDto;
use App\Modules\Pricing\Application\Dto\SourceResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Source e DASTI (fallback) — Phase 4/5.
 *
 * ── Chera va chetor ─────────────────────────────────────────────────
 * Age hame ye provider ha ghat' shan، in manba' tablo/API ro zende negah
 * midare: gheymat ha ro admin dasti gozashte (ya az yek source seed shode).
 *
 * ── 🔴 upstreamAt = null (AMDAN) ────────────────────────────────────
 * Mesl e estjt، timestamp nemide — pas check e tazegi ye `ValidateStage`
 * barash ejra NEMISHE va HARGEZ «kohne» rad nemishe. In asle fallback e:
 * akharin chizi ke hast، hamishe neshun dade mishe. «Kohne budan» e gheymat
 * e dasti joda — tavasot e `ManualPriceQuery` (ru `updated_at`) — ALARM
 * mishe، na radd.
 *
 * ── Pure translator ─────────────────────────────────────────────────
 * Gheymat ha be sorat e ARGUMENT tazrigh mishan (SourceManager az DB
 * mikhune va pass mide) — in class khodesh DB nemibine، pas test-pazir
 * mimune.
 */
final readonly class ManualQuoteSource implements QuoteSourcePort
{
    public const KEY = 'manual';

    /** @param array<string, array{minor: int, quote: string}> $prices symbol => {minor, quote} */
    public function __construct(private array $prices = []) {}

    public function key(): string
    {
        return self::KEY;
    }

    /** Source e dasti HTTP nadare — `FetchStage` mostaghim `fetch()` ro seda mizane. */
    public function request(): ?SourceRequestDto
    {
        return null;
    }

    /** @param array<string, mixed> $payload */
    public function parse(array $payload): SourceResultDto
    {
        return $this->fetch();
    }

    public function fetch(): SourceResultDto
    {
        $quotes = [];

        foreach ($this->prices as $symbol => $row) {
            $instrument = Instrument::tryFrom((string) $symbol);

            if ($instrument === null) {
                continue;
            }

            $quote = QuoteCurrency::tryFrom((string) ($row['quote'] ?? ''))
                ?? $instrument->quote();
            $minor = (int) ($row['minor'] ?? 0);

            if ($minor <= 0) {
                continue;
            }

            $quotes[] = new RawQuoteDto(
                providerKey: self::KEY,
                instrument: $instrument,
                side: Side::Last,
                price: new Price($minor, $quote),
                // 🔴 Hargez bayat nemishe — fallback e akhar ast.
                upstreamAt: null,
            );
        }

        return new SourceResultDto(
            quotes: $quotes,
            payloadHash: md5((string) json_encode($this->prices)),
            payloadBytes: 0,
        );
    }
}
