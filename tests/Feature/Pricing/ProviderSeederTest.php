<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpreadRevision;
use Database\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 🔴 Seeder az HAMUN darvaze ye panel rad mishe (`ProviderAction` /
 * `ProviderSpreadAction`).
 *
 * In test faghat «seeder kar mikone» ro check nemikone — mohafez e in ast ke
 * config e paye ye projeh va ghanun haye Action hamishe BA HAM bemunan. Do
 * invariant e eshtebah hamin tor peyda shodan: `timeout < pollInterval` va
 * `freshness <= 86400` har do config e vaghei ye hamin seeder ro rad
 * mikardan.
 */
final class ProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedProviders(): void
    {
        $this->app->make(ProviderSeeder::class)->run();
    }

    public function test_the_baseline_config_passes_the_action_validation(): void
    {
        $this->seedProviders();

        $this->assertSame(
            ['estjt', 'mock', 'tgju'],
            Provider::query()->orderBy('key')->pluck('key')->all(),
        );
    }

    public function test_providers_start_inactive(): void
    {
        $this->seedProviders();

        // Ravesh e kar: avval `pricing:tick` e dasti، ba'd `TickMonitor`، va
        // faghat ba'd roshan kardan.
        $this->assertSame(0, Provider::query()->where('is_active', true)->count());
    }

    public function test_the_daily_global_symbols_get_their_freshness_override(): void
    {
        $this->seedProviders();

        $tgju = Provider::query()->where('key', 'tgju')->firstOrFail();

        $overrides = ProviderSpread::query()
            ->where('provider_id', $tgju->id)
            ->whereNotNull('freshness_seconds')
            ->pluck('freshness_seconds', 'symbol')
            ->all();

        // Bedun e in ha، `Instrument::freshnessSeconds()` (180s baraye
        // jahani) har tick in chahar radif ro rad mikone va az tablo gom
        // mishan — bedun e hich error.
        $this->assertSame(90_000, (int) $overrides[Instrument::GoldOunce->value]);
        $this->assertSame(90_000, (int) $overrides[Instrument::SilverOunce->value]);
        $this->assertSame(90_000, (int) $overrides[Instrument::CopperPound->value]);
        $this->assertSame(90_000, (int) $overrides[Instrument::CoinGerami->value]);

        // Dollar: shab/ta'til yakh mizane (andaze-giri: 124,005s bedun e
        // update) — 2 rooz ta gap e akhare-hafte radesh nakone vali dollar
        // e yek-hafte-i «taze» e'lam nashe. (Khaste ye sahebe proje،
        // 2026-10-06: «dolar e tgju ro ham biar».)
        $this->assertSame(172_800, (int) $overrides[Instrument::UsdIrr->value]);
    }

    public function test_estjt_keeps_its_measured_timeout(): void
    {
        $this->seedProviders();

        // Andaze-giri shode: un endpoint 6.2–9.1 saniye javab mide. Timeout
        // e 20 ba fasele ye 15s amdi-e.
        $this->assertSame(20, Provider::query()->where('key', 'estjt')->firstOrFail()->timeout);
    }

    // ── 🔴 Re-seed nabayad kharab kone ──────────────────────────────

    public function test_reseeding_does_not_turn_off_a_provider_the_admin_enabled(): void
    {
        $this->seedProviders();

        Provider::query()->where('key', 'tgju')->update(['is_active' => true]);

        $this->seedProviders();

        // Ghablan seeder `is_active => false` ro hamishe minevesht: yek
        // `db:seed` ye ma'muli site ro bedun e hich khata az gheymat
        // mi'andakht.
        $this->assertTrue((bool) Provider::query()->where('key', 'tgju')->firstOrFail()->is_active);
    }

    public function test_reseeding_does_not_wipe_spreads_the_admin_configured(): void
    {
        $this->seedProviders();

        $tgju = Provider::query()->where('key', 'tgju')->firstOrFail();

        ProviderSpread::query()
            ->where('provider_id', $tgju->id)
            ->where('symbol', ProviderSpread::WILDCARD)
            ->update(['buy_value' => -0.75, 'sell_value' => 1.25]);

        $revisionsBefore = ProviderSpreadRevision::query()->count();

        $this->seedProviders();

        $wildcard = ProviderSpread::query()
            ->where('provider_id', $tgju->id)
            ->where('symbol', ProviderSpread::WILDCARD)
            ->firstOrFail();

        // Seeder wildcard ro ba SEFR minevise. Agar re-seed un ro bazneveshte
        // kone، hame ye nerkh haye tanzim-shode sefr mishan — va chun
        // `ProviderSpreadAction` un ro «taghir» mibine، revision ham minevise
        // va cache ro batel mikone.
        $this->assertSame(-0.75, $wildcard->buy_value);
        $this->assertSame(1.25, $wildcard->sell_value);

        // Va hich revision e ja'li.
        $this->assertSame($revisionsBefore, ProviderSpreadRevision::query()->count());
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seedProviders();

        $providers = Provider::query()->count();
        $spreads = ProviderSpread::query()->count();

        $this->seedProviders();
        $this->seedProviders();

        $this->assertSame($providers, Provider::query()->count());
        $this->assertSame($spreads, ProviderSpread::query()->count());
    }
}
