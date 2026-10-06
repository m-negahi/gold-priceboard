<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Enums\QueueName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;
use Tests\TestCase;

final class MonitoringAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_horizon_even_in_local(): void
    {
        $this->app['env'] = 'local';
        $this->get('/horizon')->assertRedirect('/login');
    }

    public function test_customers_cannot_open_horizon_even_in_local(): void
    {
        $this->app['env'] = 'local';
        $this->actingAs(User::factory()->create())->get('/horizon')->assertNotFound();
    }

    public function test_active_admin_can_open_horizon(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/horizon')->assertOk();
    }

    public function test_telescope_is_restricted_to_admins_even_in_local(): void
    {
        $this->app['env'] = 'local';
        config(['telescope.enabled' => true]);
        (new TelescopeServiceProvider($this->app))->boot();
        Telescope::stopRecording();

        $this->get('/telescope')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/telescope')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/telescope')->assertOk();
    }

    public function test_monitoring_gates_reject_disabled_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['status' => UserStatus::Disabled])->save();

        self::assertFalse(Gate::forUser($admin)->allows('viewHorizon'));
        self::assertFalse(Gate::forUser($admin)->allows('viewTelescope'));
    }

    public function test_horizon_consumes_every_declared_queue_with_safe_timeouts(): void
    {
        $supervisor = config('horizon.defaults.supervisor-1');

        self::assertSame(QueueName::values(), $supervisor['queue']);
        self::assertLessThan(config('queue.connections.redis.retry_after'), $supervisor['timeout']);
    }
}
