<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Seo\Infrastructure\Persistence\Models\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Safhe ye instrument (`/price/{symbol}`، P10-13): SSR ba toman، moghayese
 * ye manba' ha، JSON-LD (Breadcrumb + Product)، sitemap، link az tablo.
 */
final class InstrumentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection('prices')->flushdb();
    }

    private function provider(string $key, int $sort): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => 'tgju', 'name' => strtoupper($key), 'name_fa' => 'منبع '.$key,
            'is_active' => true, 'on_board' => true, 'sort' => $sort,
        ]);
    }

    private function snapshot(Provider $provider, string $symbol, int $minor, float $change = 0.0): void
    {
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => $symbol, 'side' => 'last',
            'quote_currency' => 'IRR', 'scale' => 0, 'raw_minor' => $minor,
            'change_pct' => $change, 'direction' => $change > 0 ? 'up' : 'flat', 'quoted_at' => now(),
        ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(TestResponse $response): array
    {
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', (string) $response->getContent(), $m);
        self::assertNotEmpty($m, 'JSON-LD nist.');

        $decoded = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);

        return $decoded['@graph'] ?? [$decoded];
    }

    public function test_the_page_renders_prices_in_toman_with_provider_comparison(): void
    {
        $tgju = $this->provider('tgju', 10);
        $estjt = $this->provider('estjt', 20);
        $this->snapshot($tgju, 'xau-18k-g-irr', 266_400_000, 1.5);
        $this->snapshot($estjt, 'xau-18k-g-irr', 267_000_000);

        $response = $this->get('/price/xau-18k-g-irr')->assertOk();

        $response
            ->assertSee('قیمت طلای 18 عیار')
            ->assertSee('منبع tgju')->assertSee('منبع estjt')
            // toman: 266,400,000 / 10 va 267,000,000 / 10
            ->assertSee('26,640,000')->assertSee('26,700,000')
            ->assertDontSee('266,400,000')
            ->assertSee('data-stat="min"', false)
            ->assertSee('data-provider-row="tgju"', false)
            ->assertSee('نمادهای مرتبط')
            ->assertSee(route('instrument', 'xau-24k-g-irr'))
            ->assertSee('<link rel="canonical" href="'.route('instrument', 'xau-18k-g-irr').'">', false);

        $page = $response->viewData('page');
        self::assertSame('تومان', $page['quoteLabel']);
        self::assertSame(2, $page['count']);
        // (26,700,000 − 26,640,000) / 26,640,000 = 0.2252…%
        self::assertSame('0.23', $page['spreadPct']);
        self::assertNotContains('xau-18k-g-irr', array_column($page['related'], 'symbol'));
        self::assertContains('xau-coin-emami-irr', array_column($page['related'], 'symbol'));
    }

    public function test_json_ld_has_a_breadcrumb_and_a_product_with_exact_low_and_high(): void
    {
        $tgju = $this->provider('tgju', 10);
        $estjt = $this->provider('estjt', 20);
        $this->snapshot($tgju, 'xau-18k-g-irr', 266_400_000);
        $this->snapshot($estjt, 'xau-18k-g-irr', 267_000_000);

        $nodes = $this->jsonLd($this->get('/price/xau-18k-g-irr')->assertOk());
        $types = array_column($nodes, '@type');

        self::assertContains('BreadcrumbList', $types);
        self::assertContains('Product', $types);

        $product = $nodes[array_search('Product', $types, true)];
        self::assertSame('xau-18k-g-irr', $product['sku']);
        self::assertSame(route('instrument', 'xau-18k-g-irr'), $product['url']);
        // JSON-LD gheymat e RIAL e daghigh (storage) — na toman e namayesh.
        self::assertSame('266400000', $product['offers']['lowPrice']);
        self::assertSame('267000000', $product['offers']['highPrice']);
        self::assertSame('IRR', $product['offers']['priceCurrency']);
        self::assertSame(2, $product['offers']['offerCount']);

        $crumbs = $nodes[array_search('BreadcrumbList', $types, true)]['itemListElement'];
        self::assertSame('خانه', $crumbs[0]['name']);
        // `LatinizeDigits` raghame Farsi ye label ro tu HTML (va JSON-LD e dakhel esh) Latin mikone.
        self::assertSame('طلای 18 عیار', $crumbs[2]['name']);
        self::assertSame(3, $crumbs[2]['position']);
    }

    public function test_a_symbol_without_prices_still_renders_and_an_unknown_symbol_is_404(): void
    {
        $this->get('/price/xcu-lb-usd')->assertOk()->assertSee('هنوز قیمتی برای این نماد ثبت نشده')->assertSee('مس');
        $this->get('/price/nist')->assertNotFound();
        $this->get('/price/XAU-18K')->assertNotFound();
    }

    public function test_seo_meta_overrides_the_instrument_title_and_noindex_drops_it_from_the_sitemap(): void
    {
        SeoMeta::query()->create([
            'seoable_type' => 'instrument', 'seoable_id' => 'xau-18k-g-irr',
            'title' => 'طلای ۱۸ سفارشی', 'robots' => 'noindex, follow',
        ]);

        $this->get('/price/xau-18k-g-irr')->assertOk()
            ->assertSee('<title>طلای 18 سفارشی</title>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $xml = simplexml_load_string((string) $this->get('/sitemaps/pages.xml')->assertOk()->getContent());
        self::assertNotFalse($xml);
        $locs = array_map('strval', iterator_to_array($xml->xpath('//*[local-name()="loc"]'), false));

        self::assertNotContains(route('instrument', 'xau-18k-g-irr'), $locs);
        self::assertContains(route('instrument', 'xau-24k-g-irr'), $locs);
        self::assertContains(route('home'), $locs);
    }

    public function test_the_board_links_every_symbol_to_its_page(): void
    {
        $tgju = $this->provider('tgju', 10);
        $this->snapshot($tgju, 'xau-18k-g-irr', 266_400_000);

        $this->get('/')->assertOk()
            ->assertSee('data-instrument-link="xau-18k-g-irr"', false)
            ->assertSee(route('instrument', 'xau-18k-g-irr'));
    }
}
