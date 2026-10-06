<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use App\Modules\Pricing\Application\Queries\ManualPriceQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Sources\ManualQuoteSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Manba' e DASTI (fallback) + feed e mo'asser (P4/P5 — khaste ye sahebe
 * proje): olaviat ba provider، fallback be manual، alarm.
 */
final class ManualFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Board e dagh (db 15) ro pak kon — vagarna EffectivePriceQuery
        // baghimande ye test e ghabli ro mikhune jaye snapshot haye sqlite.
        Redis::connection('prices')->flushdb();
    }

    // ── ManualQuoteSource (pure) ────────────────────────────────────

    public function test_the_manual_source_translates_stored_prices(): void
    {
        $source = new ManualQuoteSource([
            'xau-18k-g-irr' => ['minor' => 266_400_000, 'quote' => 'IRR'],
            'nist' => ['minor' => 1, 'quote' => 'IRR'], // symbol e nashenas -> rad
        ]);

        $result = $source->fetch();

        self::assertCount(1, $result->quotes);
        $quote = $result->quotes[0];
        self::assertSame(266_400_000, $quote->price->minor);
        // 🔴 Hargez timestamp nemide -> hichvaght stale rad nemishe.
        self::assertNull($quote->upstreamAt);
    }

    // ── ManualPriceAction ───────────────────────────────────────────

    public function test_set_from_display_converts_toman_to_minor(): void
    {
        $action = new ManualPriceAction;

        // 26,640,000 toman -> 266,400,000 rial (divisor 10، scale 0).
        $action->setFromDisplay(Instrument::Gold18Gram, 26_640_000, 'admin');

        $row = ManualPrice::query()->where('symbol', 'xau-18k-g-irr')->firstOrFail();
        self::assertSame(266_400_000, (int) $row->minor);
        self::assertSame('manual', $row->source_hint);
    }

    public function test_set_from_display_handles_usd_scale(): void
    {
        // $4,140.19 -> minor 41,401,900 (divisor 1، scale 4).
        (new ManualPriceAction)->setFromDisplay(Instrument::GoldOunce, 4_140.19, 'admin');

        $row = ManualPrice::query()->where('symbol', 'xau-oz-usd')->firstOrFail();
        self::assertSame(41_401_900, (int) $row->minor);
    }

    public function test_seed_from_provider_copies_latest_snapshots(): void
    {
        $provider = Provider::query()->create([
            'key' => 'tgju', 'driver' => 'tgju', 'name' => 'TGJU', 'name_fa' => 'ت', 'is_active' => true,
        ]);
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000, 'quoted_at' => now(),
        ]);

        $count = (new ManualPriceAction)->seedFromProvider('tgju', 'admin');

        self::assertSame(1, $count);
        $row = ManualPrice::query()->where('symbol', 'xau-18k-g-irr')->firstOrFail();
        self::assertSame(266_400_000, (int) $row->minor);
        self::assertSame('tgju', $row->source_hint);
    }

    // ── ManualPriceQuery alarm ──────────────────────────────────────

    public function test_a_stale_manual_price_raises_an_alarm(): void
    {
        $row = ManualPrice::query()->create([
            'symbol' => 'xau-18k-g-irr', 'minor' => 266_400_000, 'scale' => 0, 'quote_currency' => 'IRR',
        ]);
        // `updated_at` ro 2 rooz aghab mibarim (ba'd az create، ke `touch`
        // hamin alan gozashte).
        $row->forceFill(['updated_at' => CarbonImmutable::now()->subDays(2)])->saveQuietly();

        $query = new ManualPriceQuery(app(ClockPort::class), alarmAfterSeconds: 86_400);

        $gold = collect($query->rows())->firstWhere('symbol', 'xau-18k-g-irr');

        self::assertTrue($gold['isAlarm'], 'gheymat e 2-rooze bayad alarm bede.');
        self::assertSame(1, $query->alarmCount());
    }

    public function test_a_fresh_manual_price_has_no_alarm(): void
    {
        (new ManualPriceAction)->setFromDisplay(Instrument::Gold18Gram, 26_640_000, 'admin');

        $query = new ManualPriceQuery(app(ClockPort::class), 86_400);

        self::assertSame(0, $query->alarmCount());
    }

    // ── EffectivePriceQuery: olaviat + fallback ─────────────────────

    private function activeProvider(string $key, int $sort): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => $key === 'manual' ? 'manual' : 'tgju',
            'name' => $key, 'name_fa' => $key, 'is_active' => true, 'sort' => $sort,
        ]);
    }

    private function snapshot(Provider $p, int $ageSeconds): void
    {
        PriceSnapshot::query()->create([
            'provider_id' => $p->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000,
            'change_pct' => 0, 'direction' => 'flat',
            'quoted_at' => CarbonImmutable::now()->subSeconds($ageSeconds),
        ]);
    }

    public function test_effective_prefers_the_highest_priority_fresh_provider(): void
    {
        $tgju = $this->activeProvider('tgju', 10);
        $this->activeProvider('estjt', 20);
        $this->snapshot($tgju, 5); // tgju taze
        $this->snapshot(Provider::query()->where('key', 'estjt')->firstOrFail(), 5);

        $feed = $this->app->make(EffectivePriceQuery::class)->feed();
        $gold = collect($feed['symbols'])->firstWhere('symbol', 'xau-18k-g-irr');

        self::assertSame('tgju', $gold['provider'], 'olaviat ba sort e kamtar.');
        self::assertFalse($gold['usingFallback']);
        self::assertFalse($feed['alarm']);
    }

    public function test_effective_falls_back_to_manual_when_reals_are_stale(): void
    {
        // tgju STALE (kheyli ghadimi — freshness e tala 900s/345600s... sort
        // e seeder nist pas default 900s). 2 rooz ghadimi = stale.
        $tgju = $this->activeProvider('tgju', 10);
        $this->snapshot($tgju, 2 * 86_400);

        // Manual (sort balatar) — taze (ManualQuoteSource upstreamAt=null،
        // pas isStale ru read ba sen hesab mishe؛ manual snapshot e taze dare).
        $manual = $this->activeProvider('manual', 80);
        $this->snapshot($manual, 5);

        $feed = $this->app->make(EffectivePriceQuery::class)->feed();
        $gold = collect($feed['symbols'])->firstWhere('symbol', 'xau-18k-g-irr');

        self::assertSame('manual', $gold['provider'], 'hame ye asli stale -> manual.');
        self::assertTrue($gold['usingFallback']);
        self::assertTrue($feed['alarm'], 'fallback -> alarm.');
        self::assertSame(1, $feed['fallbackCount']);
    }
}
