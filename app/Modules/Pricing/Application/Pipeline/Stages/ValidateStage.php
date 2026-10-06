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
use App\Modules\Pricing\Domain\ValueObjects\Price;

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
        /**
         * Saghf e ekhtelaf ba provider haye digar baraye AVVALIN tick.
         * `0` ya'ni khamoosh.
         */
        private float $maxCrossProviderDeviation = PriceSanity::MAX_CROSS_PROVIDER_DEVIATION_PCT,
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
        $anchors = $context->trustAnchorKeys;

        foreach ($context->providers as $config) {
            if ($config->isTrustAnchor && $config->key !== 'manual') {
                $anchors[] = $config->key;
            }
        }

        // Anchor ghabl az baghi validate mishe, hatta age response ash akhar resid.
        if ($anchors !== []) {
            usort($context->raw, static fn ($a, $b): int => (int) in_array($b->providerKey, $anchors, true) <=> (int) in_array($a->providerKey, $anchors, true));
        }

        // Marja' haye cross-provider: gheymat e payedar e provider haye
        // DIGAR، be tafkik e instrument. Yek bar sakhte mishe va ba har
        // quote e paziresh-shode ye in tick ham por tar mishe.
        $references = $this->referenceIndex($context, $anchors);

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

            // ── 🔴 Cross-provider، FAGHAT baraye avvalin tick ───
            // `previous` nist -> check e enheraf nemitune ejra beshe، va
            // band e motlagh khata ye 10x ro nemigire. In tanha darvaze-i
            // ast ke un rakhne ro mibande.
            if ($previous === null && $quote->providerKey !== 'manual' && ! in_array($quote->providerKey, $anchors, true) && $this->maxCrossProviderDeviation > 0) {
                $trusted = $this->referencesFor($references, $quote->instrument->value, $quote->providerKey);

                if ($anchors !== [] && $trusted === []) {
                    $context->reject(new RejectionDto($quote->providerKey, $quote->instrument, $quote->side, 'trust_anchor:unavailable', $quote->price->minor));

                    continue;
                }
                $verdict = PriceSanity::crossProvider(
                    $quote->price,
                    $trusted,
                    $this->maxCrossProviderDeviation,
                );

                if ($verdict->rejected()) {
                    $context->reject(new RejectionDto(
                        $quote->providerKey, $quote->instrument, $quote->side,
                        (string) $verdict->reason, $quote->price->minor,
                    ));

                    continue;
                }
            }

            // Quote e paziresh-shode khodesh baraye baghi ye in tick marja'
            // mishe. Faghat tu halat e bootstrap asar dare (vaghti hanuz hich
            // gheymat e payedari nist)، vali hamun ja arzesh dare.
            if ($quote->providerKey !== 'manual' && ($anchors === [] || in_array($quote->providerKey, $anchors, true))) {
                $references[$quote->instrument->value][$quote->providerKey] = $quote->price;
            }

            $accepted[] = $quote;
        }

        $context->raw = $accepted;
    }

    /**
     * `instrument => providerKey => Price` az snapshot haye PAYEDAR.
     *
     * Faghat gheymat e payedar — na quote haye kham e hamin tick — chun un
     * ha ghablan az darvaze rad shodan. Moghayese kardan e yek gheymat e
     * na-sanjide ba yek gheymat e na-sanjide ye dige hich chizi ta'min
     * nemikone.
     *
     * @return array<string, array<string, Price>>
     */
    private function referenceIndex(TickContext $context, array $anchors): array
    {
        $index = [];

        foreach ($context->previous as $snapshot) {
            if ($snapshot->providerKey !== 'manual' && $snapshot->price->isPositive()
                && ($anchors === [] || in_array($snapshot->providerKey, $anchors, true))) {
                $index[$snapshot->instrument->value][$snapshot->providerKey] = $snapshot->price;
            }
        }

        return $index;
    }

    /**
     * Marja' ha baraye yek instrument — bedun e KHOD e un provider.
     *
     * Provider e khodesh kenar gozashte mishe chun `xau-melted-g-irr` do
     * radif dare (bid va ask) va moghayese ye bid e yek provider ba ask e
     * HAMUN provider hich chizi ro ta'eed nemikone — un faghat spread e
     * khodesh ast، va agar vahed esh ghalat bashe HAR DO radif be hamun
     * nesbat ghalat-and.
     *
     * ⚠️ In shart MOHAFEZATI-e، na load-bearing — va amdan test nashode.
     * Ba mutation check sanjidam: bardashtan esh hich test-i ro ghermez
     * nemikone، chun (a) `median` ba do marja' hargez natije ro
     * barnemigardune، va (b) yek provider aslan NEMITUNE chand radif e
     * ghalat ja bioftad vaghti yek marja' e durost vojud dare — avvalin
     * radif esh hamun ja rad mishe. Pas shart mimune (mafhumi durost ast:
     * provider nabayad khodesh ro ta'eed kone) vali eddea ye test-shode
     * budan esh nemikonim.
     *
     * @param  array<string, array<string, Price>>  $index
     * @return array<int, Price>
     */
    private function referencesFor(array $index, string $instrument, string $providerKey): array
    {
        $references = [];

        foreach ($index[$instrument] ?? [] as $key => $price) {
            if ($key !== $providerKey) {
                $references[] = $price;
            }
        }

        return $references;
    }

    private function deviationFor(QuoteCurrency $quote): float
    {
        return $this->maxDeviation[$quote->value] ?? ($quote === QuoteCurrency::USD ? 5.0 : 10.0);
    }
}
