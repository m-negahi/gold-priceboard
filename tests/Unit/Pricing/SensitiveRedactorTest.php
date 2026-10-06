<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Services\SensitiveRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Redact e header/field/query ghabl az zakhire tu daftarche ye upstream
 * (P4-20). Daftarche tu panel neshun dade mishe — kelid e API e yek provider
 * e ayande nabayad unja bioftad.
 */
final class SensitiveRedactorTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function sensitiveNames(): array
    {
        return [
            'authorization' => ['Authorization'],
            'api key' => ['X-Api-Key'],
            'token' => ['access_token'],
            'secret' => ['client_secret'],
            'cookie' => ['Cookie'],
            'set-cookie' => ['Set-Cookie'],
            'password' => ['password'],
        ];
    }

    #[DataProvider('sensitiveNames')]
    public function test_sensitive_keys_are_redacted(string $name): void
    {
        $out = SensitiveRedactor::map([$name => 'hunter2', 'User-Agent' => 'PriceBoard']);

        self::assertSame(SensitiveRedactor::MASK, $out[$name]);
        self::assertSame('PriceBoard', $out['User-Agent'], 'kelid e bi-khatar nabayad dast bokhore.');
    }

    public function test_list_values_are_joined_then_redacted(): void
    {
        // `Response::headers()` har header ro LIST bar migardune.
        $out = SensitiveRedactor::map(['Content-Type' => ['application/json'], 'Set-Cookie' => ['a=1', 'b=2']]);

        self::assertSame('application/json', $out['Content-Type']);
        self::assertSame(SensitiveRedactor::MASK, $out['Set-Cookie']);
    }

    public function test_sensitive_query_values_are_redacted_in_urls(): void
    {
        $url = SensitiveRedactor::url('https://api.example.test/v1/prices?symbol=xau&api_key=hunter2&token=abc');

        self::assertStringContainsString('symbol=xau', $url);
        self::assertStringNotContainsString('hunter2', $url);
        self::assertStringNotContainsString('abc', $url);
        self::assertStringStartsWith('https://api.example.test/v1/prices?', $url);
    }

    public function test_a_url_without_a_query_is_untouched(): void
    {
        self::assertSame('https://call1.tgju.org/ajax.json', SensitiveRedactor::url('https://call1.tgju.org/ajax.json'));
    }
}
