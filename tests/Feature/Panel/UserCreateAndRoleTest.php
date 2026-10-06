<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Application\Actions\UserAdminAction;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-04 — sakht e karbar (admin ya customer ba mobile) + taghir e naghsh.
 *
 * 🔴 `is_admin` faghat ba `forceFill` e Action set mishe — form/request
 * hich vaght mostaghim be model nemirese.
 */
final class UserCreateAndRoleTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create(['phone' => '+989120000000']);
    }

    // ── Sakht ──────────────────────────────────────────────────────

    public function test_the_create_form_renders_for_admins_only(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('panel.users.create'))
            ->assertOk()->assertSee('کاربر جدید');

        $this->actingAs(User::factory()->create())->get(route('panel.users.create'))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('panel.users.store'), ['phone' => '09121111111'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['phone' => '+989121111111']);
    }

    public function test_an_admin_creates_a_customer_with_a_local_mobile(): void
    {
        $response = $this->actingAs($this->makeAdmin())->post(route('panel.users.store'), [
            'phone' => '0912 111 1111',
            'name' => 'سارا',
            'role' => 'customer',
            'customer_type' => 'individual',
        ]);

        $user = User::query()->where('phone', '+989121111111')->firstOrFail();
        $response->assertRedirect(route('panel.users.show', $user->id));

        $this->assertSame('سارا', $user->name);
        $this->assertFalse($user->is_admin);
        // Hanuz OTP nazade — verify nashode.
        $this->assertNull($user->phone_verified_at);
    }

    public function test_an_admin_creates_another_admin(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('panel.users.store'), [
            'phone' => '09352222222',
            'role' => 'admin',
            'customer_type' => 'individual',
        ])->assertRedirect();

        $this->assertTrue(User::query()->where('phone', '+989352222222')->firstOrFail()->is_admin);
    }

    public function test_a_duplicate_mobile_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->create(['phone' => '+989121111111']);

        $this->actingAs($admin)->from(route('panel.users.create'))->post(route('panel.users.store'), [
            'phone' => '09121111111', 'role' => 'customer', 'customer_type' => 'individual',
        ])->assertRedirect(route('panel.users.create'))->assertSessionHasErrors('phone');

        $this->assertSame(1, User::query()->where('phone', '+989121111111')->count());
    }

    public function test_an_invalid_mobile_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->from(route('panel.users.create'))->post(route('panel.users.store'), [
            'phone' => '12345', 'role' => 'customer', 'customer_type' => 'individual',
        ])->assertSessionHasErrors('phone');
    }

    public function test_extra_fields_cannot_escalate_or_disable(): void
    {
        // Form e dast-kari-shode: `is_admin=1` + `status` — hich asari nadaran.
        $this->actingAs($this->makeAdmin())->post(route('panel.users.store'), [
            'phone' => '09121111111',
            'role' => 'customer',
            'customer_type' => 'individual',
            'is_admin' => 1,
            'status' => 'disabled',
            'level' => 'invoice_verified',
        ])->assertRedirect();

        $user = User::query()->where('phone', '+989121111111')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->isActive());
        $this->assertSame('basic', $user->level->value);
    }

    // ── Taghir e naghsh ────────────────────────────────────────────

    public function test_an_admin_promotes_and_demotes_a_user(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('panel.users.role', $user->id), ['role' => 'admin'])
            ->assertRedirect(route('panel.users.show', $user->id));
        $this->assertTrue($user->refresh()->is_admin);

        $this->actingAs($admin)->post(route('panel.users.role', $user->id), ['role' => 'customer'])
            ->assertRedirect(route('panel.users.show', $user->id));
        $this->assertFalse($user->refresh()->is_admin);
        $this->assertDatabaseCount('user_profile_audits', 2);
    }

    public function test_an_admin_cannot_demote_themselves(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('panel.users.role', $admin->id), ['role' => 'customer'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_the_last_admin_cannot_be_demoted_by_the_action(): void
    {
        $admin = $this->makeAdmin();
        $actor = User::factory()->create(); // na admin — faghat baraye check e action

        $this->expectException(UserAdminException::class);
        app(UserAdminAction::class)->setRole($admin, false, $actor);
    }

    public function test_the_role_form_is_on_the_user_page(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->get(route('panel.users.show', $user->id))
            ->assertOk()
            ->assertSee(route('panel.users.role', $user->id), false)
            ->assertSee('ارتقا به ادمین');
    }

    public function test_the_list_has_a_create_button(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('panel.users.index'))
            ->assertOk()
            ->assertSee(route('panel.users.create'), false);
    }
}
