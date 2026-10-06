<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Auth\Application\Actions\UserAdminAction;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Domain\Exceptions\UserAdminException;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P6-05 — disable/enable e karbar.
 *
 * Se masir bayad baste shan: (1) login e jadid ba OTP، (2) session e
 * GHABLAN baz (cookie/remember)، (3) HAME ye site haye karbar ru API.
 */
final class UserDisableTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '09121234567';

    private const E164 = '+989121234567';

    protected function setUp(): void
    {
        parent::setUp();

        // Kelid haye OTP e Redis beyn e test ha mimunan — pak.
        foreach (Redis::connection('default')->keys('otp:*') as $key) {
            $bare = str_replace((string) config('database.redis.options.prefix', ''), '', $key);
            Redis::connection('default')->del($bare);
        }
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create(['phone' => '+989120000000']);
    }

    private function disableVia(User $admin, User $target, string $reason = 'تخلف در استفاده'): TestResponse
    {
        return $this->actingAs($admin)->post(route('panel.users.disable', $target->id), ['reason' => $reason]);
    }

    /** Yek request e emza-shode ru `/api/v1/instruments` (majani). */
    private function signedInstruments(string $clientId, string $secret): TestResponse
    {
        $uri = '/api/v1/instruments';
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));

        return $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $clientId,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => HmacSignature::sign($secret, HmacSignature::canonical('GET', $uri, $timestamp, $nonce, '')),
        ]);
    }

    // ── Panel ───────────────────────────────────────────────────────

    public function test_an_admin_disables_a_user_with_a_reason(): void
    {
        $user = User::factory()->create();

        $this->disableVia($this->makeAdmin(), $user)
            ->assertRedirect(route('panel.users.show', $user->id));

        $user->refresh();
        $this->assertSame(UserStatus::Disabled, $user->status);
        $this->assertNotNull($user->disabled_at);
        $this->assertSame('تخلف در استفاده', $user->disabled_reason);
    }

    public function test_a_reason_is_required(): void
    {
        $user = User::factory()->create();

        $this->disableVia($this->makeAdmin(), $user, '')->assertSessionHasErrors('reason');

        $this->assertTrue($user->refresh()->isActive());
    }

    public function test_an_admin_cannot_disable_themselves(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->admin()->create();

        $this->disableVia($admin, $admin)->assertSessionHasErrors('reason');

        $this->assertTrue($admin->refresh()->isActive());
    }

    public function test_disabling_the_last_active_admin_is_refused_by_the_action(): void
    {
        $admin = $this->makeAdmin();
        $actor = User::factory()->create();

        $this->expectException(UserAdminException::class);
        app(UserAdminAction::class)->disable($admin, 'x', $actor);
    }

    public function test_enabling_clears_the_disable_fields(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();
        $this->disableVia($admin, $user);

        $this->actingAs($admin)->post(route('panel.users.enable', $user->id))
            ->assertRedirect(route('panel.users.show', $user->id));

        $user->refresh();
        $this->assertTrue($user->isActive());
        $this->assertNull($user->disabled_at);
        $this->assertNull($user->disabled_reason);
    }

    public function test_customers_cannot_reach_the_disable_routes(): void
    {
        $victim = User::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('panel.users.disable', $victim->id), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($customer)->post(route('panel.users.enable', $victim->id))->assertNotFound();

        $this->assertTrue($victim->refresh()->isActive());
    }

    public function test_the_user_page_shows_the_disable_form_and_then_the_enable_button(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)->get(route('panel.users.show', $user->id))
            ->assertOk()
            ->assertSee(route('panel.users.disable', $user->id), false);

        $this->disableVia($admin, $user);

        $this->actingAs($admin)->get(route('panel.users.show', $user->id))
            ->assertOk()
            ->assertSee(route('panel.users.enable', $user->id), false)
            ->assertSee('تخلف در استفاده');
    }

    // ── Login e jadid ───────────────────────────────────────────────

    public function test_a_disabled_user_cannot_sign_in_with_a_correct_code(): void
    {
        $user = User::factory()->create(['phone' => self::E164]);
        $this->disableVia($this->makeAdmin(), $user);
        auth()->logout();

        $this->postJson(route('login.code'), ['phone' => self::PHONE])->assertOk();
        $code = (string) $this->app->make(FakeSms::class)->lastCodeFor(self::E164);

        $this->postJson(route('login.verify'), ['phone' => self::PHONE, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'حساب کاربری شما غیرفعال شده است.');

        $this->assertGuest();
    }

    // ── Session e ghablan baz ───────────────────────────────────────

    public function test_an_open_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => UserStatus::Disabled->value, 'disabled_at' => now()])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_open_admin_session_loses_the_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['status' => UserStatus::Disabled->value, 'disabled_at' => now()])->save();

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_json_request_from_a_disabled_session_gets_403(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => UserStatus::Disabled->value, 'disabled_at' => now()])->save();

        $this->actingAs($user)->getJson('/board.json')->assertForbidden();
        $this->assertGuest();
    }

    public function test_the_login_page_tells_a_kicked_out_user_why(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => UserStatus::Disabled->value, 'disabled_at' => now()])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk()->assertSee('حساب کاربری شما غیرفعال شده است.');
    }

    public function test_disabling_rotates_the_remember_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['remember_token' => 'old-token'])->save();

        $this->disableVia($this->makeAdmin(), $user);

        $this->assertNotSame('old-token', $user->refresh()->remember_token);
    }

    // ── API ─────────────────────────────────────────────────────────

    public function test_every_site_of_a_disabled_user_gets_403_on_the_api(): void
    {
        $owner = User::factory()->create();
        $first = (new ApiClientAction)->create($owner->id, 'site-1');
        $second = (new ApiClientAction)->create($owner->id, 'site-2');
        $other = (new ApiClientAction)->create(User::factory()->create()->id, 'other');

        $this->signedInstruments($first['client']->client_id, $first['secret'])->assertOk();

        $admin = $this->makeAdmin();
        $this->disableVia($admin, $owner);

        $this->signedInstruments($first['client']->client_id, $first['secret'])
            ->assertForbidden()
            ->assertJsonPath('message', 'حساب کاربری صاحب این کلید غیرفعال است.');
        $this->signedInstruments($second['client']->client_id, $second['secret'])->assertForbidden();
        // Karbar e digar dast nakhorde.
        $this->signedInstruments($other['client']->client_id, $other['secret'])->assertOk();

        $this->actingAs($admin)->post(route('panel.users.enable', $owner->id));
        $this->signedInstruments($first['client']->client_id, $first['secret'])->assertOk();
    }

    /** 🔴 403 faghat BA'D az emza ye dorost — bi-emza hamun 401 e omumi (bedun e nasht e vaziat). */
    public function test_a_bad_signature_on_a_disabled_owner_is_still_401(): void
    {
        $owner = User::factory()->create();
        $made = (new ApiClientAction)->create($owner->id, 'site');
        $this->disableVia($this->makeAdmin(), $owner);

        $this->signedInstruments($made['client']->client_id, str_repeat('x', 40))->assertUnauthorized();
    }
}
