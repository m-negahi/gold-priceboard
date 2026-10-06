<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Seo\Infrastructure\Persistence\Models\SeoMeta;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SEO e site e omumi (P5-05): meta/canonical/OG، JSON-LD (WebSite +
 * Product/AggregateOffer + FAQPage)، override az `seo_meta`، sitemap index،
 * `robots.txt` e dynamic.
 */
final class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection('prices')->flushdb();
    }

    private function seoProvider(string $key, int $sort): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => 'tgju', 'name' => $key, 'name_fa' => $key,
            'is_active' => true, 'on_board' => true, 'sort' => $sort,
        ]);
    }

    private function seoSnapshot(Provider $provider, string $symbol, int $minor, string $quote = 'IRR', int $scale = 0): void
    {
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => $symbol, 'side' => 'last',
            'quote_currency' => $quote, 'scale' => $scale, 'raw_minor' => $minor,
            'change_pct' => 0.0, 'direction' => 'flat', 'quoted_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * JSON-LD e safhe ro az HTML dar miare (yek `<script>` ba `@graph`).
     *
     * @return array<string, mixed>
     */
    private function jsonLdOf(TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $count = preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        self::assertSame(1, $count, 'daghighan yek script e JSON-LD entezar mire.');

        return json_decode($m[1][0], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    private function nodesOfType(array $ld, string $type): array
    {
        return array_values(array_filter($ld['@graph'], static fn (array $n): bool => $n['@type'] === $type));
    }

    public function test_home_has_canonical_open_graph_and_a_description(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:locale" content="fa_IR">', false)
            ->assertSee('<meta property="og:url" content="'.route('home').'">', false)
            ->assertSee('<meta name="description" content="مقایسه', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_home_json_ld_has_a_product_per_symbol_with_exact_low_and_high(): void
    {
        // Se manba' (kelid va sort ham-tartib) ba kamtarin e VASAT — ta
        // «avvalin» ya «akharin» radif be eshtebah kamtarin/bishtarin nashe.
        $first = $this->seoProvider('aaa', 10);
        $middle = $this->seoProvider('bbb', 20);
        $last = $this->seoProvider('ccc', 30);
        $this->seoSnapshot($first, 'xau-18k-g-irr', 268_000_000);
        $this->seoSnapshot($middle, 'xau-18k-g-irr', 266_400_000);
        $this->seoSnapshot($last, 'xau-18k-g-irr', 270_000_000);
        $this->seoSnapshot($first, 'xau-oz-usd', 41_401_950, 'USD', 4);

        $ld = $this->jsonLdOf($this->get('/')->assertOk());

        self::assertCount(1, $this->nodesOfType($ld, 'WebSite'));

        $products = $this->nodesOfType($ld, 'Product');
        self::assertCount(2, $products);

        $gold = collect($products)->firstWhere('sku', 'xau-18k-g-irr');
        self::assertSame('طلای 18 عیار', $gold['name']);
        self::assertSame('IRR', $gold['offers']['priceCurrency']);
        // Rial e DAGHIGH (na toman، na float).
        self::assertSame('266400000', $gold['offers']['lowPrice']);
        self::assertSame('270000000', $gold['offers']['highPrice']);
        self::assertSame(3, $gold['offers']['offerCount']);

        $ounce = collect($products)->firstWhere('sku', 'xau-oz-usd');
        self::assertSame('USD', $ounce['offers']['priceCurrency']);
        self::assertSame('4140.1950', $ounce['offers']['lowPrice']);
    }

    /**
     * Google: FAQPage faghat vaghti ke HAMUN soal/javab ru safhe DIDE beshe.
     * Har do az YEK manba' (config e `seo.faq`) miyan.
     */
    public function test_faq_json_ld_matches_the_visible_faq_section(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('سوالات متداول');
        $faq = $this->nodesOfType($this->jsonLdOf($response), 'FAQPage');

        self::assertCount(1, $faq);
        self::assertNotEmpty($faq[0]['mainEntity']);
        self::assertCount(count(config('seo.faq')), $faq[0]['mainEntity']);

        foreach ($faq[0]['mainEntity'] as $question) {
            $response->assertSee(e($question['name']), false);
            $response->assertSee(e($question['acceptedAnswer']['text']), false);
        }
    }

    public function test_an_empty_board_still_renders_valid_json_ld_without_products(): void
    {
        $ld = $this->jsonLdOf($this->get('/')->assertOk());

        self::assertSame([], $this->nodesOfType($ld, 'Product'));
        self::assertCount(1, $this->nodesOfType($ld, 'FAQPage'));
    }

    public function test_seo_meta_overrides_title_and_description_of_a_page(): void
    {
        SeoMeta::query()->create([
            'seoable_type' => 'page', 'seoable_id' => 'home',
            'title' => 'قیمت طلا امروز', 'description' => 'توضیح سفارشی',
        ]);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('<title>قیمت طلا امروز</title>', false)
            ->assertSee('<meta name="description" content="توضیح سفارشی">', false)
            ->assertSee('<meta property="og:title" content="قیمت طلا امروز">', false);

        $site = $this->nodesOfType($this->jsonLdOf($response), 'WebSite')[0];
        self::assertSame('توضیح سفارشی', $site['description']);
    }

    /** Field e khali/space-only e override = pish-farz (na `<title>` e khali). */
    public function test_a_partial_override_keeps_the_defaults_for_empty_fields(): void
    {
        SeoMeta::query()->create([
            'seoable_type' => 'page', 'seoable_id' => 'home', 'title' => '   ', 'description' => 'فقط توضیح',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>قیمت لحظه‌ای طلا، سکه و نقره — تابلو قیمت</title>', false)
            ->assertSee('<meta name="description" content="فقط توضیح">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
    }

    /** 🔴 Matn e override (admin) nabayad az `<script>` e JSON-LD farar kone. */
    public function test_an_override_cannot_inject_a_script(): void
    {
        SeoMeta::query()->create([
            'seoable_type' => 'page', 'seoable_id' => 'home',
            'description' => '</script><script>alert(1)</script>',
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertSame(1, preg_match_all('#<script type="application/ld\+json">#', $html));
    }

    public function test_sitemap_index_points_to_the_pages_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        self::assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string((string) $response->getContent());
        self::assertNotFalse($xml);
        self::assertSame('sitemapindex', $xml->getName());
        self::assertSame(route('sitemap.pages'), (string) $xml->sitemap[0]->loc);
    }

    public function test_pages_sitemap_lists_public_pages_but_not_noindex_or_private_ones(): void
    {
        $tgju = $this->seoProvider('tgju', 10);
        $this->seoSnapshot($tgju, 'xau-18k-g-irr', 266_400_000);

        $response = $this->get('/sitemaps/pages.xml')->assertOk();
        $xml = simplexml_load_string((string) $response->getContent());
        self::assertNotFalse($xml);
        self::assertSame('urlset', $xml->getName());

        $locs = array_map('strval', iterator_to_array($xml->xpath('//*[local-name()="loc"]'), false));
        self::assertContains(route('home'), $locs);
        self::assertNotContains(route('tv'), $locs);
        self::assertNotContains(route('login'), $locs);

        // lastmod e safhe ye zende = akharin gheymat (W3C datetime).
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $xml->url[0]->lastmod);
    }

    /** Safhe-i ke admin `noindex` karde tu sitemap nabayad bashe (tanaghoz). */
    public function test_a_page_marked_noindex_drops_out_of_the_sitemap_and_gets_a_noindex_meta(): void
    {
        SeoMeta::query()->create([
            'seoable_type' => 'page', 'seoable_id' => 'home', 'robots' => 'noindex, follow',
        ]);

        $this->get('/')->assertSee('<meta name="robots" content="noindex, follow">', false);

        $xml = simplexml_load_string((string) $this->get('/sitemaps/pages.xml')->getContent());
        $locs = array_map('strval', iterator_to_array($xml->xpath('//*[local-name()="loc"]'), false));

        self::assertNotContains(route('home'), $locs);
    }

    public function test_robots_txt_in_an_indexable_environment(): void
    {
        config(['seo.indexable' => true]);

        $response = $this->get('/robots.txt')->assertOk();

        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        self::assertStringContainsString("Disallow: /dashboard\n", $body);
        self::assertStringContainsString("Disallow: /login\n", $body);
        self::assertStringContainsString('Sitemap: '.route('sitemap.index'), $body);
        self::assertStringNotContainsString("Disallow: /\n", $body);
    }

    public function test_robots_txt_blocks_everything_outside_production(): void
    {
        config(['seo.indexable' => false]);

        self::assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('/robots.txt')->assertOk()->getContent());
    }

    /**
     * 🔴 File e STATIC e `public/robots.txt` ghabl az route serve mishe
     * (Octane/nginx file e mojud ro mostaghim midan) — route e dynamic
     * hichvaght seda zade nemishod.
     */
    public function test_no_static_robots_file_shadows_the_route(): void
    {
        self::assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_the_tv_page_is_noindex(): void
    {
        $this->get('/tv')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
