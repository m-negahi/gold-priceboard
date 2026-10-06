<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Safhe ye «تنظیم قیمت نمادها» (P5-07 + 2026-10-06) az HTTP: zanjire ye
 * olaviat + siasat e pushtiban e har symbol. Dokme ye «کپی از اولویت‌ها»
 * hazf shod — backup KHODKAR az olaviat por mishe (`manual_auto_source`
 * default `priority`).
 */
final class PriorityPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tablo ye dagh e test (db 15) — vagarna feed baghimande ro mikhune.
        Redis::connection('prices')->flushdb();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function provider(string $key, int $sort): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => $key === 'manual' ? 'manual' : 'tgju',
            'name' => strtoupper($key), 'name_fa' => 'منبع '.$key, 'is_active' => true, 'sort' => $sort,
        ]);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get(route('panel.priorities.index'))->assertRedirect(route('login'));
    }

    public function test_the_page_lists_every_symbol_with_its_chain(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);

        $this->actingAs($this->admin())->get(route('panel.priorities.index'))
            ->assertOk()
            ->assertSee('تنظیم قیمت نمادها')
            ->assertDontSee('کپی قیمت‌های تازه از اولویت‌ها')
            ->assertSee('xau-18k-g-irr')
            ->assertViewHas('rows', fn (array $rows): bool => $rows[0]['chain'] === ['tgju', 'estjt']);
    }

    public function test_saving_a_chain_stores_it_and_returns_fresh_rows(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);

        $this->actingAs($this->admin())
            ->postJson(route('panel.priorities.update'), ['symbol' => 'xau-18k-g-irr', 'providers' => ['estjt', 'tgju']])
            ->assertOk()
            ->assertJsonPath('toast.kind', 'success')
            ->assertJsonPath('rows.0.chain', ['estjt', 'tgju']);

        self::assertSame(['xau-18k-g-irr' => ['estjt', 'tgju']], $this->app->make(SymbolPriorityQuery::class)->customMap());
    }

    public function test_an_empty_chain_resets_to_default(): void
    {
        $this->provider('tgju', 10);

        $this->actingAs($this->admin())
            ->postJson(route('panel.priorities.update'), ['symbol' => 'xau-18k-g-irr', 'providers' => ['tgju']])
            ->assertOk();

        $this->actingAs($this->admin())
            ->postJson(route('panel.priorities.update'), ['symbol' => 'xau-18k-g-irr', 'providers' => []])
            ->assertOk()
            ->assertJsonPath('rows.0.isCustom', false);
    }

    public function test_an_unknown_provider_is_a_422_with_a_message(): void
    {
        $this->provider('tgju', 10);

        $this->actingAs($this->admin())
            ->postJson(route('panel.priorities.update'), ['symbol' => 'xau-18k-g-irr', 'providers' => ['nist']])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'nist'));
    }

    public function test_an_unknown_symbol_is_a_422(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('panel.priorities.update'), ['symbol' => 'nist', 'providers' => []])
            ->assertStatus(422);
    }

    /** Default e har symbol (bedun e radif e tanzim) = backup e khodkar az olaviat. */
    public function test_every_symbol_defaults_to_automatic_backup_from_priorities(): void
    {
        $this->provider('tgju', 10);

        $this->actingAs($this->admin())->get(route('panel.priorities.index'))
            ->assertOk()
            ->assertViewHas('rows', static fn (array $rows): bool => array_all(
                $rows,
                static fn (array $row): bool => $row['manualAutoSource'] === 'priority' && $row['manualFallbackEnabled'] === true && $row['manualOnly'] === false,
            ));
    }

    /** `sourceCount` = chand manba' e VAGHEI in symbol ro midan — badge e «تک‌منبع» az in miad. */
    public function test_rows_carry_the_real_source_count(): void
    {
        $tgju = $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        $this->provider('manual', 80);

        foreach ([$tgju->id => 266_400_000] as $providerId => $minor) {
            PriceSnapshot::query()->create([
                'provider_id' => $providerId, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
                'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => $minor,
                'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => CarbonImmutable::now()->subSeconds(5),
            ]);
        }
        ManualPrice::query()->create(['symbol' => 'xau-18k-g-irr', 'minor' => 266_000_000, 'scale' => 0, 'quote_currency' => 'IRR', 'source_hint' => 'manual']);

        $rows = $this->actingAs($this->admin())->get(route('panel.priorities.index'))->assertOk()->viewData('rows');
        $rows = array_column($rows, null, 'symbol');

        // Manual hesab nemishe: faghat tgju in symbol ro mide.
        self::assertSame(1, $rows['xau-18k-g-irr']['sourceCount']);
        self::assertSame('tgju', $rows['xau-18k-g-irr']['selectedProvider']);
        // Symbol-i ke hich kas nemide.
        self::assertSame(0, $rows['xcu-lb-usd']['sourceCount']);
    }

    public function test_the_seed_by_priority_endpoint_is_gone(): void
    {
        $this->actingAs($this->admin())->postJson('/dashboard/manual/seed-priority')->assertNotFound();
    }
}
