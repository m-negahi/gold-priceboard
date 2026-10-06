<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Application\Actions\ManageAccessAction;
use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;
use Tests\TestCase;

final class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_admins_can_manage_roles(): void
    {
        $this->get('/dashboard/access')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/dashboard/access')->assertNotFound();
        $owner = User::factory()->admin()->create();
        $this->actingAs($owner)->post('/dashboard/access/roles', ['name' => 'ناظر', 'permissions' => ['users.view']])->assertRedirect();
        $this->assertDatabaseHas('access_roles', ['name' => 'ناظر']);
        $this->assertDatabaseHas('access_audits', ['actor_id' => $owner->id, 'action' => 'role_saved']);
    }

    public function test_read_only_role_cannot_write_or_view_other_areas_and_navigation_is_filtered(): void
    {
        $owner = User::factory()->admin()->create();
        $reader = User::factory()->admin()->create();
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'ناظر', 'permissions' => ['users.view']]);
        $action->assign($owner, $reader->id, [$role->id], false);
        self::assertTrue(Gate::forUser($reader)->allows('users.view'));
        self::assertFalse(Gate::forUser($reader)->allows('users.manage'));
        $this->actingAs($reader)->get('/dashboard/users')->assertOk()->assertDontSee('data-nav-item="providers"', false);
        $this->post('/dashboard/users', [])->assertForbidden();
        $this->get('/dashboard/access')->assertForbidden();
        $this->get('/horizon')->assertForbidden();
    }

    public function test_last_access_manager_cannot_remove_their_own_permission(): void
    {
        $owner = User::factory()->admin()->create();
        $this->actingAs($owner)->put('/dashboard/access/admins/'.$owner->id, ['full_access' => false, 'roles' => []])
            ->assertSessionHasErrors('access');
        self::assertTrue(app(AccessQuery::class)->allows($owner, 'access.manage'));
        $this->assertDatabaseCount('admin_access_profiles', 0);
    }

    public function test_removing_all_roles_does_not_restore_legacy_full_access(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        app(ManageAccessAction::class)->assign($owner, $other->id, [], false);
        self::assertSame([], app(AccessQuery::class)->permissions($other));
        $this->actingAs($other)->get('/dashboard')->assertForbidden();
    }

    public function test_unknown_permissions_are_rejected_and_assigned_roles_cannot_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();
        $this->actingAs($owner)->post('/dashboard/access/roles', ['name' => 'bad', 'permissions' => ['admin.all']])->assertSessionHasErrors('permissions.0');
        $role = app(ManageAccessAction::class)->saveRole($owner, null, ['name' => 'reader', 'permissions' => ['users.view']]);
        app(ManageAccessAction::class)->assign($owner, User::factory()->admin()->create()->id, [$role->id], false);
        $this->delete('/dashboard/access/roles/'.$role->id)->assertSessionHasErrors('role');
        $this->assertDatabaseHas('access_roles', ['id' => $role->id]);
    }

    public function test_role_edit_cannot_lock_out_the_last_manager(): void
    {
        $owner = User::factory()->admin()->create();
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'manager', 'permissions' => ['access.manage']]);
        $action->assign($owner, $owner->id, [$role->id], false);
        $this->actingAs($owner)->put('/dashboard/access/roles/'.$role->id, ['name' => 'manager', 'permissions' => []])->assertSessionHasErrors('access');
        self::assertTrue(app(AccessQuery::class)->allows($owner, 'access.manage'));
    }

    public function test_monitoring_readers_cannot_retry_jobs_or_modify_telescope(): void
    {
        $owner = User::factory()->admin()->create();
        $reader = User::factory()->admin()->create();
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'monitor', 'permissions' => ['monitoring.view']]);
        $action->assign($owner, $reader->id, [$role->id], false);
        $this->actingAs($reader)->get('/horizon')->assertOk();
        $this->post('/horizon/api/jobs/retry/missing')->assertForbidden();
        config(['telescope.enabled' => true]);
        (new TelescopeServiceProvider($this->app))->boot();
        Telescope::stopRecording();
        $this->get('/telescope')->assertOk();
        $this->postJson('/telescope/telescope-api/requests')->assertOk();
        $this->delete('/telescope/telescope-api/entries')->assertForbidden();
        $this->post('/telescope/telescope-api/toggle-recording')->assertForbidden();
    }

    public function test_provider_reader_cannot_open_the_spread_editor(): void
    {
        $owner = User::factory()->admin()->create();
        $reader = User::factory()->admin()->create();
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'provider-reader', 'permissions' => ['providers.view']]);
        $action->assign($owner, $reader->id, [$role->id], false);
        $this->actingAs($reader)->get('/dashboard/providers/tgju/spreads')->assertForbidden();
    }
}
