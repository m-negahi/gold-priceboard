<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ManualPriceAction;
use App\Modules\Pricing\Application\Actions\SymbolPriceSettingsAction;
use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Application\Pipeline\Stages\ManualBackupStage;
use App\Modules\Pricing\Application\Pipeline\Stages\PublishStage;
use App\Modules\Pricing\Application\Pipeline\TickContext;
use App\Modules\Pricing\Application\Pipeline\TickPipeline;
use App\Modules\Pricing\Application\Queries\EffectivePriceQuery;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\ManualBackupPort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\TickTime;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FakeManualBackup;
use Tests\Support\Fakes\InMemorySnapshotStore;
use Tests\TestCase;

final class PricingSettingsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection('prices')->flushdb();
    }

    private function provider(string $key, int $sort = 10): Provider
    {
        return Provider::query()->create(['key' => $key, 'driver' => $key === 'manual' ? 'manual' : 'tgju', 'name' => $key, 'name_fa' => $key, 'sort' => $sort, 'is_active' => true, 'hide_after_seconds' => 0]);
    }

    private function snapshot(string $provider, int $minor, int $age = 0): SnapshotDto
    {
        return new SnapshotDto($provider, Instrument::Gold18Gram, Side::Last, Price::irr($minor), CarbonImmutable::now()->subSeconds($age));
    }

    public function test_manual_only_uses_the_admin_price_immediately_and_preserves_priorities(): void
    {
        $provider = $this->provider('tgju');
        $this->provider('estjt', 20);
        PriceSnapshot::query()->create(['provider_id' => $provider->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last', 'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000, 'quoted_at' => now()]);
        app(SymbolPriorityAction::class)->set(Instrument::Gold18Gram, ['estjt', 'tgju']);
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000', 'admin');

        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, true, false, 'priority');
        $gold = collect(app(EffectivePriceQuery::class)->feed()['symbols'])->firstWhere('symbol', 'xau-18k-g-irr');

        self::assertSame('manual', $gold['provider']);
        self::assertSame(280_000_000, $gold['sell']);
        self::assertTrue($gold['manualOnly']);
        self::assertFalse($gold['usingFallback']);
        self::assertSame(['estjt', 'tgju'], app(SymbolPriorityQuery::class)->customMap()['xau-18k-g-irr']);
        self::assertSame([], app(EffectivePriceQuery::class)->freshestByPriority());
    }

    public function test_disabling_manual_fallback_never_selects_manual_for_that_symbol(): void
    {
        $provider = $this->provider('tgju');
        $this->provider('manual', 99);
        PriceSnapshot::query()->create(['provider_id' => $provider->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last', 'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000, 'quoted_at' => now()->subHours(2)]);
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000');
        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, false, true, 'none');
        self::assertSame('manual', app(EffectivePriceQuery::class)->feed()['symbols'][0]['provider']);

        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, false, false, 'none');
        $gold = app(EffectivePriceQuery::class)->feed()['symbols'][0];

        self::assertSame('tgju', $gold['provider']);
        self::assertTrue($gold['isStale']);
    }

    public function test_auto_backup_keeps_stale_price_then_recovers_with_fresh_raw_price(): void
    {
        $this->freezeTime();
        $this->provider('tgju');
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000', 'admin');
        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, false, true, 'tgju');
        $before = ManualPrice::query()->firstOrFail()->updated_at;
        $stale = $this->snapshot('tgju', 266_400_000, 1000);

        self::assertSame(0, app(ManualBackupPort::class)->sync([$stale->key() => $stale], TickTime::fromCarbon(CarbonImmutable::now())));
        self::assertSame(280_000_000, ManualPrice::query()->firstOrFail()->minor);
        self::assertTrue($before->equalTo(ManualPrice::query()->firstOrFail()->updated_at));

        $this->travel(15)->seconds();
        $fresh = $this->snapshot('tgju', 266_400_000);
        app(ManualBackupPort::class)->sync([$fresh->key() => $fresh], TickTime::fromCarbon(CarbonImmutable::now()));

        $this->assertDatabaseHas('manual_prices', ['symbol' => 'xau-18k-g-irr', 'minor' => 266_400_000, 'source_hint' => 'tgju', 'updated_by' => 'auto']);
    }

    public function test_priority_backup_skips_stale_first_source_and_manual_only_blocks_all_auto_updates(): void
    {
        $this->provider('tgju');
        $this->provider('estjt', 20);
        app(SymbolPriorityAction::class)->set(Instrument::Gold18Gram, ['estjt', 'tgju']);
        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, false, true, 'priority');
        $stale = $this->snapshot('estjt', 270_000_000, 1000);
        $fresh = $this->snapshot('tgju', 266_400_000);
        $snapshots = [$stale->key() => $stale, $fresh->key() => $fresh];
        app(ManualBackupPort::class)->sync($snapshots, TickTime::fromCarbon(CarbonImmutable::now()));
        $this->assertDatabaseHas('manual_prices', ['minor' => 266_400_000, 'source_hint' => 'tgju']);
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000');

        app(SymbolPriceSettingsAction::class)->set(Instrument::Gold18Gram, true, true, 'priority');
        app(ManualBackupPort::class)->sync($snapshots, TickTime::fromCarbon(CarbonImmutable::now()));

        $this->assertDatabaseHas('manual_prices', ['minor' => 280_000_000, 'source_hint' => 'manual']);
        $this->assertDatabaseHas('symbol_price_settings', ['manual_auto_source' => 'priority', 'manual_only' => true]);
    }

    public function test_backup_stage_ignores_previous_snapshots_when_current_fetch_failed(): void
    {
        $fake = new FakeManualBackup;
        $context = new TickContext(TickTime::fromCarbon(CarbonImmutable::now()), [], []);
        $old = $this->snapshot('tgju', 266_400_000);
        $context->previous[$old->key()] = $old;

        (new ManualBackupStage($fake))->handle($context);

        self::assertSame([], $fake->snapshots);
    }

    public function test_backup_failure_is_isolated_and_publish_still_runs(): void
    {
        $fake = new FakeManualBackup;
        $fake->fails = true;
        $cache = new ArrayHotCache;
        $at = TickTime::fromCarbon(CarbonImmutable::now());
        $pipeline = new TickPipeline(new InMemorySnapshotStore, [new ManualBackupStage($fake), new PublishStage($cache)]);

        $report = $pipeline->run(new TickContext($at, [], []));

        self::assertFalse($report->skipped);
        self::assertSame($at->timestamp(), $cache->heartbeatAt());
    }

    public function test_settings_endpoint_rejects_manual_only_without_a_price_and_saves_valid_policy(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $payload = ['symbol' => 'xau-18k-g-irr', 'manualOnly' => true, 'manualFallbackEnabled' => false, 'manualAutoSource' => 'none'];

        $this->postJson(route('panel.priorities.settings'), $payload)->assertUnprocessable()->assertJsonPath('message', 'برای فعال کردن فقط دستی، ابتدا قیمت دستی را ثبت کنید.');
        $this->assertDatabaseCount('symbol_price_settings', 0);
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000');
        $this->postJson(route('panel.priorities.settings'), $payload)->assertOk()->assertJsonPath('rows.0.manualOnly', true);
        $this->assertDatabaseHas('symbol_price_settings', ['symbol' => 'xau-18k-g-irr', 'manual_only' => true, 'manual_fallback_enabled' => false]);
    }

    public function test_legacy_pages_redirect_to_the_combined_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/dashboard/manual')->assertRedirect(route('panel.priorities.index'));
        $this->get('/dashboard/health')->assertRedirect(route('panel.providers.index'));
    }

    public function test_removing_the_manual_price_does_not_resurrect_an_old_manual_snapshot(): void
    {
        $manual = $this->provider('manual', 99);
        PriceSnapshot::query()->create(['provider_id' => $manual->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last', 'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000, 'quoted_at' => now()]);
        app(ManualPriceAction::class)->setFromDisplay(Instrument::Gold18Gram, '280000000');
        self::assertSame(280_000_000, app(EffectivePriceQuery::class)->feed()['symbols'][0]['sell']);

        app(ManualPriceAction::class)->remove(Instrument::Gold18Gram);

        self::assertSame([], app(EffectivePriceQuery::class)->feed()['symbols']);
    }
}
