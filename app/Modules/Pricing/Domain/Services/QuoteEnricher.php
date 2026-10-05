<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use Carbon\CarbonImmutable;

/**
 * Tabdil e yek gheymat e KHAM be yek snapshot e kamel.
 *
 * ── Chera snapshot e ghabli ARGUMENT ast، na chizi ke khodesh mikhune ──
 * Tu projeh ye marja' hamin kar dakhel e driver anjam mishod va driver
 * mostaghim Redis mikhund o minevesht. Natije: driver ghabl e test bedun e
 * Redis nabud، va «motarjem» be «store» tabdil shode bud.
 *
 * Inja hame chiz pass dade mishe، pas in class kamelan mahz ast.
 *
 * ── Rooz be vaght e Tehran ──────────────────────────────────────────
 * `dayOpen`/`dayHigh`/`dayLow` ba rooz e **Tehran** reset mishan، na UTC.
 * Nime-shab e UTC = 03:30 e Tehran؛ agar un ro meyar begirim، «بیشترین
 * امروز» har rooz sa'at e 3:30 e sobh reset mishe — vasat e shab e kari.
 */
final readonly class QuoteEnricher
{
    private const TIMEZONE = 'Asia/Tehran';

    public function enrich(RawQuoteDto $raw, ?SnapshotDto $previous, CarbonImmutable $tickAt): SnapshotDto
    {
        $sameDay = $previous !== null && $this->sameTehranDay($previous->quotedAt, $tickAt);

        // Rooz e jadid (ya avvalin snapshot) -> band az hamin gheymat shoru' mishe.
        $dayOpen = $sameDay ? ($previous->dayOpen ?? $raw->price) : $raw->price;

        $dayHigh = $sameDay
            ? Price::max($previous->dayHigh ?? $raw->price, $raw->price)
            : $raw->price;

        $dayLow = $sameDay
            ? Price::min($previous->dayLow ?? $raw->price, $raw->price)
            : $raw->price;

        // Band-i ke khod e upstream mide az mal e ma behtar ast — un kol e
        // rooz ro dide، ma faghat az vaghti ke bala umadim.
        if ($raw->dayHigh !== null) {
            $dayHigh = Price::max($dayHigh, $raw->dayHigh);
        }

        if ($raw->dayLow !== null) {
            $dayLow = Price::min($dayLow, $raw->dayLow);
        }

        return new SnapshotDto(
            providerKey: $raw->providerKey,
            instrument: $raw->instrument,
            side: $raw->side,
            price: $raw->price,
            quotedAt: $tickAt,
            previous: $this->previousPrice($raw, $previous),
            dayOpen: $dayOpen,
            dayHigh: $dayHigh,
            dayLow: $dayLow,
            changePercent: $raw->upstreamChangePercent ?? $raw->price->changePercentFrom($dayOpen),
            direction: $this->direction($raw, $previous),
            upstreamAt: $raw->upstreamAt,
            upstreamLagSeconds: $this->lag($raw, $tickAt),
            // Outlier e moallagh ba yek gheymat e PAZIRESH-SHODE pak mishe.
            pendingOutlier: null,
        );
    }

    /**
     * Snapshot e ghabli ba yek outlier e moallagh negah dashte mishe.
     *
     * Vaghti `PriceSanity` yek meghdar ro rad mikone vali mige negah esh dar،
     * gheymat e ghabli bayad **dast nakhorde** bemune — tanha chizi ke avaz
     * mishe `pendingOutlier` ast.
     */
    public function holdOutlier(SnapshotDto $previous, Price $outlier): SnapshotDto
    {
        return new SnapshotDto(
            providerKey: $previous->providerKey,
            instrument: $previous->instrument,
            side: $previous->side,
            price: $previous->price,
            quotedAt: $previous->quotedAt,
            previous: $previous->previous,
            dayOpen: $previous->dayOpen,
            dayHigh: $previous->dayHigh,
            dayLow: $previous->dayLow,
            changePercent: $previous->changePercent,
            direction: $previous->direction,
            upstreamAt: $previous->upstreamAt,
            upstreamLagSeconds: $previous->upstreamLagSeconds,
            pendingOutlier: $outlier,
        );
    }

    /**
     * `previous` akharin gheymat e MOTEFAVET ast، na akharin tick.
     *
     * Chera: `direction` bayad «akharin harekat» ro neshun bede. Agar har
     * tick ro paye begirim، tu bazaar e aram hame chiz `flat` mishe va arrow
     * ha khamoosh mimunan — hamun chizi ke tu projeh ye marja' ettefagh oftad.
     */
    private function previousPrice(RawQuoteDto $raw, ?SnapshotDto $previous): ?Price
    {
        if ($previous === null) {
            return null;
        }

        return $previous->price->equals($raw->price)
            ? $previous->previous
            : $previous->price;
    }

    private function direction(RawQuoteDto $raw, ?SnapshotDto $previous): Direction
    {
        if ($previous === null) {
            return Direction::Flat;
        }

        if ($previous->price->equals($raw->price)) {
            // Gheymat avaz nashode -> jahat e ghabli hefz mishe، na `flat`.
            return $previous->direction;
        }

        return Direction::between($previous->price->minor, $raw->price->minor);
    }

    private function lag(RawQuoteDto $raw, CarbonImmutable $tickAt): int
    {
        if ($raw->upstreamAt === null) {
            return 0;
        }

        return max(0, $tickAt->getTimestamp() - $raw->upstreamAt->getTimestamp());
    }

    private function sameTehranDay(CarbonImmutable $a, CarbonImmutable $b): bool
    {
        return $a->setTimezone(self::TIMEZONE)->toDateString()
            === $b->setTimezone(self::TIMEZONE)->toDateString();
    }
}
