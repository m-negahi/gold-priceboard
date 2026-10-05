<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Pipeline\Stages;

use App\Modules\Pricing\Application\Dto\RejectionDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Pipeline\Stage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\StagePolicy;
use App\Modules\Pricing\Domain\Services\PriceSanity;
use App\Modules\Pricing\Domain\Services\QuoteEnricher;

/**
 * Darvaze: tazegi + salamat e meghdar.
 *
 * ── Chera staleness INJA-st va na tu Source ───────────────────────
 * Tazegi yek POLICY ast ke be (provider, instrument) vabaste ast، na yek
 * vizhegi ye payload. `ons` e tgju faghat RUZANE be-rooz mishe dar hali ke
 * `geram18` har daghighe — va hamun `ons` az yek provider e digar mitune
 * zende bashe. Source faghat `upstreamAt` ro zamime mikone؛ tasmim inja
 * gerefte mishe، jayi ke ham `Instrument` ro darim ham config e provider ro.
 *
 * Har chizi ke inja rad beshe HICH JA neveshte nemishe — na Redis، na
 * Postgres، na Influx. Yek meghdar e kharab ke be `candles_1d` berese yek
 * dorugh e DAEMI ast.
 */
final readonly class ValidateStage implements Stage
{
    /** `ts` e ayande = clock skew ya timestamp e kharab e upstream. */
    private const MAX_FUTURE_SECONDS = 120;

    /** @param array<string, float> $maxDeviation arz => darsad */
    public function __construct(
        private array $maxDeviation = [],
        private QuoteEnricher $enricher = new QuoteEnricher,
    ) {}

    public function name(): string
    {
        return 'validate';
    }

    public function policy(): StagePolicy
    {
        return StagePolicy::Isolated;
    }

    public function handle(TickContext $context): void
    {
        $accepted = [];

        foreach ($context->raw as $quote) {
            $key = SnapshotDto::makeKey($quote->providerKey, $quote->instrument, $quote->side);
            $previous = $context->previous[$key] ?? null;
            $config = $context->config($quote->providerKey);

            // ── tazegi ──────────────────────────────────────────
            if ($quote->upstreamAt !== null) {
                $lag = $context->at->signedLagSecondsFrom($quote->upstreamAt);
                $limit = $config->freshnessFor($quote->instrument);

                if ($lag > $limit) {
                    $context->reject(new RejectionDto(
                        $quote->providerKey, $quote->instrument, $quote->side,
                        "stale:{$lag}s", $quote->price->minor, $lag,
                    ));

                    continue;
                }

                if ($lag < -self::MAX_FUTURE_SECONDS) {
                    $context->reject(new RejectionDto(
                        $quote->providerKey, $quote->instrument, $quote->side,
                        "future:{$lag}s", $quote->price->minor, $lag,
                    ));

                    continue;
                }
            }

            // ── salamat e meghdar ───────────────────────────────
            $verdict = PriceSanity::check(
                $quote->instrument,
                $quote->price,
                $previous?->price,
                $previous?->pendingOutlier,
                $this->deviationFor($quote->instrument->quote()),
            );

            if ($verdict->rejected()) {
                $context->reject(new RejectionDto(
                    $quote->providerKey, $quote->instrument, $quote->side,
                    (string) $verdict->reason, $quote->price->minor,
                ));

                // Outlier e moallagh: meghdar negah dashte mishe ta agar tick
                // e ba'di ta'eed esh kone، paziresh beshe. Gheymat e namayesh
                // dade shode DAST NEMIKHORE.
                if ($verdict->holdAsOutlier && $previous !== null) {
                    $held = $this->enricher->holdOutlier($previous, $quote->price);

                    $context->previous[$key] = $held;
                    // Bayad PAYEDAR beshe — vagarna tick e ba'di nemidune
                    // chi rad shode bud va ghanun e ta'eed hargez kar nemikone.
                    $context->holds[] = $held;
                }

                continue;
            }

            $accepted[] = $quote;
        }

        $context->raw = $accepted;
    }

    private function deviationFor(QuoteCurrency $quote): float
    {
        return $this->maxDeviation[$quote->value] ?? ($quote === QuoteCurrency::USD ? 5.0 : 10.0);
    }
}
