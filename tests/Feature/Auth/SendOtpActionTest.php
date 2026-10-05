<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Application\Actions\SendOtpAction;
use App\Modules\Auth\Domain\Exceptions\OtpException;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Auth\Support\OtpThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\InMemoryOtpStore;
use Tests\TestCase;

final class SendOtpActionTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+989121234567';

    private const IP = '203.0.113.9';

    private InMemoryOtpStore $store;

    private FakeSms $sms;

    private OtpCode $otp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryOtpStore;
        $this->sms = new FakeSms;
        $this->otp = new OtpCode('test-key');
    }

    private function action(bool $bypass = false): SendOtpAction
    {
        return new SendOtpAction(
            store: $this->store,
            otp: $this->otp,
            sms: $this->sms,
            throttle: new OtpThrottle($this->app->make(RateLimiter::class)),
            ttlSeconds: 120,
            cooldownSeconds: 60,
            digits: 6,
            bypass: $bypass,
        );
    }

    // ── Masir e salem ───────────────────────────────────────────────

    public function test_sends_a_six_digit_code_and_stores_only_its_hash(): void
    {
        $result = $this->action()->execute(self::PHONE, self::IP);

        $this->sms->assertSentTo(self::PHONE);
        $code = (string) $this->sms->lastCodeFor(self::PHONE);
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);

        // 🔴 Tu store faghat HASH hast — hich ja khod e code nist.
        $entry = $this->store->get(self::PHONE);
        self::assertNotNull($entry);
        self::assertStringNotContainsString($code, $entry->codeHash);
        self::assertTrue($this->otp->verify(self::PHONE, $code, $entry->codeHash));

        self::assertSame(120, $result->expiresInSeconds);
        self::assertSame(60, $result->resendCooldownSeconds);
        self::assertNull($result->bypassCode, 'bedun e bypass nabayad code tu result biad.');
    }

    public function test_the_raw_phone_is_normalised_before_anything(): void
    {
        $this->action()->execute('۰۹۱۲ ۱۲۳ ۴۵۶۷', self::IP);

        // SMS va store har do ba E.164.
        $this->sms->assertSentTo(self::PHONE);
        self::assertNotNull($this->store->get(self::PHONE));
    }

    public function test_bypass_returns_the_code_for_the_dev_ui(): void
    {
        $result = $this->action(bypass: true)->execute(self::PHONE, self::IP);

        // UI e dev ba hamin code dokme ye por kardan e khodkar ro misaze.
        self::assertSame($this->sms->lastCodeFor(self::PHONE), $result->bypassCode);
    }

    // ── 🔴 Enumeration safety ───────────────────────────────────────

    /**
     * Raftar baraye shomare-i ke KARBAR DARE va shomare-i ke NADARE bayad
     * yeki bashe — vagarna hamin endpoint mishe abzar e daravordan e lista
     * ye moshtari ha.
     */
    public function test_behaviour_is_identical_for_existing_and_unknown_users(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        $forExisting = $this->action()->execute(self::PHONE, self::IP);
        $forUnknown = $this->action()->execute('+989999999999', '203.0.113.10');

        self::assertEquals(
            [$forExisting->expiresInSeconds, $forExisting->resendCooldownSeconds, $forExisting->bypassCode],
            [$forUnknown->expiresInSeconds, $forUnknown->resendCooldownSeconds, $forUnknown->bypassCode],
        );
        self::assertSame(2, $this->sms->sentCount(), 'be har do bayad SMS rafte bashe.');
    }

    public function test_sending_never_creates_a_user(): void
    {
        $this->action()->execute(self::PHONE, self::IP);

        // Karbar FAGHAT ba'd az ta'eed sakhte mishe (`VerifyOtpAction`).
        self::assertSame(0, User::query()->count());
    }

    // ── Cooldown ────────────────────────────────────────────────────

    public function test_an_immediate_resend_hits_the_cooldown(): void
    {
        $action = $this->action();
        $action->execute(self::PHONE, self::IP);

        try {
            $action->execute(self::PHONE, self::IP);
            self::fail('bayad cooldown migereft.');
        } catch (OtpException $e) {
            self::assertGreaterThan(0, $e->retryAfterSeconds);
            self::assertLessThanOrEqual(60, $e->retryAfterSeconds);
        }

        self::assertSame(1, $this->sms->sentCount());
    }

    public function test_after_the_cooldown_a_resend_replaces_the_code(): void
    {
        $action = $this->action();
        $action->execute(self::PHONE, self::IP);
        $first = (string) $this->sms->lastCodeFor(self::PHONE);

        $this->store->advanceSeconds(61);
        $action->execute(self::PHONE, self::IP);

        $entry = $this->store->get(self::PHONE);
        // Code e jadid jaygozin shod — ghadimi dige kar nemikone.
        self::assertFalse($this->otp->verify(self::PHONE, $first, (string) $entry?->codeHash));
        self::assertSame(0, $entry?->attempts);
    }

    // ── 🔴 Rate limit: phone VA ip، jodagane ────────────────────────

    public function test_the_phone_limit_holds_even_across_ips(): void
    {
        $action = $this->action();

        // 3 ersal e mojaz — IP har bar fargh mikone، pas faghat PHONE
        // mitune band biare.
        foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $i => $ip) {
            $this->store->advanceSeconds(61);
            $action->execute(self::PHONE, $ip);
        }

        $this->store->advanceSeconds(61);
        $this->expectException(OtpException::class);
        $action->execute(self::PHONE, '4.4.4.4');
    }

    public function test_the_ip_limit_holds_even_across_phones(): void
    {
        $action = $this->action();

        // 10 shomare ye motefavet az YEK IP — attacker-i ke SMS-khoone ro
        // khali mikone.
        for ($i = 0; $i < 10; $i++) {
            $action->execute(sprintf('+98912%07d', $i), self::IP);
        }

        $this->expectException(OtpException::class);
        $action->execute('+989999999999', self::IP);
    }

    // ── Vorudi/Khata ────────────────────────────────────────────────

    public function test_an_invalid_phone_is_rejected_without_any_sms(): void
    {
        try {
            $this->action()->execute('0912', self::IP);
            self::fail('bayad OtpException miumad.');
        } catch (OtpException) {
            $this->sms->assertNothingSent();
        }
    }

    /**
     * 🔴 Shekast e SMS nabayad sahmie bokhore: SMS-i NARAFTE، pas karbar e
     * vaghei bayad betune FORI dobare bezane (na cooldown na throttle).
     */
    public function test_an_sms_failure_burns_no_quota_and_no_cooldown(): void
    {
        $this->sms->failWith('kavenegar kharab');
        $action = $this->action();

        try {
            $action->execute(self::PHONE, self::IP);
            self::fail('bayad OtpException miumad.');
        } catch (OtpException) {
            // Code e yatim ham nabayad munde bashe.
            self::assertNull($this->store->get(self::PHONE));
        }

        // Fori retry — bedun e hich sabr.
        $this->sms->repair();
        $action->execute(self::PHONE, self::IP);
        $this->sms->assertSentTo(self::PHONE);
    }
}
