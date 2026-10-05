<?php

declare(strict_types=1);

namespace App\Modules\Panel\Actions;

use App\Modules\Pricing\Enums\InstrumentKind;
use App\Modules\Pricing\Enums\InstrumentUnit;
use App\Modules\Pricing\Enums\SourceCurrency;
use App\Modules\Pricing\Enums\SourceRole;
use App\Modules\Pricing\Models\Instrument;
use App\Modules\Pricing\Models\PriceQuote;
use App\Modules\Pricing\Models\PriceSource;
use App\Modules\Pricing\Queries\PriceQuoteQuery;
use Carbon\CarbonImmutable;

/**
 * Dade-ye namayeshi-ye mizkar baraye Playwright (`panel:seed-dashboard`): yek gheymat-e taze,
 * yek gheymat-e stale va yek manba' dar cooldown — daghighan se halati ke spec-e dashboard
 * mikhad bebine, bedoone inke be vaziat-e database-e dev tekye kone.
 *
 * Code ha va key sabet va ba pishvand-e `E2E_`-and ta `cleanup()` faghat hamin ha ro pak kone va
 * hich vaght ba radif-e vaghei barkhord nakone. Idempotent-e (updateOrCreate).
 */
final readonly class SeedDashboardDemoAction
{
    public const SOURCE_KEY = 'e2e_dash_cooldown';

    public const FRESH_CODE = 'E2E_DASH_FRESH';

    public const STALE_CODE = 'E2E_DASH_STALE';

    public function __construct(private PriceQuoteQuery $quotes) {}

    /** @return array{source: string, instruments: list<string>} */
    public function handle(): array
    {
        $now = CarbonImmutable::now();

        // Manba'-e dar cooldown: 5 khata-ye poshte-sar-e-ham, ta yek sa'at kharej az charkhe. Gheyr-e
        // fa'al, chon driver-i ba in key vojood nadare: age `--clean` faramoosh beshe, yek manba'-e fa'al
        // ba'd az yek sa'at vared-e charkhe-ye poll-e vaghei mishod va har 15 sanie UnknownProviderException misakht.
        $source = PriceSource::query()->updateOrCreate(['key' => self::SOURCE_KEY], [
            'name' => 'منبع آزمایشی (خنک‌سازی)',
            'role' => SourceRole::Source,
            'priority' => 999,
            'poll_interval_seconds' => 15,
            'timeout_ms' => 5000,
            'currency' => SourceCurrency::Rial,
            'is_active' => false,
            'consecutive_failures' => 5,
            'disabled_until' => $now->addHour(),
            'last_success_at' => $now->subMinutes(20),
            'last_error' => 'HTTP 500 from provider (seeded for e2e)',
        ]);

        $fresh = $this->instrument(self::FRESH_CODE, 'طلای آزمایشی (تازه)', 9001);
        $stale = $this->instrument(self::STALE_CODE, 'طلای آزمایشی (کهنه)', 9002);

        $this->quote($fresh, $source, 42_850_000, 42_000_000, $now->subSeconds(4), false);
        $this->quote($stale, $source, 520_000_000, 520_000_000, $now->subMinutes(10), true);

        // Mizkar az cache-e quote mikhoone; bedoone flush radif-haye jadid ta TTL dide nemishan.
        $this->quotes->flush();

        return ['source' => self::SOURCE_KEY, 'instruments' => [self::FRESH_CODE, self::STALE_CODE]];
    }

    public function cleanup(): void
    {
        $ids = Instrument::query()->whereIn('code', [self::FRESH_CODE, self::STALE_CODE])->pluck('id');

        PriceQuote::query()->whereIn('instrument_id', $ids)->delete();
        Instrument::query()->whereKey($ids)->delete();
        PriceSource::query()->where('key', self::SOURCE_KEY)->delete();

        $this->quotes->flush();
    }

    private function instrument(string $code, string $name, int $sort): Instrument
    {
        return Instrument::query()->updateOrCreate(['code' => $code], [
            'name_fa' => $name,
            'kind' => InstrumentKind::Metal,
            'unit' => InstrumentUnit::Gram,
            'market' => 'irr_gold',
            'purity' => 750,
            'rounding_step' => 1000,
            'is_active' => true,
            'sort' => $sort,
        ]);
    }

    private function quote(Instrument $instrument, PriceSource $source, int $mid, int $prevClose, CarbonImmutable $asOf, bool $stale): void
    {
        PriceQuote::query()->updateOrCreate(['instrument_id' => $instrument->id], [
            'price_source_id' => $source->id,
            'buy' => $mid - 100_000,
            'sell' => $mid + 100_000,
            'mid' => $mid,
            'prev_close' => $prevClose,
            'change_abs' => $mid - $prevClose,
            'change_pct' => $prevClose === 0 ? '0' : bcdiv(bcmul((string) ($mid - $prevClose), '100', 4), (string) $prevClose, 4),
            'as_of' => $asOf,
            'is_stale' => $stale,
        ]);
    }
}
