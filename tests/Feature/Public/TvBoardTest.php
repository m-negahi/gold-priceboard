<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceSnapshot;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Board e TV (P5-03): full-screen، font e bozorg، flash — OMUMI (bedun e auth).
 *
 * Har kashi YEK gheymat dare: gheymat e MO'ASSER (hamun feed e API —
 * zanjire ye olaviat e per-symbol e P5-07)، na miangin. Maghaze-i ke TV ro
 * be divar mizane yek adad mikhad، na moghayese.
 */
final class TvBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Board e dagh (db 15) ro pak kon — vagarna baghimande ye test e
        // ghabli khunde mishe (hamun dars e ManualFallbackTest).
        Redis::connection('prices')->flushdb();
    }

    private function tvProvider(string $key, int $sort, string $nameFa): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => 'tgju', 'name' => $key, 'name_fa' => $nameFa,
            'is_active' => true, 'on_board' => true, 'sort' => $sort,
        ]);
    }

    private function tvSnapshot(Provider $provider, string $symbol, int $minor, float $change = 0.0, int $ageSeconds = 0, string $quote = 'IRR', int $scale = 0): void
    {
        PriceSnapshot::query()->create([
            'provider_id' => $provider->id, 'symbol' => $symbol, 'side' => 'last',
            'quote_currency' => $quote, 'scale' => $scale, 'raw_minor' => $minor,
            'change_pct' => $change, 'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'quoted_at' => CarbonImmutable::now()->subSeconds($ageSeconds),
        ]);
    }

    public function test_the_tv_page_renders_without_auth_and_without_site_chrome(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        $this->tvSnapshot($tgju, 'xau-18k-g-irr', 266_400_000, 1.5);

        $this->get('/tv')
            ->assertOk()
            ->assertSee('طلای 18 عیار')
            ->assertSee('266,400,000')
            ->assertSee('data-tv-tile="xau-18k-g-irr"', false)
            ->assertSee('ریال')
            // Gheymat e taze -> kashi «قدیمی» nist.
            ->assertDontSee('data-stale', false)
            // Poll e 15s be `/tv.json` (na `/board.json`).
            ->assertSee('tvBoard(', false)
            ->assertSee('tv.json', false)
            // TV nabayad index beshe (mohtava ye tekrari ye safhe ye asli).
            ->assertSee('<meta name="robots" content="noindex', false)
            // Chrome e site (header ba «ورود») ru TV nist.
            ->assertDontSee('ورود');
    }

    public function test_the_tile_shows_the_effective_price_by_priority_not_the_average(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        $estjt = $this->tvProvider('estjt', 20, 'اتحادیه');
        $this->tvSnapshot($tgju, 'xau-18k-g-irr', 266_400_000);
        $this->tvSnapshot($estjt, 'xau-18k-g-irr', 270_000_000);

        // Tartib e global: tgju aval.
        $this->getJson('/tv.json')
            ->assertOk()
            ->assertJsonPath('rows.0.symbol', 'xau-18k-g-irr')
            ->assertJsonPath('rows.0.display', '266,400,000')
            ->assertJsonPath('rows.0.value', 266_400_000);

        // Olaviat e per-symbol (P5-07): estjt aval -> TV ham estjt.
        $this->app->make(SymbolPriorityAction::class)->set(Instrument::Gold18Gram, ['estjt', 'tgju']);

        $this->getJson('/tv.json')->assertJsonPath('rows.0.display', '270,000,000');
        $this->get('/tv')->assertSee('270,000,000')->assertDontSee('268,200,000');
    }

    public function test_change_direction_and_usd_precision_are_shown(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        $this->tvSnapshot($tgju, 'xau-oz-usd', 41_401_950, -0.42, quote: 'USD', scale: 4);

        $this->getJson('/tv.json')
            ->assertJsonPath('rows.0.display', '4,140.20')
            ->assertJsonPath('rows.0.direction', 'down')
            ->assertJsonPath('rows.0.change', '0.42')
            ->assertJsonPath('rows.0.quoteLabel', 'دلار')
            ->assertJsonPath('rows.0.groupLabel', 'طلا و سکه');
    }

    public function test_a_stale_price_is_marked_on_the_tile(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        // Tala 900s freshness dare va radif ba'd az 900+600s (hide_after)
        // AZ TABLO hazf mishe -> 1200s ya'ni stale vali hanuz namayan.
        $this->tvSnapshot($tgju, 'xau-18k-g-irr', 266_400_000, ageSeconds: 1_200);

        $this->getJson('/tv.json')->assertJsonPath('rows.0.isStale', true);
        $this->get('/tv')->assertSee('data-stale', false);
    }

    public function test_tv_json_has_an_etag_and_an_unchanged_poll_gets_304(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        $this->tvSnapshot($tgju, 'xau-18k-g-irr', 266_400_000);

        $etag = (string) $this->getJson('/tv.json')->headers->get('ETag');
        self::assertStringStartsWith('W/"', $etag);

        $this->getJson('/tv.json', ['If-None-Match' => $etag])->assertStatus(304);
    }

    /**
     * ETag az MOHTAVA sakhte mishe، na `boardVersion`: taghir e olaviat
     * (P5-07) board ro avaz nemikone vali adad e TV ro avaz mikone — ba ETag
     * e version-i TV ta tick e ba'di adad e ghadimi neshun midad.
     */
    public function test_the_etag_changes_when_the_effective_price_changes_without_a_new_tick(): void
    {
        $tgju = $this->tvProvider('tgju', 10, 'تی‌جی‌جی‌یو');
        $estjt = $this->tvProvider('estjt', 20, 'اتحادیه');
        $this->tvSnapshot($tgju, 'xau-18k-g-irr', 266_400_000);
        $this->tvSnapshot($estjt, 'xau-18k-g-irr', 270_000_000);

        $before = (string) $this->getJson('/tv.json')->headers->get('ETag');

        $this->app->make(SymbolPriorityAction::class)->set(Instrument::Gold18Gram, ['estjt', 'tgju']);

        $this->getJson('/tv.json', ['If-None-Match' => $before])->assertOk();
    }

    public function test_the_tv_page_shows_an_empty_state_with_no_prices(): void
    {
        $this->get('/tv')->assertOk()->assertSee('به‌زودی');
    }
}
