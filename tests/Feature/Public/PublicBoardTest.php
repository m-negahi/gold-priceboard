<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tablo ye omumi (P5-02): SSR + `/board.json` ba ETag. Bedun e auth.
 */
final class PublicBoardTest extends TestCase
{
    use RefreshDatabase;

    private function seedPrice(): void
    {
        $provider = Provider::query()->create([
            'key' => 'tgju', 'driver' => 'tgju', 'name' => 'TGJU', 'name_fa' => 'تی‌جی‌جی‌یو',
            'is_active' => true, 'on_board' => true,
        ]);

        PriceSnapshot::query()->create([
            'provider_id' => $provider->id,
            'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 266_400_000,
            'change_pct' => 1.5, 'direction' => 'up', 'quoted_at' => now(),
        ]);
    }

    public function test_the_home_page_renders_the_board_without_auth(): void
    {
        $this->seedPrice();

        $this->get('/')
            ->assertOk()
            ->assertSee('قیمت لحظه‌ای طلا، سکه و نقره')
            // `LatinizeDigits` «۱۸» ro Latin mikone.
            ->assertSee('طلای 18 عیار')
            ->assertSee('تی‌جی‌جی‌یو')
            // Toman (266,400,000 rial / 10) — Latin digit (LatinizeDigits).
            ->assertSee('26,640,000');
    }

    public function test_the_home_page_shows_an_empty_state_with_no_prices(): void
    {
        $this->get('/')->assertOk()->assertSee('به‌زودی');
    }

    public function test_board_json_returns_data_with_an_etag(): void
    {
        $this->seedPrice();

        $response = $this->getJson('/board.json')->assertOk();

        self::assertNotNull($response->headers->get('ETag'));
        $response->assertJsonPath('rows.0.symbol', 'xau-18k-g-irr');
        $response->assertJsonPath('rows.0.cells.tgju.direction', 'up');
    }

    public function test_an_unchanged_poll_gets_304(): void
    {
        $this->seedPrice();

        $etag = (string) $this->getJson('/board.json')->headers->get('ETag');

        $this->getJson('/board.json', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_only_board_providers_appear(): void
    {
        $this->seedPrice();

        // Provider e `on_board=false` nabayad tu tablo biad (hanuz allActive
        // ro mide vali present() faghat radif-dar ha ro migire — in snapshot
        // nadare pas gheyb mishe).
        Provider::query()->create([
            'key' => 'hidden', 'driver' => 'mock', 'name' => 'Hidden', 'name_fa' => 'مخفی',
            'is_active' => true, 'on_board' => false,
        ]);

        $this->getJson('/board.json')->assertOk()->assertJsonMissing(['key' => 'hidden']);
    }
}
