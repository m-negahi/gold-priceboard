<?php

declare(strict_types=1);

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Application\Actions\SendOtpAction;
use App\Modules\Auth\Application\Actions\VerifyOtpAction;
use App\Modules\Auth\Console\Commands\OtpFlushCommand;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Auth\Domain\Services\OtpCode;
use App\Modules\Auth\Infrastructure\Otp\RedisOtpStore;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Auth\Infrastructure\Sms\SmsManager;
use App\Modules\Auth\Support\OtpThrottle;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Hamun ghanun e `PricingServiceProvider`: har bind yek test e assert dare
 * (`AuthBindingTest`) — bind-i ke resolve nashe shekast e SAKET ast.
 */
final class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'auth');

        if ($this->app->runningInConsole()) {
            $this->commands([
                OtpFlushCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/otp.php', 'otp');
        $this->mergeConfigFrom(__DIR__.'/../Config/sms.php', 'sms');

        $this->app->singleton(OtpStorePort::class, static fn (): OtpStorePort => new RedisOtpStore(
            (string) config('otp.connection', 'default'),
        ));

        // 🔴 `FakeSms` bayad singleton bashe: instance-i ke action behesh
        // mifereste va instance-i ke test assert mikone bayad YEKI bashan.
        $this->app->singleton(FakeSms::class, static fn ($app): FakeSms => new FakeSms(
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(SmsPort::class, static fn ($app): SmsPort => SmsManager::make(
            // Fallback ba default e config yeki-e — `log` dige vojud nadare.
            (string) config('sms.driver', 'kavenegar'),
            $app,
        ));

        $this->app->singleton(OtpThrottle::class, static fn ($app): OtpThrottle => new OtpThrottle(
            limiter: $app->make(RateLimiter::class),
            sendPerPhone: (int) config('otp.limits.send_per_phone', 3),
            sendPerIp: (int) config('otp.limits.send_per_ip', 10),
            verifyPerPhone: (int) config('otp.limits.verify_per_phone', 5),
            verifyPerIp: (int) config('otp.limits.verify_per_ip', 20),
            decaySeconds: (int) config('otp.limits.decay_seconds', 3_600),
        ));

        $this->app->singleton(SendOtpAction::class, static fn ($app): SendOtpAction => new SendOtpAction(
            store: $app->make(OtpStorePort::class),
            otp: $app->make(OtpCode::class),
            sms: $app->make(SmsPort::class),
            throttle: $app->make(OtpThrottle::class),
            ttlSeconds: (int) config('otp.ttl_seconds', 120),
            cooldownSeconds: (int) config('otp.resend_cooldown_seconds', 60),
            digits: (int) config('otp.digits', 6),
            bypass: (bool) config('otp.bypass', false),
            logger: $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(VerifyOtpAction::class, static fn ($app): VerifyOtpAction => new VerifyOtpAction(
            store: $app->make(OtpStorePort::class),
            otp: $app->make(OtpCode::class),
            throttle: $app->make(OtpThrottle::class),
            maxAttempts: (int) config('otp.max_attempts', 3),
        ));

        // Kelid e HMAC az APP_KEY — `OtpCode` khodesh kelid e khali ro rad
        // mikone، pas yek APP_KEY e set-nashode hamin ja miterke، na saket.
        $this->app->singleton(OtpCode::class, static fn (): OtpCode => new OtpCode(
            (string) config('app.key'),
        ));
    }
}
