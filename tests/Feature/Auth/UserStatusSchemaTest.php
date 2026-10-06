<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Enums\CustomerType;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P6-01 — status/level/noe e karbar ru `users` (migration e ezafe-shi).
 *
 * 🔴 Backfill: karbar e MOJUD (radif-i ke ghabl az in migration sakhte
 * shode va hich kodum az in soton ha ro set nakarde) bayad `active` +
 * `basic` + `individual` bashe — vagarna ba deploy hame ye karbar haye
 * fe'li ya login nemitunan ya API eshun 403 mishe.
 */
final class UserStatusSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_raw_row_without_the_new_columns_is_backfilled_active_basic(): void
    {
        // Shabih e radif e ghabl az migration: insert e kham bedun e soton haye jadid.
        DB::table('users')->insert([
            'phone' => '+989120000001',
            'is_admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('users')->where('phone', '+989120000001')->first();

        $this->assertSame('active', $row->status);
        $this->assertSame('basic', $row->level);
        $this->assertSame('individual', $row->customer_type);
        $this->assertNull($row->disabled_at);
        $this->assertNull($row->disabled_reason);
        $this->assertNull($row->national_code);
    }

    public function test_the_model_casts_to_enums_and_defaults_on_a_fresh_instance(): void
    {
        // Default e sath e MODEL (na faghat DB): instance e taze bedun e
        // refresh() ham bayad active bashe — hamun dars e `is_admin`.
        $user = User::create(['phone' => '+989120000002']);

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserLevel::Basic, $user->level);
        $this->assertSame(CustomerType::Individual, $user->customer_type);
        $this->assertTrue($user->isActive());

        $fresh = $user->refresh();
        $this->assertSame(UserStatus::Active, $fresh->status);
        $this->assertSame(UserLevel::Basic, $fresh->level);
    }

    public function test_status_and_level_cannot_be_mass_assigned(): void
    {
        // Disable/ertegha ye sath TASMIM e admin ast (Action)، na form.
        $user = User::create([
            'phone' => '+989120000003',
            'status' => 'disabled',
            'level' => 'invoice_verified',
            'disabled_reason' => 'x',
        ]);

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(UserLevel::Basic, $user->level);
        $this->assertNull($user->disabled_reason);
    }

    public function test_customer_type_and_national_code_are_fillable(): void
    {
        $user = User::create([
            'phone' => '+989120000004',
            'customer_type' => 'legal',
            'national_code' => '10101010101',
        ])->refresh();

        $this->assertSame(CustomerType::Legal, $user->customer_type);
        $this->assertSame('10101010101', $user->national_code);
    }

    public function test_a_disabled_user_is_not_active(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'status' => UserStatus::Disabled,
            'disabled_at' => CarbonImmutable::now(),
            'disabled_reason' => 'تست',
        ])->save();

        $user->refresh();
        $this->assertFalse($user->isActive());
        $this->assertInstanceOf(CarbonImmutable::class, $user->disabled_at);
    }

    public function test_enum_labels_are_persian(): void
    {
        $this->assertSame('فعال', UserStatus::Active->label());
        $this->assertSame('غیرفعال', UserStatus::Disabled->label());
        $this->assertSame('پایه', UserLevel::Basic->label());
        $this->assertSame('تأییدشده برای فاکتور', UserLevel::InvoiceVerified->label());
        $this->assertSame('حقیقی', CustomerType::Individual->label());
        $this->assertSame('حقوقی', CustomerType::Legal->label());
    }
}
