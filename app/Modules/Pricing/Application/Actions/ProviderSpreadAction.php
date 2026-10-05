<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Application\Dto\SpreadInputDto;
use App\Modules\Pricing\Application\Dto\SpreadSyncResultDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\Exceptions\InvalidSpreadException;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpreadRevision;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Neveshtan e spread haye yek provider.
 *
 * ── Se kar ke bayad BA HAM anjam shan ──────────────────────────────
 *   (a) upsert e `provider_spreads`
 *   (b) yek radif tu `provider_spread_revisions` (append-only)
 *   (c) bump e `spreadVersion` -> batel kardan e cache e chart/API
 *
 * (a) va (b) tu YEK transaction e Postgres-and. (c) **nemitune** — Redis ast
 * va transaction e Postgres un ro rollback nemikone.
 *
 * ── 🔴 Chera bump BA'D az commit، na ghabl az un ───────────────────
 * Faz kon bump ghabl az neveshtan bashe:
 *   v=5 -> bump be 6 -> (yek reader spread e GHADIM ro mikhune va zire
 *   kelid e v6 cache mikone) -> ma spread e jadid ro minevisim.
 * Version hanuz 6 ast، pas un cache e ghalat ta akhar e TTL servis mishe.
 *
 * Ba'd az commit:
 *   neveshtan -> (reader e vasat، spread e jadid ro zire v5 cache mikone)
 *   -> bump be 6 -> har kelid avaz mishe -> miss -> dobare mohasebe.
 * Bad tarin halat yek cache miss ast، na yek javab e ghalat.
 *
 * ── Agar bump shekast bokhore ───────────────────────────────────────
 * `cacheInvalidated=false` bar migarde. `BoardQuery` cache nemikone pas
 * tablo FORI durost ast؛ faghat `CandleQuery` ta enghaza ye TTL e interval
 * javab e ghadim mide. Panel bayad hamin ro neshun bede — «zakhire shod vali
 * nemudar hanuz be-rooz nashode» hezar bar behtar az sakut ast.
 */
