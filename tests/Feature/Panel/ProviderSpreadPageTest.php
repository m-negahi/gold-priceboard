<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpreadRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Form e spread az rooye HTTP (P3-07) — sync/remove/preview az masir e
 * `ProviderSpreadAction` + `SpreadQuery`.
 */
final class ProviderSpreadPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function provider(): Provider
    {
        return (new ProviderAction)->create(ProviderInputDto::fromArray([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'تی‌جی‌جی‌یو',
            'is_active' => false,
        ]));
    }

    /** @return array<string, mixed> */
    private function row(string $symbol, array $overrides = []): array
    {
        return array_merge([
            'symbol' => $symbol,
            'buy_mode' => 'percent',
            'buy_value' => 0,
            'sell_mode' => 'percent',
            'sell_value' => 0,
            'is_enabled' => 1,
        ], $overrides);
    }

    public function test_the_form_lists_a_row_for_every_instrument_plus_wildcard(): void
    {
        $this->provider();

        $response = $this->actingAs($this->admin())->get(route('panel.providers.spreads', 'tgju'));

        $response->assertOk()
            ->assertSee('اسپرد تی‌جی‌جی‌یو')
            // 16 radif: wildcard + 15 instrument — tu payload e Alpine.
            ->assertSee('پیش‌فرض (همه نمادها)')
            ->assertSee('xau-18k-g-irr');
    }

    public function test_saving_changed_rows_writes_revisions_and_bumps_the_version(): void
    {
        $this->provider();

        $this->actingAs($this->admin())
            ->put(route('panel.providers.spreads.update', 'tgju'), [
                'rows' => [
                    $this->row('*'),
                    $this->row('xau-18k-g-irr', ['sell_value' => 0.5]),
                ],
            ])
            ->assertRedirect(route('panel.providers.spreads', 'tgju'));

        $this->assertDatabaseHas('provider_spreads', ['symbol' => 'xau-18k-g-irr', 'sell_value' => 0.5]);
        self::assertSame(2, ProviderSpreadRevision::query()->count(), 'har radif e jadid yek revision.');

        // Save ye DOBARE ye hamun meghdar ha: hich revision e jadid.
        $this->actingAs($this->admin())
            ->put(route('panel.providers.spreads.update', 'tgju'), [
                'rows' => [
                    $this->row('*'),
                    $this->row('xau-18k-g-irr', ['sell_value' => 0.5]),
                ],
            ])
            ->assertRedirect();

        self::assertSame(2, ProviderSpreadRevision::query()->count(), 'save ye bi-taghir nabayad revision bede.');
    }

    public function test_an_out_of_band_percent_is_rejected(): void
    {
        $this->provider();

        $this->actingAs($this->admin())
            ->from(route('panel.providers.spreads', 'tgju'))
            ->put(route('panel.providers.spreads.update', 'tgju'), [
                'rows' => [$this->row('xau-18k-g-irr', ['sell_value' => 150])],
            ])
            ->assertRedirect(route('panel.providers.spreads', 'tgju'))
            ->assertSessionHasErrors('rows');

        self::assertSame(0, ProviderSpread::query()->count());
    }

    public function test_remove_deletes_the_override_and_logs_a_removal_revision(): void
    {
        $this->provider();

        $this->actingAs($this->admin())->put(route('panel.providers.spreads.update', 'tgju'), [
            'rows' => [$this->row('xau-18k-g-irr', ['sell_value' => 1])],
        ]);

        $this->actingAs($this->admin())
            ->put(route('panel.providers.spreads.remove', ['tgju', 'xau-18k-g-irr']))
            ->assertRedirect();

        $this->assertDatabaseMissing('provider_spreads', ['symbol' => 'xau-18k-g-irr']);
        $this->assertDatabaseHas('provider_spread_revisions', [
            'symbol' => 'xau-18k-g-irr',
            'is_removal' => true,
        ]);
    }

    public function test_preview_uses_the_same_spread_pricer(): void
    {
        $provider = $this->provider();

        // Yek snapshot e kham tu Postgres (fallback e preview vaghti Redis
        // e test khali-e).
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id,
            'symbol' => 'xau-18k-g-irr',
            'side' => 'last',
            'quote_currency' => 'IRR',
            'scale' => 0,
            'raw_minor' => 266_400_000,
            'quoted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('panel.providers.spreads.preview', 'tgju'), [
                'symbol' => 'xau-18k-g-irr',
                'buy_mode' => 'percent',
                'buy_value' => -1,
                'sell_mode' => 'percent',
                'sell_value' => 1,
            ]);

        $response->assertOk();

        // Daghighan hamun arithmetic e SpreadPricer: ±1%.
        // (`assertEquals` na `assertSame`: json_encode float e bedun e
        // ashar ro int mikone — 266400000.0 -> 266400000.)
        self::assertEquals(266_400_000, $response->json('preview.raw'));
        self::assertEquals(263_736_000, $response->json('preview.buy'));
        self::assertEquals(269_064_000, $response->json('preview.sell'));
    }
}
