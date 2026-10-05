<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Otp\RedisOtpStore;
use Tests\TestCase;

/**
 * Hamun ghanun e `PricingBindingTest`: bind-i ke resolve nashe shekast e
 * SAKET ast — har bind yek assert.
 */
final class AuthBindingTest extends TestCase
{
    public function test_the_otp_store_port_resolves_to_redis(): void
    {
        self::assertInstanceOf(RedisOtpStore::class, $this->app->make(OtpStorePort::class));
    }

    public function test_the_otp_store_is_memoized(): void
    {
        self::assertSame($this->app->make(OtpStorePort::class), $this->app->make(OtpStorePort::class));
    }

    public function test_otp_code_resolves_with_the_app_key(): void
    {
        $otp = $this->app->make(OtpCode::class);

        // Kelid az APP_KEY umade — pas hash e yek code sabet va tekrar-pazir
        // ast tu hamin process.
        self::assertSame(
            $otp->hash('+989121234567', '123456'),
            $this->app->make(OtpCode::class)->hash('+989121234567', '123456'),
        );
    }

    public function test_the_otp_config_is_merged(): void
    {
        self::assertSame(120, config('otp.ttl_seconds'));
        self::assertSame(3, config('otp.max_attempts'));
        self::assertSame(6, config('otp.digits'));
        self::assertFalse((bool) config('otp.bypass'));
    }
}
