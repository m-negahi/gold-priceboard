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
            // 🔴 TOMAN (khaste ye sahebe proje): 266,400,000 rial / 10.
            ->assertSee('26,640,000')
            ->assertDontSee('266,400,000')
            ->assertSee('قیمت‌ها به تومان');
    }

    /**
     * SSR va JS har do az `divisor` e radif estefade mikonan؛ agar in adad
     * ghalat bashe، poll e ba'di ye Alpine gheymat ro 10 barabar mikone.
     */
    public function test_board_rows_carry_the_toman_divisor_for_rial_and_none_for_dollar(): void
    {
        $this->seedPrice();

        $provider = Provider::query()->where('key', 'tgju')->firstOrFail();

        PriceSnapshot::query()->create([
            'provider_id' => $provider->id,
            'symbol' => 'xau-oz-usd', 'side' => 'last',
            'quote_currency' => 'USD', 'scale' => 4, 'raw_minor' => 41_401_900,
            'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => now(),
        ]);

        $rows = collect($this->getJson('/board.json')->assertOk()->json('rows'))->keyBy('symbol');

        $this->assertSame(10, $rows['xau-18k-g-irr']['divisor']);
        $this->assertSame('تومان', $rows['xau-18k-g-irr']['quoteLabel']);
        $this->assertEqualsWithDelta(26_640_000, $rows['xau-18k-g-irr']['min'], 0.0);
        $this->assertSame('26,640,000', $rows['xau-18k-g-irr']['minDisplay']);
        $this->assertSame('26,640,000', $rows['xau-18k-g-irr']['avgDisplay']);
        $this->assertSame('26,640,000', $rows['xau-18k-g-irr']['cells']['tgju']['sellDisplay']);

        $this->assertSame(1, $rows['xau-oz-usd']['divisor']);
        $this->assertSame('دلار', $rows['xau-oz-usd']['quoteLabel']);
        $this->assertEqualsWithDelta(4140.19, $rows['xau-oz-usd']['min'], 0.0001);
    }

    /**
     * 🔴 Bug e vaghei: Blade `metal` ro ba 'gold'/'silver' moghayese mikard
     * vali meghdar e enum `xau`/`xag`/`xcu`-e — pas HAME ye gruh ha «ارز»
     * neshun dade mishodan va dot e rangi khakestari bud.
     */
    public function test_group_heading_uses_the_metal_label_not_the_fx_fallback(): void
    {
        $this->seedPrice();

        $this->get('/')
            ->assertOk()
            ->assertSee('طلا و سکه')
            ->assertSee('bg-metal-gold', false)
            ->assertDontSee('ارز');
    }

    public function test_large_rial_prices_and_statistics_preserve_every_digit_in_ssr_and_polling(): void
    {
        $this->seedPrice();
        PriceSnapshot::query()->update(['raw_minor' => 9_007_199_254_740_993]);
        $other = Provider::query()->create([
            'key' => 'estjt', 'driver' => 'estjt', 'name' => 'ESTJT', 'name_fa' => 'اتحادیه',
            'is_active' => true, 'on_board' => true,
        ]);
        PriceSnapshot::query()->create([
            'provider_id' => $other->id, 'symbol' => 'xau-18k-g-irr', 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => 9_007_199_254_740_995,
            'change_pct' => 0, 'direction' => 'flat', 'quoted_at' => now(),
        ]);

        // Toman ba bcmath (nim-be-bala): …993 rial → …099.3 → …099؛ …995 → …099.5 → …100.
        // Bala ye 2^53 float ragham gom mikard — in daghighan hamun check ast.
        $this->get('/')->assertOk()->assertSee('900,719,925,474,099')
            ->assertDontSee('9,007,199,254,740,99');
        $this->getJson('/board.json')->assertOk()
            ->assertJsonPath('rows.0.cells.tgju.sellDisplay', '900,719,925,474,099')
            ->assertJsonPath('rows.0.minDisplay', '900,719,925,474,099')
            ->assertJsonPath('rows.0.maxDisplay', '900,719,925,474,100')
            // miangin e minor = …994 → 900,719,925,474,099.4 → …099
            ->assertJsonPath('rows.0.avgDisplay', '900,719,925,474,099');
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

    public function test_candles_endpoint_validates_the_symbol(): void
    {
        $this->getJson('/candles.json?symbol=nist-hamchin&interval=m5')->assertStatus(422);
    }

    public function test_candles_endpoint_returns_a_series_shape(): void
    {
        $this->seedPrice();

        // Influx fake nist tu test — CandleQuery degraded bar migardune
        // (Influx dar dastres nist)، vali shape bayad salem bashe.
        $this->getJson('/candles.json?symbol=xau-18k-g-irr&interval=m5')
            ->assertOk()
            ->assertJsonStructure(['data', 'symbol', 'interval', 'provider', 'precision', 'displayDivisor', 'degraded'])
            ->assertJsonPath('symbol', 'xau-18k-g-irr')
            ->assertJsonPath('interval', 'm5');
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
