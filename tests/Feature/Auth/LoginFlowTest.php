<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Application\Actions\SendOtpAction;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Flow e KAMEL e login az rooye HTTP — controller e nazok + Action haye
 * vaghei + store ye Redis e vaghei (db e 13 e test) + FakeSms e singleton.
 */
final class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '09121234567';

    private const E164 = '+989121234567';

    protected function setUp(): void
    {
        parent::setUp();

        // Kelid haye OTP e Redis beyn e test ha MIMUNAN (bar khalaf e cache
        // e array) — cooldown e test e ghabli nabayad test e ba'di ro band
        // biare.
        foreach (Redis::connection('default')->keys('otp:*') as $key) {
            $bare = str_replace((string) config('database.redis.options.prefix', ''), '', $key);
            Redis::connection('default')->del($bare);
        }
    }

    private function fakeSms(): FakeSms
    {
        return $this->app->make(FakeSms::class);
    }

    /** @return string code-i ke «SMS shod» */
    private function requestCode(string $phone = self::PHONE): string
    {
        $this->postJson(route('login.code'), ['phone' => $phone])->assertOk();

        return (string) $this->fakeSms()->lastCodeFor(self::E164);
    }

    // ── Safhe ───────────────────────────────────────────────────────

    public function test_a_guest_sees_the_login_page_at_step_phone(): void
    {
        // ⚠️ `@js()` khoruji ro escape mikone، pas assertSee ru JSON e kham
        // kar nemikone — `assertViewHas` mostaghim data ye view ro mibine.
        $this->get('/login')
            ->assertOk()
            ->assertSee('ورود به حساب')
            ->assertViewHas('initial', fn (array $i): bool => $i['step'] === 'phone');
    }

    public function test_a_logged_in_user_is_bounced_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect();
    }

    /**
     * 🔴 Refresh nabayad flow ro beshkane: step ro SERVER tasmim migire.
     * Ba'd az ferestadan e code، baz kardan e dobare ye /login bayad step e
     * CODE bashe ba shomarande ye server.
     */
    public function test_a_refresh_lands_back_on_the_code_step_with_server_counters(): void
    {
        $this->requestCode();

        $this->get('/login')
            ->assertOk()
            ->assertViewHas('initial', fn (array $i): bool => $i['step'] === 'code'
                && $i['phone'] === self::E164
                && $i['resendIn'] > 0
                && $i['expiresIn'] > 0
                && $i['code'] === null
                // Bedun e bypass hich vaght «pending» nist.
                && $i['bypassPending'] === false);
    }

    // ── Gereftan e code ─────────────────────────────────────────────

    public function test_requesting_a_code_sends_an_sms_and_returns_counters(): void
    {
        $response = $this->postJson(route('login.code'), ['phone' => self::PHONE]);

        $response->assertOk()
            ->assertJsonPath('resend_in', 60)
            ->assertJsonPath('expires_in', 120)
            // 🔴 Bedun e bypass، kelid e `code` ASLAN nabayad bashe.
            ->assertJsonMissingPath('code');

        $this->fakeSms()->assertSentTo(self::E164);
    }

    public function test_bypass_exposes_the_code_in_the_response(): void
    {
        config()->set('otp.bypass', true);
        $this->app->forgetInstance(SendOtpAction::class);

        $this->postJson(route('login.code'), ['phone' => self::PHONE])
            ->assertOk()
            ->assertJsonPath('code', fn ($code): bool => is_string($code) && strlen($code) === 6);
    }

    public function test_an_immediate_resend_gets_429_with_retry_after(): void
    {
        $this->requestCode();

        $this->postJson(route('login.code'), ['phone' => self::PHONE])
            ->assertStatus(429)
            ->assertJsonPath('retry_after', fn ($s): bool => is_int($s) && $s > 0 && $s <= 60)
            // 🔴 Bedun e bypass in kelid ASLAN nabayad bashe.
            ->assertJsonMissingPath('bypass_pending');
    }

    // ── 🔴 Bypass + cooldown e session e DIGAR ──────────────────────

    /**
     * Bon-bast e saket ke vaghe'an gir kardim: code ru hamun shomare tu yek
     * session e DIGAR (browser/e2e e digar) sakhte shode. Store faghat HASH
     * negah midare، pas code e ghadimi ghabl e reveal NIST. Bedun e in flag
     * dev ru step e code mioftad bedun e code va bedun e hich tozih.
     *
     * Hal: cooldown ghat' NEMISHE (limit ha armed mimunan) — faghat zire
     * bypass flag midim ta UI bege «sabr kon va ارسال دوباره bezan».
     */
    private function codeFromAnotherSession(): void
    {
        config()->set('otp.bypass', true);
        $this->app->forgetInstance(SendOtpAction::class);

        $this->postJson(route('login.code'), ['phone' => self::PHONE])->assertOk();

        // Browser e jadid: session khali، vali state e OTP e Redis mimune.
        $this->app['session']->flush();
    }

    public function test_bypass_flags_a_code_issued_to_another_session(): void
    {
        $this->codeFromAnotherSession();

        $this->postJson(route('login.code'), ['phone' => self::PHONE])
            ->assertStatus(429)
            ->assertJsonPath('bypass_pending', true)
            // Code e ghadimi reveal NEMISHE — faghat hash dar dastres e.
            ->assertJsonMissingPath('code');
    }

    public function test_a_refresh_after_another_sessions_code_flags_bypass_pending(): void
    {
        $this->codeFromAnotherSession();

        // 429 shomare ro tu session e jadid sabt mikone…
        $this->postJson(route('login.code'), ['phone' => self::PHONE])->assertStatus(429);

        // …pas refresh be step e code mire — bayad befahme code nadarim.
        $this->get('/login')
            ->assertOk()
            ->assertViewHas('initial', fn (array $i): bool => $i['step'] === 'code'
                && $i['code'] === null
                && $i['bypassPending'] === true);
    }

    public function test_the_session_that_owns_the_code_is_not_pending(): void
    {
        config()->set('otp.bypass', true);
        $this->app->forgetInstance(SendOtpAction::class);

        $this->postJson(route('login.code'), ['phone' => self::PHONE])->assertOk();

        // Hamun session — code dare، pas «pending» nist.
        $this->get('/login')
            ->assertOk()
            ->assertViewHas('initial', fn (array $i): bool => is_string($i['code'])
                && $i['bypassPending'] === false);
    }

    public function test_an_invalid_phone_is_a_validation_error(): void
    {
        $this->postJson(route('login.code'), ['phone' => '0912'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // ── Verify + vorud ──────────────────────────────────────────────

    public function test_the_full_flow_signs_in_a_new_user_and_redirects_home(): void
    {
        $code = $this->requestCode();

        $this->postJson(route('login.verify'), ['phone' => self::PHONE, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('redirect', route('home'));

        $this->assertAuthenticated();
        $this->assertSame(self::E164, User::query()->sole()->phone);
    }

    public function test_an_admin_is_redirected_to_the_dashboard(): void
    {
        User::factory()->admin()->create(['phone' => self::E164]);
        $code = $this->requestCode();

        $this->postJson(route('login.verify'), ['phone' => self::PHONE, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('redirect', route('panel.dashboard'));
    }

    public function test_a_wrong_code_is_a_validation_error_and_leaves_the_guest_out(): void
    {
        $this->requestCode();

        $this->postJson(route('login.verify'), ['phone' => self::PHONE, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    // ── Darvaze ye panel ────────────────────────────────────────────

    public function test_a_guest_is_sent_to_login_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /**
     * 🔴 404، na 403: yek 403 be karbar e login-shode mige «in masir vojud
     * dare vali to nemituni» — masir e panel ro TA'EED mikone.
     */
    public function test_a_non_admin_gets_a_404_not_a_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertNotFound();
    }

    public function test_an_admin_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('میزکار');
    }
}
