<?php

declare(strict_types=1);

namespace Tests\Feature\Kyc;

use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Kyc\Infrastructure\Zohal\ZohalIdentityVerification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ZohalVerificationTest extends TestCase
{
    public function test_binding_and_missing_token_return_warning_without_network(): void
    {
        Http::preventStrayRequests();
        config(['modules.kyc.token' => '']);
        $port = app(IdentityVerificationPort::class);
        self::assertInstanceOf(ZohalIdentityVerification::class, $port);
        self::assertSame('warning', $port->verify('shahkar', [])->status);
        Http::assertNothingSent();
    }

    public function test_shahkar_posts_documented_payload_and_classifies_mismatch(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sandbox.zohal.io/api/v0/services/inquiry/shahkar' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/zohal/shahkar-not-matched.json')), true))]);
        $result = (new ZohalIdentityVerification('fixture-token', 'https://sandbox.zohal.io/api/v0'))->verify('shahkar', ['mobile' => '09121111111', 'national_code' => '0499370899']);
        self::assertSame('failed', $result->status);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fixture-token') && $request['national_code'] === '0499370899' && $request['mobile'] === '09121111111');
        Http::assertSentCount(1);
    }

    public function test_business_errors_and_malformed_success_are_not_successful_checks(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sandbox.zohal.io/api/v0/services/inquiry/shahkar' => Http::sequence()
            ->push(json_decode(file_get_contents(base_path('tests/Fixtures/zohal/error-invalid-national-code.json')), true))
            ->push(['result' => 1, 'response_body' => ['data' => ['matched' => 'false']]])
            ->push('not json', 200)]);
        $adapter = new ZohalIdentityVerification('fixture-token', 'https://sandbox.zohal.io/api/v0');
        self::assertSame('warning', $adapter->verify('shahkar', [])->status);
        self::assertSame('warning', $adapter->verify('shahkar', [])->status);
        self::assertSame('warning', $adapter->verify('shahkar', [])->status);
        Http::assertSentCount(3);
    }

    public function test_secret_fields_and_echoed_token_are_removed_from_raw_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sandbox.zohal.io/api/v0/services/inquiry/shahkar' => Http::response(['result' => 1, 'response_body' => ['data' => ['matched' => true, 'token' => 'secret', 'nested' => ['api_key' => 'hidden']], 'message' => 'fixture-token']])]);
        $result = (new ZohalIdentityVerification('fixture-token', 'https://sandbox.zohal.io/api/v0'))->verify('shahkar', []);
        self::assertSame('passed', $result->status);
        self::assertSame('[redacted]', $result->raw['response_body']['data']['token']);
        self::assertSame('[redacted]', $result->raw['response_body']['data']['nested']['api_key']);
        self::assertSame('[redacted]', $result->raw['response_body']['message']);
        Http::assertSentCount(1);
    }

    public function test_expired_enamad_and_dead_identity_are_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox.zohal.io/api/v0/services/inquiry/enamad_inquiry' => Http::response(['result' => 1, 'response_body' => ['data' => ['domain' => 'tala.example', 'expired' => true]]]),
            'https://sandbox.zohal.io/api/v0/services/inquiry/national_identity_inquiry' => Http::response(['result' => 1, 'response_body' => ['data' => ['matched' => true, 'alive' => false]]]),
        ]);
        $adapter = new ZohalIdentityVerification('fixture-token', 'https://sandbox.zohal.io/api/v0');
        self::assertSame('failed', $adapter->verify('enamad', ['website' => 'tala.example'])->status);
        self::assertSame('failed', $adapter->verify('national_identity', [])->status);
        Http::assertSentCount(2);
    }
}
