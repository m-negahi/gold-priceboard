<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Infrastructure\Sms\KavenegarSms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class KavenegarSmsTest extends TestCase
{
    private const KEY = 'SECRET-API-KEY-123';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function driver(string $key = self::KEY): KavenegarSms
    {
        return new KavenegarSms([
            'api_key' => $key,
            'template' => 'pb-verify',
            'base_url' => 'https://api.kavenegar.test/v1',
        ], timeout: 5);
    }

    /** @param array<string, mixed> $body */
    private function fakeOk(array $body = ['return' => ['status' => 200, 'message' => 'tayid']]): void
    {
        Http::fake(['api.kavenegar.test/*' => Http::response($body)]);
    }

    // ── Masir e movafagh ────────────────────────────────────────────

    public function test_sends_through_verify_lookup_with_the_local_receptor(): void
    {
        $this->fakeOk();

        $this->driver()->sendOtp('+989121234567', '042531');

        Http::assertSent(static function (Request $request): bool {
            return str_contains($request->url(), '/verify/lookup.json')
                // Kavenegar format e MAHALLI mikhad، na E.164.
                && $request['receptor'] === '09121234567'
                && $request['token'] === '042531'
                && $request['template'] === 'pb-verify';
        });
    }

    // ── 🔴 Dam e 1: khata ba HTTP 200 ───────────────────────────────

    /**
     * Kavenegar khata ha ro ba HTTP 200 barmigardune — code e vaghei tu
     * `return.status` e body ast. Faghat `successful()` ro check koni،
     * shomare ye namotabar va e'tebar e tamam-shode har do «movafagh» dide
     * mishan va SMS hargez nemirese، bedun e hich khata.
     */
    public function test_an_api_error_inside_an_http_200_is_a_failure(): void
    {
        $this->fakeOk(['return' => ['status' => 418, 'message' => 'e\'tebar kafi nist']]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessageMatches('/418/');

        $this->driver()->sendOtp('+989121234567', '042531');
    }

    public function test_an_http_error_status_is_a_failure(): void
    {
        Http::fake(['api.kavenegar.test/*' => Http::response('', 503)]);

        $this->expectExceptionMessageMatches('/503/');
        $this->driver()->sendOtp('+989121234567', '042531');
    }

    // ── 🔴 Dam e 2: kelid tu URL ────────────────────────────────────

    /**
     * 🔴 `ConnectionException` e Laravel URL e KAMEL ro tu payam esh dare —
     * va tu Kavenegar API key dakhel e URL ast. Agar exception e ma jozyat
     * e un ro pass bede، kelid tu log/Telescope mishine.
     */
    public function test_a_connection_failure_never_leaks_the_api_key(): void
    {
        Http::fake(static function (): never {
            throw new ConnectionException(
                'cURL error 28: timeout for https://api.kavenegar.test/v1/'.self::KEY.'/verify/lookup.json',
            );
        });

        try {
            $this->driver()->sendOtp('+989121234567', '042531');
            self::fail('bayad SmsException miumad.');
        } catch (SmsException $e) {
            self::assertStringNotContainsString(self::KEY, $e->getMessage());
            self::assertStringNotContainsString('http', $e->getMessage());
        }
    }

    // ── Vorudi haye kharab ──────────────────────────────────────────

    public function test_a_missing_api_key_fails_before_any_http(): void
    {
        Http::fake();

        try {
            $this->driver(key: '')->sendOtp('+989121234567', '042531');
            self::fail('bayad SmsException miumad.');
        } catch (SmsException) {
            Http::assertNothingSent();
        }
    }

    public function test_an_invalid_phone_fails_masked_and_without_http(): void
    {
        Http::fake();

        try {
            $this->driver()->sendOtp('021-2842', '042531');
            self::fail('bayad SmsException miumad.');
        } catch (SmsException $e) {
            // Shomare (har chi ke hast) kamel tu payam e log nabayad biad.
            self::assertStringNotContainsString('021-2842', $e->getMessage());
            Http::assertNothingSent();
        }
    }
}
