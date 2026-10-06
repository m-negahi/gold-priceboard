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
 * Safhe ye «اولویت منابع» + seed e dasti bar asas e olaviat (P5-07) az HTTP.
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
            ->assertSee('اولویت منابع')
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

    public function test_the_manual_page_can_seed_by_priority(): void
    {
        $tgju = $this->provider('tgju', 10);
        PriceSnapshot::query()->create([
            'provider_id' => $tgju->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000,
            'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => CarbonImmutable::now()->subSeconds(5),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('panel.manual.seed-priority'))
            ->assertOk()
            ->assertJsonPath('toast.kind', 'success');

        self::assertSame('tgju', ManualPrice::query()->where('symbol', 'xau-18k-g-irr')->value('source_hint'));
    }
}
