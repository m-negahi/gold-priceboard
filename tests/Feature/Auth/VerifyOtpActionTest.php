<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Application\Actions\VerifyOtpAction;
use App\Modules\Auth\Domain\Exceptions\OtpException;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Support\OtpThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\InMemoryOtpStore;
use Tests\TestCase;

final class VerifyOtpActionTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+989121234567';

    private const IP = '203.0.113.9';

    private const CODE = '042531';

    private InMemoryOtpStore $store;

    private OtpCode $otp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryOtpStore;
        $this->otp = new OtpCode('test-key');
    }

    private function action(): VerifyOtpAction
    {
        return new VerifyOtpAction(
            store: $this->store,
            otp: $this->otp,
            throttle: new OtpThrottle($this->app->make(RateLimiter::class)),
            maxAttempts: 3,
        );
    }

    private function seedCode(string $phone = self::PHONE, string $code = self::CODE): void
    {
        $this->store->put($phone, $this->otp->hash($phone, $code), 120);
    }

    /** Talash e ghalat ke MIDUNIM exception mide. */
    private function failOnce(VerifyOtpAction $action, string $code = '000000', string $ip = self::IP): void
    {
        try {
            $action->execute(self::PHONE, $code, $ip);
            self::fail('code e ghalat nabayad ghabul mishod.');
        } catch (OtpException) {
            // entezar.
        }
    }

    // ── Masir e salem ───────────────────────────────────────────────

    public function test_a_correct_code_signs_up_a_new_user(): void
    {
        $this->seedCode();

        $user = $this->action()->execute(self::PHONE, self::CODE, self::IP);

        self::assertSame(self::PHONE, $user->phone);
        self::assertNotNull($user->phone_verified_at);
        self::assertNotNull($user->last_login_at);
        self::assertFalse($user->is_admin);
        self::assertSame(1, User::query()->count());
    }

    public function test_a_correct_code_logs_in_an_existing_user(): void
    {
        $existing = User::factory()->create([
            'phone' => self::PHONE,
            'last_login_at' => now()->subDay(),
        ]);
        $this->seedCode();

        $user = $this->action()->execute(self::PHONE, self::CODE, self::IP);

        self::assertSame($existing->id, $user->id, 'nabayad karbar e dovom sakhte she.');
        self::assertTrue($user->last_login_at->isAfter(now()->subMinute()));
        self::assertSame(1, User::query()->count());
    }

    public function test_the_raw_phone_is_normalised(): void
    {
        $this->seedCode();

        $user = $this->action()->execute('۰۹۱۲ ۱۲۳ ۴۵۶۷', self::CODE, self::IP);

        self::assertSame(self::PHONE, $user->phone);
    }

    // ── 🔴 Yek-bar-masraf ───────────────────────────────────────────

    public function test_a_code_can_only_be_used_once(): void
    {
        $this->seedCode();
        $action = $this->action();

        $action->execute(self::PHONE, self::CODE, self::IP);

        // Hamun code، bar e dovom — masalan replay e yek form e dobare
        // submit-shode ya yek attacker ke SMS ro khunde.
        $this->expectException(OtpException::class);
        $action->execute(self::PHONE, self::CODE, self::IP);
    }

    // ── 🔴 Batel shodan ba'd az 3 ghalat ────────────────────────────

    /**
     * Na faghat shomordan: ba'd az saghf، hatta code e DOROST ham dige kar
     * nemikone. 6 ragham = 10^6 halat؛ bedun e batel kardan، hads zadan
     * faghat mas'ale ye sor'at ast.
     */
    public function test_three_wrong_attempts_kill_the_code_for_good(): void
    {
        $this->seedCode();
        $action = $this->action();

        $this->failOnce($action, '111111');
        $this->failOnce($action, '222222');
        $this->failOnce($action, '333333');

        // Entry kolan rafte.
        self::assertNull($this->store->get(self::PHONE));

        // Code e DOROST ham dige fayde nadare.
        $this->expectException(OtpException::class);
        $action->execute(self::PHONE, self::CODE, self::IP);
    }

    public function test_two_wrong_attempts_then_the_right_code_still_works(): void
    {
        $this->seedCode();
        $action = $this->action();

        $this->failOnce($action, '111111');
        $this->failOnce($action, '222222');

        // Karbar e vaghei ke do bar typo zade.
        $user = $action->execute(self::PHONE, self::CODE, self::IP);

        self::assertSame(self::PHONE, $user->phone);
    }

    // ── 🔴 Enumeration: yek payam baraye hame ye shekast ha ─────────

    public function test_wrong_expired_and_never_sent_all_fail_with_the_same_message(): void
    {
        $action = $this->action();
        $messages = [];

        // 1) aslan code-i ferestade nashode
        try {
            $action->execute(self::PHONE, self::CODE, '1.1.1.1');
        } catch (OtpException $e) {
            $messages[] = $e->getMessage();
        }

        // 2) code e ghalat
        $this->seedCode();
        try {
            $action->execute(self::PHONE, '999999', '2.2.2.2');
        } catch (OtpException $e) {
            $messages[] = $e->getMessage();
        }

        // 3) monghazi
        $this->store->advanceSeconds(121);
        try {
            $action->execute(self::PHONE, self::CODE, '3.3.3.3');
        } catch (OtpException $e) {
            $messages[] = $e->getMessage();
        }

        self::assertCount(3, $messages);
        self::assertCount(1, array_unique($messages), 'har se shekast bayad YEK payam dashte bashan.');
    }

    public function test_a_failed_verify_never_creates_a_user(): void
    {
        $this->seedCode();
        $this->failOnce($this->action(), '999999');

        self::assertSame(0, User::query()->count());
    }

    // ── Rate limit ──────────────────────────────────────────────────

    public function test_the_phone_verify_limit_holds_across_ips(): void
    {
        $this->seedCode();
        $action = $this->action();

        for ($i = 1; $i <= 5; $i++) {
            // Har bar code e taze، ta faghat THROTTLE band biare na
            // `max_attempts` (un yeki jodagane test shode).
            $this->seedCode();
            $this->failOnce($action, '999999', "10.0.0.{$i}");
        }

        $this->seedCode();
        try {
            $action->execute(self::PHONE, self::CODE, '10.0.0.99');
            self::fail('bayad throttle migereft.');
        } catch (OtpException $e) {
            self::assertGreaterThan(0, $e->retryAfterSeconds);
        }
    }

    public function test_the_ip_verify_limit_holds_across_phones(): void
    {
        $action = $this->action();

        for ($i = 0; $i < 20; $i++) {
            $phone = sprintf('+98912%07d', $i);
            $this->store->put($phone, $this->otp->hash($phone, self::CODE), 120);

            try {
                $action->execute($phone, '999999', self::IP);
            } catch (OtpException) {
                // entezar — code ghalat e.
            }
        }

        $this->seedCode();
        $this->expectException(OtpException::class);
        $this->action()->execute(self::PHONE, self::CODE, self::IP);
    }

    /**
     * Ba'd az login e movafagh، shomaresh e PHONE pak mishe — karbar e
     * vaghei ke emrooz do bar login mikone (ba chand typo) nabayad gir kone.
     */
    public function test_a_successful_login_clears_the_phone_counter(): void
    {
        $action = $this->action();

        // 4 talash (3 ghalat ru code e aval + 1 dorost ru code e dovom).
        $this->seedCode();
        $this->failOnce($action, '111111');
        $this->failOnce($action, '222222');
        $this->seedCode();
        $action->execute(self::PHONE, self::CODE, self::IP);

        // Login e dovom tu hamun sa'at: bedun e clear، talash e 6om (2 ta ye
        // paeen) az saghf e 5 rad mishod.
        $this->seedCode('+989121234567', '777777');
        $this->failOnce($action, '111111');
        $user = $action->execute(self::PHONE, '777777', self::IP);

        self::assertSame(self::PHONE, $user->phone);
    }
}