final readonly class ProviderSpreadAction
{
    /**
     * Hadaksar override e tazegi: yek HAFTE.
     *
     * ⚠️ Avval yek rooz (86,400) gozashtam va test e khodam un ro rad kard:
     * seeder baraye `xau-oz-usd`/`xag-oz-usd`/`xcu-lb-usd` e tgju **90,000**
     * saniye mizare، chun un symbol ha tu un feed faghat RUZANE be-rooz
     * mishan va `ts` eshun nime-shab e rooz e ghabl ast — ya'ni sen eshun
     * mitune az 24 saat rad kone. Yek rooz band e ghalat bud.
     *
     * Yek hafte saghf e manteghi-e: ba'd az un، yek gheymat dige gheymat
     * nist.
     */
    private const MAX_FRESHNESS_SECONDS = 604_800;

    public function __construct(
        private HotCachePort $cache,
        private ClockPort $clock,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Upsert e yek dast radif e spread.
     *
     * ⚠️ Radif haye NA-AMADE hazf NEMISHAN. Hazf faghat ba `remove()` e
     * sarih ettefagh mioftad. Yek `sync()` e pak-kon، yek submit e nime-kare
     * ye form (ya yek bug e Livewire) ro tabdil mikone be «hame ye override
     * ha raft» — chizi ke ta avvalin shekayat kasi motavajeh esh nemishe.
     *
     * @param  array<int, SpreadInputDto>  $rows
     */
    public function sync(Provider $provider, array $rows, ?string $changedBy = null): SpreadSyncResultDto
    {
        $this->guard($rows);

        $at = $this->clock->now();

        /** @var array<string, ProviderSpread> $existing */
        $existing = ProviderSpread::query()
            ->where('provider_id', $provider->id)
            ->get()
            ->keyBy('symbol')
            ->all();

        $created = [];
        $updated = [];
        $unchanged = [];
        $revisions = [];

        DB::transaction(function () use (
            $provider, $rows, $existing, $at, $changedBy,
            &$created, &$updated, &$unchanged, &$revisions,
        ): void {
            foreach ($rows as $row) {
                $current = $existing[$row->symbol] ?? null;

                if ($current !== null && $this->comparable($current) === $row->comparable()) {
                    // Hichi avaz nashode. Na revision، na bump.
                    //
                    // 🔴 In «optimization» nist: form e panel yek radif baraye
                    // HAR `Instrument::cases()` mide (15 ta). Bedun e in
                    // moghayese، har bar ke admin faghat yek symbol ro avaz
                    // kone، 15 revision e ja'li neveshte mishe va daftarche ye
                    // audit — ke tanha hadafesh «ki chi ro avaz kard» ast —
                    // bi-estefade mishe.
                    $unchanged[] = $row->symbol;

                    continue;
                }

                ProviderSpread::query()->updateOrCreate(
                    ['provider_id' => $provider->id, 'symbol' => $row->symbol],
                    [
                        'buy_mode' => $row->buyMode,
                        'buy_value' => $row->buyValue,
                        'sell_mode' => $row->sellMode,
                        'sell_value' => $row->sellValue,
                        'is_enabled' => $row->isEnabled,
                        'freshness_seconds' => $row->freshnessSeconds,
                        'sort' => $row->sort,
                    ],
                );

                $current === null ? $created[] = $row->symbol : $updated[] = $row->symbol;

                $revisions[] = [
                    'provider_id' => $provider->id,
                    'symbol' => $row->symbol,
                    'buy_mode' => $row->buyMode->value,
                    'buy_value' => $row->buyValue,
                    'sell_mode' => $row->sellMode->value,
                    'sell_value' => $row->sellValue,
                    'is_removal' => false,
                    'changed_by' => $changedBy,
                    'effective_from' => $at,
                    'created_at' => $at,
                ];
            }

            if ($revisions !== []) {
                // Yek `insert` e dast-jam'i — daftarche append-only ast pas
                // hich conflict-i momken nist.
                ProviderSpreadRevision::query()->insert($revisions);
            }
        });

        $result = new SpreadSyncResultDto(
            created: $created,
            updated: $updated,
            unchanged: $unchanged,
            revisions: count($revisions),
        );

        return $this->invalidate($result);
    }

    /**
     * Hazf e yek override.
     *
     * Ba'd az in، un (provider, symbol) be `*` (ya be sefr) fallback mikone —
     * ya'ni gheymat e namayesh dade shode AVAZ mishe. Pas hamun ghadr yek
     * «taghir» ast ke yek update hast va revision + bump mikhahe.
     */
    public function remove(Provider $provider, string $symbol, ?string $changedBy = null): SpreadSyncResultDto
    {
        $this->assertKnownSymbol($symbol);

        $at = $this->clock->now();
        $removed = [];
        $revisions = 0;

        DB::transaction(function () use ($provider, $symbol, $at, $changedBy, &$removed, &$revisions): void {
            $current = ProviderSpread::query()
                ->where('provider_id', $provider->id)
                ->where('symbol', $symbol)
                ->first();

            if ($current === null) {
                return;
            }

            // Meghdar haye zabt-shode HAMUN chizi-e ke ghabl az hazf dar
            // jarian bud — «in spread bud va bardashte shod».
            ProviderSpreadRevision::query()->create([
                'provider_id' => $provider->id,
                'symbol' => $symbol,
                'buy_mode' => $current->buy_mode->value,
                'buy_value' => $current->buy_value,
                'sell_mode' => $current->sell_mode->value,
                'sell_value' => $current->sell_value,
                'is_removal' => true,
                'changed_by' => $changedBy,
                'effective_from' => $at,
                'created_at' => $at,
            ]);

            $current->delete();

            $removed[] = $symbol;
            $revisions = 1;
        });

        return $this->invalidate(new SpreadSyncResultDto(removed: $removed, revisions: $revisions));
    }

    /**
     * Bump e `spreadVersion` — faghat agar chizi VAGHE'AN avaz shode.
     *
     * Bump e bi-dalil kol e cache e chart (hame ye provider ha × hame ye
     * symbol ha × 7 interval) ro ba ham batel mikone. Yek save e bi-taghir
     * nabayad un hazine ro bede.
     */
    private function invalidate(SpreadSyncResultDto $result): SpreadSyncResultDto
    {
        if (! $result->changed()) {
            return $result;
        }

        try {
            $version = $this->cache->bumpSpreadVersion();

            return new SpreadSyncResultDto(
                created: $result->created,
                updated: $result->updated,
                unchanged: $result->unchanged,
                removed: $result->removed,
                revisions: $result->revisions,
                spreadVersion: $version,
                cacheInvalidated: true,
            );
        } catch (Throwable $e) {
            // Spread ha zakhire shodan (commit shode). Faghat cache e nemudar
            // ta enghaza ye TTL ghadim mimune. In bayad DIDE she، na gheyb.
            $this->logger?->warning('pricing.spread_version_bump_failed', [
                'error' => $e->getMessage(),
            ]);

            return $result;
        }
    }

    /**
     * Check haye kol e dast — GHABL az transaction.
     *
     * @param  array<int, SpreadInputDto>  $rows
     */
    private function guard(array $rows): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $this->assertKnownSymbol($row->symbol);

            if (isset($seen[$row->symbol])) {
                throw InvalidSpreadException::duplicateSymbol($row->symbol);
            }

            $seen[$row->symbol] = true;

            $this->assertValue('خرید', $row->buyMode, $row->buyValue);
            $this->assertValue('فروش', $row->sellMode, $row->sellValue);

            if ($row->freshnessSeconds !== null
                && ($row->freshnessSeconds < 1 || $row->freshnessSeconds > self::MAX_FRESHNESS_SECONDS)) {
                throw InvalidSpreadException::freshnessOutOfRange($row->freshnessSeconds);
            }
        }
    }

    private function assertKnownSymbol(string $symbol): void
    {
        if ($symbol === ProviderSpread::WILDCARD) {
            return;
        }

        if (Instrument::tryFrom($symbol) === null) {
            throw InvalidSpreadException::unknownSymbol($symbol);
        }
    }

    private function assertValue(string $side, SpreadMode $mode, float $value): void
    {
        if (! is_finite($value)) {
            throw InvalidSpreadException::notFinite($side);
        }

        if ($mode !== SpreadMode::Percent) {
            // `amount` tu vahed e ASLI ye arz ast va saghf esh be instrument
            // vabaste-e (rial vs dollar). Baraye wildcard aslan nemidunim
            // kodum arz ast، pas yek band e adadi inja dorugh misaze.
            // `SpreadPricer` (invariant C) jologir e akhar ast.
            return;
        }

        if ($value <= -100.0 || $value >= 100.0) {
            throw InvalidSpreadException::percentOutOfRange($side, $value);
        }
    }

    /** @return array<string, mixed> */
    private function comparable(ProviderSpread $row): array
    {
        return [
            'buy_mode' => $row->buy_mode->value,
            'buy_value' => round($row->buy_value, 4),
            'sell_mode' => $row->sell_mode->value,
            'sell_value' => round($row->sell_value, 4),
            'is_enabled' => $row->is_enabled,
            'freshness_seconds' => $row->freshness_seconds,
            'sort' => $row->sort,
        ];
    }
}
