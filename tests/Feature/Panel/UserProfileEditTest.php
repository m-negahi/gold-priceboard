<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Actions\RunIdentityChecksAction;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Kyc\Jobs\RunIdentityChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\FakeIdentityVerification;
use Tests\TestCase;

final class UserProfileEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edits_natural_identity_with_audit_and_verification(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'قبل', 'phone_verified_at' => now()]);
        $this->actingAs($admin)->put(route('panel.users.update', $user->id), ['phone' => '09121112233', 'name' => 'بعد', 'email' => 'new@example.test', 'customer_type' => 'individual', 'national_code' => '0499370899', 'birth_date' => '1370/01/15', 'address' => 'تهران', 'postal_code' => '1234567890', 'level' => 'basic', 'company_national_id' => '10380284790', 'representative_national_code' => '0499370899'])->assertRedirect(route('panel.users.edit', $user->id));
        self::assertSame('بعد', $user->refresh()->name);
        self::assertNull($user->phone_verified_at);
        self::assertSame('1370/01/15', $user->identity_profile['birth_date']);
        self::assertArrayNotHasKey('company_national_id', $user->identity_profile);
        self::assertArrayNotHasKey('representative_national_code', $user->identity_profile);
        $audit = DB::table('user_profile_audits')->where('user_id', $user->id)->first();
        self::assertSame($admin->id, $audit->actor_id);
        self::assertSame('قبل', json_decode($audit->before, true)['name']);
        self::assertSame('بعد', json_decode($audit->after, true)['name']);
        Queue::assertPushed(RunIdentityChecks::class, 1);
        $this->app->instance(IdentityVerificationPort::class, new FakeIdentityVerification);
        app(RunIdentityChecksAction::class)->execute(DB::table('identity_verification_runs')->where('user_id', $user->id)->value('id'));
        $this->get(route('panel.users.edit', $user->id))->assertOk()->assertSee('گزارش استعلام زحل')->assertSee('تاریخچه تغییرات')->assertSee('شاهکار');
    }

    public function test_invalid_identity_duplicate_phone_and_non_admin_cannot_edit(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'قبل']);
        $this->actingAs($admin)->put(route('panel.users.update', $user->id), ['phone' => $admin->phone, 'customer_type' => 'individual', 'national_code' => '1111111111', 'birth_date' => '1405/13/50'])->assertSessionHasErrors(['phone', 'national_code', 'birth_date']);
        self::assertSame('قبل', $user->refresh()->name);
        $this->actingAs($user)->put(route('panel.users.update', $admin->id), [])->assertNotFound();
        $this->assertDatabaseCount('user_profile_audits', 0);
        Queue::assertNothingPushed();
    }

    public function test_create_saves_legal_fields_and_only_explicit_role(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $this->actingAs(User::factory()->admin()->create())->post(route('panel.users.store'), ['phone' => '09124445566', 'name' => 'نماینده', 'customer_type' => 'legal', 'role' => 'customer', 'registered_name' => 'شرکت نمونه', 'company_national_id' => '10380284790', 'representative_national_code' => '0499370899', 'representative_birth_date' => '1370/01/15', 'is_admin' => true])->assertRedirect();
        $user = User::query()->where('phone', '+989124445566')->firstOrFail();
        self::assertFalse($user->is_admin);
        self::assertSame('10380284790', $user->identity_profile['company_national_id']);
        $this->assertDatabaseHas('user_profile_audits', ['user_id' => $user->id]);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_identity_change_revokes_invoice_approval_even_if_form_keeps_verified_level(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'نام معتبر']);
        $approved = ['name' => 'نام معتبر', 'national_code' => '0499370899'];
        $user->forceFill(['level' => 'invoice_verified', 'invoice_profile' => $approved, 'national_code' => '0499370899'])->save();

        $this->actingAs($admin)->put(route('panel.users.update', $user->id), [
            'phone' => $user->phone, 'name' => 'نام تازه', 'customer_type' => 'individual',
            'national_code' => '0499370899', 'level' => 'invoice_verified',
        ])->assertRedirect();

        self::assertSame('basic', $user->refresh()->level->value);
        self::assertNull($user->invoice_profile);
        $audit = DB::table('user_profile_audits')->where('user_id', $user->id)->first();
        self::assertSame($approved, json_decode($audit->before, true)['invoice_profile']);
        self::assertNull(json_decode($audit->after, true)['invoice_profile']);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_email_only_edit_preserves_approved_invoice_identity(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'نام معتبر']);
        $profile = ['birth_date' => '1370/01/15', 'postal_code' => '1234567890', 'address' => 'تهران'];
        $approved = [...$profile, 'name' => $user->name, 'national_code' => '0499370899'];
        $user->forceFill(['level' => 'invoice_verified', 'invoice_profile' => $approved, 'identity_profile' => $profile, 'national_code' => '0499370899'])->save();

        $this->actingAs($admin)->put(route('panel.users.update', $user->id), [
            ...$profile, 'phone' => $user->phone, 'name' => $user->name, 'email' => 'new@example.test',
            'customer_type' => 'individual', 'national_code' => '0499370899', 'level' => 'invoice_verified', 'landline' => '',
        ])->assertRedirect();

        self::assertSame('invoice_verified', $user->refresh()->level->value);
        self::assertSame($approved, $user->invoice_profile);
        self::assertSame('new@example.test', $user->email);
    }
}
