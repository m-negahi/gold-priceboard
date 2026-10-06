<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Billing\Infrastructure\Payments\VandarGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Adapter e Vandar ru pasokh haye FAKE (hich request e vaghei). Shekl e
 * payload az mostanad e rasmi ye IPG v3.
 */
final class VandarGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('billing.vandar.api_key', 'test-key');
    }

    public function test_send_returns_the_token_and_redirect_url(): void
    {
        Http::fake(['https://ipg.vandar.io/api/v3/send' => Http::response(['status' => 1, 'token' => 'AbC123xyz'], 200)]);

        $result = (new VandarGateway)->request(250_000, 'https://pb.test/payments/callback', '42');

        self::assertSame('AbC123xyz', $result['track_id']);
        self::assertSame('https://ipg.vandar.io/v3/AbC123xyz', $result['url']);
        self::assertSame('vandar', (new VandarGateway)->name());

        Http::assertSent(static fn ($request): bool => $request['api_key'] === 'test-key'
            && $request['amount'] === 250_000
            && $request['callback_url'] === 'https://pb.test/payments/callback'
            && $request['factorNumber'] === '42');
    }

    public function test_send_rejects_gateway_errors_with_their_message(): void
    {
        Http::fake(['https://ipg.vandar.io/api/v3/send' => Http::response(['status' => 0, 'errors' => ['کلید نامعتبر']], 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('کلید نامعتبر');

        (new VandarGateway)->request(250_000, 'https://pb.test/cb', '1');
    }

    public function test_amount_below_minimum_is_rejected_before_any_request(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        (new VandarGateway)->request(999, 'https://pb.test/cb', '1');

        Http::assertNothingSent();
    }

    public function test_verify_marks_status_one_and_two_as_paid_with_amount_and_reference(): void
    {
        Http::fake(['https://ipg.vandar.io/api/v3/verify' => Http::sequence()
            ->push(['status' => 1, 'amount' => 250_000, 'realAmount' => 247_500, 'wage' => 2_500, 'transId' => 998877, 'factorNumber' => '42', 'cardNumber' => '6037-xxxx'], 200)
            ->push(['status' => 2, 'amount' => 250_000, 'transId' => 998877, 'factorNumber' => '42'], 200)]);

        $first = (new VandarGateway)->verify('AbC123xyz');
        $second = (new VandarGateway)->verify('AbC123xyz');

        self::assertTrue($first['verified']);
        self::assertSame(250_000, $first['amount_rial']);
        self::assertSame('998877', $first['reference']);
        self::assertSame('42', $first['data']['orderId']);
        self::assertSame(247_500, $first['data']['realAmount']);
        // Hich kelid-i tu data nist.
        self::assertArrayNotHasKey('api_key', $first['data']);

        self::assertTrue($second['verified'], 'status 2 = ghablan verify shode؛ pardakht e vaghei-e.');
    }

    public function test_verify_treats_failed_expired_or_zero_amount_as_unpaid(): void
    {
        Http::fake(['https://ipg.vandar.io/api/v3/verify' => Http::sequence()
            ->push(['status' => 0, 'errors' => ['تراکنش ناموفق']], 200)
            ->push(['status' => 3, 'amount' => 250_000], 200)
            ->push(['status' => 1, 'amount' => 0], 200)]);

        foreach (['status0', 'status3', 'zero'] as $case) {
            $result = (new VandarGateway)->verify('tok');
            self::assertFalse($result['verified'], $case);
        }
    }

    public function test_missing_api_key_is_a_configuration_error(): void
    {
        config()->set('billing.vandar.api_key', '');
        Http::fake();

        $this->expectException(RuntimeException::class);
        (new VandarGateway)->request(250_000, 'https://pb.test/cb', '1');
    }

    public function test_v4_switches_the_version_segment_only(): void
    {
        config()->set('billing.vandar.version', 'v4');
        Http::fake(['https://ipg.vandar.io/api/v4/send' => Http::response(['status' => 1, 'token' => 'T4'], 200)]);

        $result = (new VandarGateway)->request(250_000, 'https://pb.test/cb', '7');

        self::assertSame('https://ipg.vandar.io/v4/T4', $result['url']);
    }
}
