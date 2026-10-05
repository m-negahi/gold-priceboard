<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Otp\RedisOtpStore;
use Illuminate\Support\ServiceProvider;

/**
 * Hamun ghanun e `PricingServiceProvider`: har bind yek test e assert dare
 * (`AuthBindingTest`) — bind-i ke resolve nashe shekast e SAKET ast.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/otp.php', 'otp');

        $this->app->singleton(OtpStorePort::class, static fn (): OtpStorePort => new RedisOtpStore(
            (string) config('otp.connection', 'default'),
        ));

        // Kelid e HMAC az APP_KEY — `OtpCode` khodesh kelid e khali ro rad
        // mikone، pas yek APP_KEY e set-nashode hamin ja miterke، na saket.
        $this->app->singleton(OtpCode::class, static fn (): OtpCode => new OtpCode(
            (string) config('app.key'),
        ));
    }
}
