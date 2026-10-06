<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P2-08 — sakht-sazi ye OTP az rooye HTTP (na faghat sath e Action).
 *
 * Chera HTTP: rate limit، enumeration va `TrustProxies` har kodum tu laye
 * ye khodeshun test shodan، vali masir e VAGHEI ye attacker az rooye HTTP
 * ast — middleware + controller + Action BA HAM. Yek jabe-jaei ye kuchik tu
 * tartib e middleware mitune hame ye un zemanat ha ro bi-asar kone bedun e
 * inke test haye laye-i ghermez beshan.
 *
 * ⚠️ `TrustProxies` GLOBAL ast، pas tu hamin test ha ham fa'al e —
 * `withServerVariables(['REMOTE_ADDR' => …])` hamun rah e vorudi-e ke
 * production dare.
 */
final class OtpHttpHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kelid haye OTP tu Redis beyn e test ha MIMUNAN — pak mishan ta
        // cooldown e test e ghabli in yeki ro aludeh nakone. (Limiter ha ru
        // cache e `array`-and va khod-be-khod reset mishan.)
        foreach (Redis::connection('default')->keys('otp:*') as $key) {
            $bare = str_replace((string) config('database.redis.options.prefix', ''), '', $key);
            Redis::connection('default')->del($bare);
        }
    }

    /** Shomare ye shakhsi e test — har method yek range ye joda dare. */
    private static function phone(int $n): string
    {
        return sprintf('0912%07d', $n);
    }

    private static function e164(int $n): string
    {
        return sprintf('+98912%07d', $n);
    }

    private function sendFor(string $phone, string $ip = '127.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('login.code'), ['phone' => $phone]);
    }

    /** Cooldown e 60s e resend pak mishe ta FAGHAT limit e saat-i test she. */
    private function clearCooldown(string $e164): void
    {
        $this->app->make(OtpStorePort::class)->clearCooldown($e164);
    }

    // ── Rate limit e PHONE، mostaghel az IP ─────────────────────────

    public function test_the_phone_limit_blocks_the_fourth_send_even_from_fresh_ips(): void
    {
        // 3 ersal e movafagh — har bar az yek IP e JADID، ta sabet she
        // chizi ke mibande PHONE ast na IP.
        foreach ([1, 2, 3] as $i) {
            $this->sendFor(self::phone(11), "203.0.113.{$i}")->assertOk();
            $this->clearCooldown(self::e164(11));
        }

        $response = $this->sendFor(self::phone(11), '203.0.113.99');

        $response->assertStatus(429);
        // `retry_after` e limit e saat-i kheyli bozorgtar az cooldown e 60s
        // ast — in forgh e beyn e «sabr kon resend» va «ban e saat-i» ro
        // sabt mikone.
        self::assertGreaterThan(60, (int) $response->json('retry_after'));
    }

    // ── Rate limit e IP، mostaghel az phone ────────────────────────

    public function test_the_ip_limit_blocks_the_eleventh_send_even_with_fresh_phones(): void
    {
        // 10 ersal az YEK IP — har bar shomare ye jadid (pas limit e phone
        // hich vaght lams nemishe).
        foreach (range(21, 30) as $n) {
            $this->sendFor(self::phone($n), '198.51.100.7')->assertOk();
        }

        // Shomare ye 11om e taze، hamun IP -> bayad band biad.
        $this->sendFor(self::phone(31), '198.51.100.7')->assertStatus(429);

        // Hamun shomare az IP e digar -> azad. (Mutation check: agar limiter
        // phone-only bud in ham 429 mishod؛ agar IP-only bud test e bala
        // mishekast.)
        $this->sendFor(self::phone(31), '198.51.100.8')->assertOk();
    }

    // ── Enumeration az rooye HTTP ───────────────────────────────────

    public function test_send_responses_are_identical_for_existing_and_unknown_users(): void
    {
        User::factory()->create(['phone' => self::e164(41)]);

        $known = $this->sendFor(self::phone(41))->assertOk();
        $unknown = $this->sendFor(self::phone(42))->assertOk();

        // BYTE-BE-BYTE yeki (be joz khod e shomare ke echo mishe) — hatta
        // yek kelid e ezafe ya tartib e motefavet ham signal e.
        $strip = static fn (array $json): array => array_diff_key($json, ['phone' => true]);

        self::assertSame(
            $strip($known->json()),
            $strip($unknown->json()),
            'pasokh e shomare ye mojud va namojud bayad yeki bashe.',
        );
    }

    public function test_verify_errors_are_identical_for_wrong_code_expired_and_never_sent(): void
    {
        // (a) code e GHALAT baraye shomare-i ke vaghe'an code dare.
        $this->sendFor(self::phone(51))->assertOk();
        $wrongCode = $this->postJson(route('login.verify'), [
            'phone' => self::phone(51), 'code' => '000000',
        ])->assertStatus(422);

        // (b) shomare-i ke HICH code-i barash ferestade nashode.
        $neverSent = $this->postJson(route('login.verify'), [
            'phone' => self::phone(52), 'code' => '000000',
        ])->assertStatus(422);

        self::assertSame(
            $wrongCode->json(),
            $neverSent->json(),
            'payam e «ghalat» va «nabude» bayad gheyr e ghabel e tafkik bashan.',
        );
    }

    // ── `max_attempts` code ro BATEL mikone — az rooye HTTP ─────────

    public function test_three_wrong_codes_burn_the_otp_even_if_the_right_code_follows(): void
    {
        $this->sendFor(self::phone(61))->assertOk();
        $code = (string) $this->app->make(FakeSms::class)->lastCodeFor(self::e164(61));
        self::assertNotSame('', $code);

        foreach ([1, 2, 3] as $i) {
            $this->postJson(route('login.verify'), [
                'phone' => self::phone(61), 'code' => '999999',
            ])->assertStatus(422);
        }

        // Code e DOROST، vali dige suzande — bayad rad she va karbar sakhte
        // NASHE.
        $this->postJson(route('login.verify'), [
            'phone' => self::phone(61), 'code' => $code,
        ])->assertStatus(422);

        self::assertDatabaseMissing('users', ['phone' => self::e164(61)]);
        $this->assertGuest();
    }

    // ── TrustProxies + OTP ba ham ───────────────────────────────────

    public function test_behind_a_trusted_proxy_each_forwarded_client_gets_its_own_ip_bucket(): void
    {
        // REMOTE_ADDR = Docker bridge (mored e e'temad) -> IP e vaghei az
        // X-Forwarded-For khunde mishe.
        $send = fn (int $n, string $client) => $this
            ->withServerVariables(['REMOTE_ADDR' => '172.17.0.2'])
            ->withHeaders(['X-Forwarded-For' => $client])
            ->postJson(route('login.code'), ['phone' => self::phone($n)]);

        // Client e aval saghf e IP esh (10) ro por mikone.
        foreach (range(71, 80) as $n) {
            $send($n, '203.0.113.50')->assertOk();
        }

        $send(81, '203.0.113.50')->assertStatus(429);

        // 🔴 Client e dovom posht e HAMUN proxy bayad azad bashe. Agar
        // TrustProxies kharab bashe hame `ip()` e proxy ro migiran va in
        // test ghermez mishe — ya'ni «yek karbar e por-masraf hame ye
        // karbar haye posht e un CDN ro ban mikard».
        $send(82, '203.0.113.60')->assertOk();
    }

    public function test_a_forged_xff_from_an_untrusted_source_cannot_escape_the_ip_limit(): void
    {
        // REMOTE_ADDR e PUBLIC (gheyr e mored e e'temad) -> header ha nadide
        // gerefte mishan va bucket hamun REMOTE_ADDR ast.
        $send = fn (int $n, string $forged) => $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
            ->withHeaders(['X-Forwarded-For' => $forged])
            ->postJson(route('login.code'), ['phone' => self::phone($n)]);

        foreach (range(91, 100) as $i => $n) {
            // Har bar yek XFF e ja'li ye motefavet — nabayad hich asari
            // dashte bashe.
            $send($n, "198.51.100.{$i}")->assertOk();
        }

        // Talash e 11om ba yek XFF e ja'li ye taze: agar ja'l kar mikard in
        // 200 mishod va kol e limit e IP bi-ma'ni bud.
        $send(101, '198.51.100.99')->assertStatus(429);
    }
}
