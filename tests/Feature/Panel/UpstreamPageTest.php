<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\Support\Fakes\InMemoryUpstreamCallLog;
use Tests\TestCase;

/**
 * Safhe ye `/dashboard/monitor/upstream` (P4-14) — daftarche ye call haye
 * MA be provider ha، na call e moshtari be ma.
 */
final class UpstreamPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test haye adapter e Redis entry ja mizaran — bedun e flush،
        // empty-state hichvaght dide nemishe.
        Redis::connection()->flushdb();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function fakeLogWith(UpstreamCallDto ...$calls): InMemoryUpstreamCallLog
    {
        $log = new InMemoryUpstreamCallLog;

        foreach ($calls as $call) {
            $log->push($call);
        }

        $this->app->instance(UpstreamLogPort::class, $log);

        return $log;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('panel.monitor.upstream'))->assertRedirect(route('login'));
    }

    public function test_the_page_shows_an_honest_empty_state(): void
    {
        $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))
            ->assertOk()
            ->assertSee('هنوز فراخوانی‌ای ثبت نشده');
    }

    public function test_logged_calls_reach_the_page_with_provider_names(): void
    {
        (new ProviderAction)->create(ProviderInputDto::fromArray([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'طلا و ارز',
            'is_active' => true,
        ]));

        $this->fakeLogWith(
            new UpstreamCallDto(
                providerKey: 'tgju',
                succeeded: false,
                at: CarbonImmutable::parse('2026-10-06T10:00:00Z'),
                method: 'GET',
                url: 'https://call1.example/prices',
                status: 503,
                latencyMs: 2_400,
                attempt: 2,
                error: 'pasokh e na-movafagh (503).',
            ),
            new UpstreamCallDto(
                providerKey: 'tgju',
                succeeded: true,
                at: CarbonImmutable::parse('2026-10-06T10:00:15Z'),
                method: 'GET',
                url: 'https://call2.example/prices',
                status: 200,
                latencyMs: 520,
                symbolCount: 16,
                bytes: 175_000,
            ),
        );

        $response = $this->actingAs($this->admin())->get(route('panel.monitor.upstream'))->assertOk();

        // Data ye har do call (URL ha ba'd az @js escape mishan، pas faghat
        // host e bedun e slash assert mishe) + esm e Farsi ye provider.
        $response->assertSee('call1.example');
        $response->assertSee('call2.example');
        $response->assertSee('503');
        $response->assertSee('pasokh e na-movafagh', false);
        $response->assertSee('طلا و ارز');
    }
}
