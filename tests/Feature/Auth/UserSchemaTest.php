<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hoviat e login `phone` ast — na email/password.
 */
final class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_is_created_with_phone_alone(): void
    {
        // Lahze ye sabt-e-nam ba OTP: faghat phone darim. Na name، na email،
        // na password.
        $user = User::create(['phone' => '+989121234567']);

        $this->assertNull($user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_phone_is_unique(): void
    {
        User::create(['phone' => '+989121234567']);

        // `PhoneHelper` + middleware ta'min mikonan ke har shomare faghat
        // YEK shekl dare — pas in unique vaghe'an ya'ni yek nafar yek hesab.
        $this->expectException(QueryException::class);
        User::create(['phone' => '+989121234567']);
    }

    public function test_phone_is_required(): void
    {
        // Karbar e bedun e hoviat e login nabayad SAKET sakhte beshe.
        $this->expectException(QueryException::class);
        User::create(['name' => 'bi-phone']);
    }

    // ── 🔴 is_admin ─────────────────────────────────────────────────

    /**
     * 🔴 Mohem tarin test e in file: `is_admin` az mass assignment RAD
     * NEMISHE.
     *
     * Yek `$user->update($request->all())` e bi-deghat tu HAR controller e
     * ayande kafi-e ta har kasi ke `is_admin=1` be form esh ezafe kone
     * admin beshe. In test zamin ro baraye hamishe mikhkub mikone.
     */
    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::create(['phone' => '+989121234567', 'is_admin' => true]);

        $this->assertFalse($user->refresh()->is_admin, 'is_admin az mass assignment rad shod!');

        // Update ham hamin tor.
        $user->update(['is_admin' => true, 'name' => 'x']);

        $this->assertFalse($user->refresh()->is_admin);
        $this->assertSame('x', $user->name, 'field haye mojaz bayad kar konan.');
    }

    public function test_is_admin_is_set_explicitly_with_force_fill(): void
    {
        $user = User::create(['phone' => '+989121234567']);
        $user->forceFill(['is_admin' => true])->save();

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_the_factory_admin_state_works(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->refresh()->is_admin);
        $this->assertFalse(User::factory()->create()->is_admin);
    }

    // ── Cast ha va hidden ───────────────────────────────────────────

    public function test_timestamps_are_immutable_datetimes(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(CarbonImmutable::class, $user->phone_verified_at);
    }

    public function test_password_and_remember_token_are_hidden(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password' => 'secret', 'remember_token' => 'tok'])->save();

        $payload = $user->refresh()->toArray();

        // In model mostaghim tu payload e API/Livewire miad — nasht e hash
        // e password yek bug e amniati-e hatta agar hash bashe.
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('remember_token', $payload);
    }

    public function test_the_factory_builds_otp_style_users(): void
    {
        $user = User::factory()->create();

        // Default e factory bayad karbar e VAGHEI ye OTP bashe — bedun e
        // password/email — ta test haye ayande na-khasteh karbar e
        // email-dar nasazan.
        $this->assertMatchesRegularExpression('/^\+989\d{9}$/', $user->phone);
        $this->assertNull($user->password);
        $this->assertNull($user->email);
        $this->assertNotNull($user->phone_verified_at);
    }
}
