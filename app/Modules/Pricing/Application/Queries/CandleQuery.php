<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Application\Dto\CandleDto;
use App\Modules\Pricing\Application\Dto\CandleRequestDto;
use App\Modules\Pricing\Application\Dto\CandleSeriesDto;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\Ports\TimeSeriesPort;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Khundan e candle ha ba cache e DO TABAGHE.
 *
 * ── Do tabaghe ──────────────────────────────────────────────────────
 *   `fresh` -> TTL e motanaseb ba interval (m1 15s … d1 900s)
 *   `:last` -> 6 saat، faghat baraye vaghti ke Influx javab nemide
 *
 * Tabaghe ye `:last` ghat'i ye Influx ro be «bayat vali DUROST» tabdil
 * mikone jaye «chart e khali». Chart e khali be nazar e karbar ya'ni site
 * kharab ast؛ chart e yek-daghighe-bayat ya'ni hich.
 *
 * ── 🔴 `spreadVersion` tu kelid ────────────────────────────────────
 * Bedun esh، ba'd az taghir e spread، chart ta enghaza ye TTL gheymat e
 * ghadim ro neshun mide dar hali ke tablo jadid ro neshun mide — va karbar
 * un ro be onvan e bug gozaresh mikone.
 *
 * ── Chera `Repository` va na facade ────────────────────────────────
 * `Cache::` yek facade ast va in class tu laye ye Application-e. Ba
 * tazrigh e contract، test mitune yek array store bede va hich chizi be
 * Redis vasl nashe.
 */
final readonly class CandleQuery
{
    /** TTL e tabaghe ye `:last` — ghat'i ye tulani ro ham pushesh mide. */
    private const LAST_GOOD_TTL = 6 * 3_600;

    public function __construct(
        private TimeSeriesPort $series,
        private HotCachePort $hot,
        private SpreadQuery $spreads,
        private CacheRepository $cache,
    ) {}

    public function candles(CandleRequestDto $request): CandleSeriesDto
    {
        $spreadVersion = $this->spreadVersion();
        $key = $request->cacheKey($spreadVersion);
        $lastKey = $request->lastGoodCacheKey($spreadVersion);

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $this->series($request, $this->hydrate($cached), $spreadVersion);
        }

        try {
            $candles = $this->series->candles($request);
        } catch (Throwable) {
            // Influx ghat' ast -> akharin javab e salem.
            $fallback = $this->cache->get($lastKey);

            return $this->series(
                $request,
                is_array($fallback) ? $this->hydrate($fallback) : [],
                $spreadVersion,
                degraded: true,
            );
        }

        $raw = array_map(static fn (CandleDto $c): array => $c->toArray(), $candles);

        $this->remember($key, $raw, $request->interval->cacheSeconds());

        if ($candles !== []) {
            // Faghat javab e GHEYR e khali be onvan e «akharin salem» negah
            // dashte mishe — vagarna yek bazeh ye khali ye movaghat baraye
            // 6 saat be onvan e fallback mimune.
            $this->remember($lastKey, $raw, self::LAST_GOOD_TTL);
        }

        return $this->series($request, $candles, $spreadVersion);
    }

    /**
     * @param  array<int, CandleDto>  $candles
     */
    private function series(
        CandleRequestDto $request,
        array $candles,
        int $spreadVersion,
        bool $degraded = false,
    ): CandleSeriesDto {
        return new CandleSeriesDto(
            // 🔴 Chart BAYAD hamun spread e tablo ro begire — vagarna close e
            // akharin candle ba gheymat e tablo fargh mikone.
            candles: $this->applySpread($request, $candles),
            instrument: $request->instrument,
            interval: $request->interval,
            providerKey: $request->providerKey,
            spreadVersion: $spreadVersion,
            degraded: $degraded,
        );
    }

    /**
     * @param  array<int, CandleDto>  $candles
     * @return array<int, CandleDto>
     */
    private function applySpread(CandleRequestDto $request, array $candles): array
    {
        if ($candles === []) {
            return [];
        }

        try {
            $pair = SpreadQuery::resolve($this->spreads->map(), $request->providerKey, $request->instrument);
        } catch (Throwable) {
            return $candles;
        }

        // Samt e chart hamun samti-e ke darkhast shode: `bid` -> spread e
        // kharid، baghi -> spread e foroosh (hamun chizi ke ru tablo be
        // onvan e «foroosh» neshun dade mishe).
        $spread = $request->side === Side::Bid
            ? $pair->buy
            : $pair->sell;

        if ($spread->isNeutral()) {
            return $candles;
        }

        $rows = SpreadPricer::applySeries(
            array_map(static fn (CandleDto $c): array => $c->toArray(), $candles),
            $spread,
            $request->instrument->quote(),
        );

        return array_map(static fn (array $row): CandleDto => CandleDto::fromArray($row), $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, CandleDto>
     */
    private function hydrate(array $rows): array
    {
        return array_map(static fn (array $row): CandleDto => CandleDto::fromArray($row), $rows);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function remember(string $key, array $rows, int $seconds): void
    {
        try {
            $this->cache->put($key, $rows, $seconds);
        } catch (Throwable) {
            // Cache ghat' ast — chart kond tar mishe، vali kar mikone.
        }
    }

    private function spreadVersion(): int
    {
        try {
            return $this->hot->spreadVersion();
        } catch (Throwable) {
            return 0;
        }
    }
}
