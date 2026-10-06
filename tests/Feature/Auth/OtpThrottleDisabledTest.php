<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Support\OtpThrottle;
use Illuminate\Cache\RateLimiter;
use Tests\TestCase;

/**
 * `enabled = false` (driver e fake/dev — khaste ye sahebe proje): rate
 * limit KAMELAN khamoosh، login e dev throttle nemishe.
 */
final class OtpThrottleDisabledTest extends TestCase
{
    public function test_disabled_throttle_never_blocks(): void
    {
        $throttle = new OtpThrottle(
            $this->app->make(RateLimiter::class),
            sendPerPhone: 1, sendPerIp: 1, verifyPerPhone: 1, verifyPerIp: 1,
            enabled: false,
        );

        // Kheyli bishtar az saghf hit mizanim — nabayad hichvaght block she.
        for ($i = 0; $i < 20; $i++) {
            $throttle->hitSend('+989121234567', '1.2.3.4');
            $throttle->hitVerify('+989121234567', '1.2.3.4');
        }

        self::assertNull($throttle->sendBlockedFor('+989121234567', '1.2.3.4'));
        self::assertNull($throttle->verifyBlockedFor('+989121234567', '1.2.3.4'));
    }

    public function test_enabled_throttle_still_blocks(): void
    {
        $throttle = new OtpThrottle(
            $this->app->make(RateLimiter::class),
            sendPerPhone: 2, sendPerIp: 10, verifyPerPhone: 5, verifyPerIp: 20,
            enabled: true,
        );

        $throttle->hitSend('+989121234567', '1.2.3.4');
        $throttle->hitSend('+989121234567', '1.2.3.4');

        self::assertNotNull($throttle->sendBlockedFor('+989121234567', '1.2.3.4'));
    }
}
