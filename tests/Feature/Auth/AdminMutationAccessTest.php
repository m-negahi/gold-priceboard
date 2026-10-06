<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Access\Application\Actions\ManageAccessAction;
use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Auth\Application\Actions\SaveUserProfileAction;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Kyc\Jobs\RunIdentityChecks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\InMemoryOtpStore;
use Tests\TestCase;

final class AdminMutationAccessTest extends TestCase
{
    use RefreshDatabase;

    private function restrict(User $owner, User $admin, array $permissions): void
    {
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'role-'.$admin->id, 'permissions' => $permissions]);
        $action->assign($owner, $admin->id, [$role->id], false);
    }

    public function test_customer_manager_cannot_take_over_disable_enable_or_demote_an_admin(): void
    {
        $owner = User::factory()->admin()->create();
        $manager = User::factory()->admin()->create();
        $this->restrict($owner, $manager, ['users.view', 'users.manage']);
        $oldPhone = $owner->phone;
        $this->actingAs($manager)->put(route('panel.users.update', $owner->id), ['phone' => '09124445566', 'customer_type' => 'individual'])->assertForbidden();
        $this->get(route('panel.users.edit', $owner->id))->assertForbidden();
        $this->post(route('panel.users.disable', $owner->id), ['reason' => 'x'])->assertForbidden();
        $this->post(route('panel.users.enable', $owner->id))->assertForbidden();
        $this->post(route('panel.users.role', $owner->id), ['role' => 'customer'])->assertForbidden();
        $this->post(route('panel.users.store'), ['phone' => '09124445566', 'customer_type' => 'individual', 'role' => 'admin'])->assertForbidden();
        self::assertSame($oldPhone, $owner->refresh()->phone);
        self::assertTrue($owner->isActive());
        self::assertTrue(app(AccessQuery::class)->allows($owner, 'access.manage'));
        $this->assertDatabaseMissing('users', ['phone' => '+989124445566']);
    }

    public function test_customer_manager_can_still_edit_customer_profile(): void
    {
        Queue::fake();
        $owner = User::factory()->admin()->create();
        $manager = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->restrict($owner, $manager, ['users.view', 'users.manage']);
        $this->actingAs($manager)->put(route('panel.users.update', $customer->id), ['phone' => $customer->phone, 'customer_type' => 'individual', 'name' => 'نام جدید'])->assertRedirect();
        self::assertSame('نام جدید', $customer->refresh()->name);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_stale_customer_model_cannot_bypass_admin_target_guard(): void
    {
        $owner = User::factory()->admin()->create();
        $manager = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->restrict($owner, $manager, ['users.manage']);
        User::query()->whereKey($target->id)->update(['is_admin' => true]);
        $this->expectException(AuthorizationException::class);
        app(SaveUserProfileAction::class)->execute($target, ['phone' => '09124445566', 'customer_type' => 'individual'], $manager);
    }

    public function test_restricted_admin_login_redirects_to_the_first_allowed_page(): void
    {
        $owner = User::factory()->admin()->create();
        $admin = User::factory()->admin()->create(['phone' => '+989121234567']);
        $this->restrict($owner, $admin, ['tickets.view']);
        $this->app->instance(OtpStorePort::class, new InMemoryOtpStore);
        $this->postJson(route('login.code'), ['phone' => '09121234567'])->assertOk();
        $code = app(FakeSms::class)->lastCodeFor($admin->phone);
        $this->postJson(route('login.verify'), ['phone' => '09121234567', 'code' => $code])->assertJsonPath('redirect', route('panel.tickets.index'));
        $this->assertAuthenticatedAs($admin);
    }
}
