<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Services\QuoteEnricher;
use Carbon\CarbonImmutable;

/**
 * Tabdil e gheymat e kham be snapshot (band e rooz، jahat، darsad e taghir).
 *
 * Ba'd az `Validate` miad: enrich kardan e chizi ke gharar ast rad beshe
 * faghat kar e ezafi-e — va badtar، `dayHigh` ro ba yek meghdar e kharab
 * aludeh mikone.
 */
final readonly class EnrichStage implements Stage
{
    public function __construct(private QuoteEnricher $enricher = new QuoteEnricher) {}

    public function name(): string
    {
        return 'enrich';
    }

    public function policy(): StagePolicy
    {
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        $tickAt = CarbonImmutable::createFromTimestampUTC($context->at->timestamp());

        foreach ($context->raw as $quote) {
            $key = SnapshotDto::makeKey($quote->providerKey, $quote->instrument, $quote->side);
            $previous = $context->previous[$key] ?? null;

            $snapshot = $this->enricher->enrich($quote, $previous, $tickAt);

            $context->snapshots[] = $snapshot;

            // FAGHAT taghir ha mirizan tu daftarche — tala shayad 200-2000
            // bar tu rooz harekat kone، na 5,760 bar.
            if ($snapshot->differsFrom($previous)) {
                $context->changed[] = $snapshot;
            }
        }
    }
}
