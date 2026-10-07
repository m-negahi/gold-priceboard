<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Safhe haye monitoring (P3-08/09/10) az rooye HTTP.
 *
 * Tick e vaghei ba driver e MOCK zade mishe (hich network-i) ta masir e
 * kamel — `IngestTickAction` -> `putTickReport` -> safhe — yek ja test she.
 */
final class MonitorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection('prices')->flushdb();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function seedMockProvider(): void
    {
        (new ProviderAction)->create(ProviderInputDto::fromArray([
            'key' => 'mock',
            'driver' => 'mock',
            'name' => 'Mock',
            'name_fa' => 'آزمایشی',
            'is_active' => true,
        ]));
    }

    public function test_the_old_health_page_redirects_to_provider_health_and_settings(): void
    {
        $this->seedMockProvider();

        $this->actingAs($this->admin())->get(route('panel.health'))
            ->assertRedirect(route('panel.providers.index'));

        $this->get(route('panel.providers.index'))
            ->assertOk()
            ->assertSee('منابع قیمت')
            ->assertSee('آزمایشی');
    }

    public function test_the_monitor_page_shows_an_honest_empty_state(): void
    {
        $this->actingAs($this->admin())->get(route('panel.monitor.ticks'))
            ->assertOk()
            ->assertSee('هنوز گزارشی ثبت نشده');
    }

    public function test_the_tick_monitor_offers_summary_filters_and_a_rejection_table(): void
    {
        $cache = $this->app->make(HotCachePort::class);
        $cache->putTickReport([
            'at' => '2026-10-06T10:00:00Z', 'skipped' => true, 'skip_reason' => "hich provider e fa'ali nobat nadasht",
            'accepted' => 0, 'rejected' => 0, 'duration_ms' => 0, 'providers' => [], 'sinks' => [], 'rejections' => [], 'unmapped' => [],
        ]);
        $cache->putTickReport([
            'at' => '2026-10-06T10:00:15Z', 'skipped' => false, 'skip_reason' => null,
            'accepted' => 15, 'rejected' => 1, 'duration_ms' => 9_000,
            'providers' => ['tgju' => ['succeeded' => true, 'latency_ms' => 1_200, 'symbol_count' => 16, 'retried' => false, 'error' => null]],
            'sinks' => [],
            'rejections' => [['provider' => 'tgju', 'symbol' => 'ons', 'side' => 'last', 'reason' => 'stale:90000s', 'category' => 'stale', 'minor' => 41_000_000]],
            'unmapped' => [],
        ]);

        $this->actingAs($this->admin())->get(route('panel.monitor.ticks'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s): bool => $s['tgju']['rejections'] === 1)
            ->assertViewMissing('options')
            ->assertSee('جدول ردها')
            ->assertSee('خط زمانی')
            // Tick e «بدون اجرا» be soorat e default penhan + shomaresh.
            ->assertSee('بدون اجرا');
    }

    public function test_a_real_tick_lands_on_the_monitor_and_matrix_pages(): void
    {
        $this->seedMockProvider();

        // Tick e vaghei — mock source hich HTTP-i nadare.
        $this->artisan('pricing:tick', ['--force' => true])->assertSuccessful();

        // Daftarche por shod (masir e IngestTickAction -> putTickReport).
        $reports = $this->app->make(HotCachePort::class)->tickReports(1);
        self::assertNotSame([], $reports);

        // Gharardad e kelid haye payload — view az hamin ha mikhune
        // (`ok` e sink، na `succeeded`؛ yek bar eshtebah shod).
        self::assertTrue($reports[0]['sinks']['redis']['ok'] ?? false, 'sink e redis tu tick e test bayad ok bashe.');
        self::assertTrue($reports[0]['providers']['mock']['succeeded'] ?? false);

        $this->actingAs($this->admin())->get(route('panel.monitor.ticks'))
            ->assertOk()
            ->assertSee('قیمت') // pill e «N قیمت»
            ->assertSee('mock');

        // Matrix ham hamun tick ro mibine. (`LatinizeDigits` ragham e label
        // ro Latin mikone، pas «۱۸» tu khoruji «18» ast.)
        // Header e matrix esm e NAMAYESHI ro neshun mide، na key (P10-24).
        $this->actingAs($this->admin())->get(route('panel.matrix'))
            ->assertOk()
            ->assertSee('طلای 18 عیار')
            ->assertSee('xau-18k-g-irr')
            ->assertSee('آزمایشی');
    }
}
