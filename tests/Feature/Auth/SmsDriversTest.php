<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Auth\Infrastructure\Sms\LogSms;
use App\Modules\Auth\Infrastructure\Sms\SmsManager;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class SmsDriversTest extends TestCase
{
    // ── FakeSms ─────────────────────────────────────────────────────

    public function test_fake_records_what_was_sent(): void
    {
        $fake = new FakeSms;

        $fake->sendOtp('+989121234567', '042531');
        $fake->sendOtp('+989121234567', '042532');

        $fake->assertSentTo('+989121234567');
        self::assertSame('042532', $fake->lastCodeFor('+989121234567'), 'akharin code bayad bargarde.');
        self::assertSame(2, $fake->sentCount());
        self::assertNull($fake->lastCodeFor('+989999999999'));
    }

    public function test_fake_can_simulate_a_failure(): void
    {
        $fake = (new FakeSms)->failWith('kharab e sakhtegi');

        try {
            $fake->sendOtp('+989121234567', '042531');
            self::fail('bayad SmsException miumad.');
        } catch (SmsException) {
            $fake->assertNothingSent();
        }

        $fake->repair();
        $fake->sendOtp('+989121234567', '042531');
        $fake->assertSentTo('+989121234567');
    }

    // ── LogSms ──────────────────────────────────────────────────────

    public function test_log_driver_writes_the_code_to_the_log(): void
    {
        Log::shouldReceive('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['code'] === '042531'
                && $context['phone'] === '+989121234567',
        );

        (new LogSms(Log::getFacadeRoot()))->sendOtp('+989121234567', '042531');
    }

    // ── SmsManager + bind ───────────────────────────────────────────

    public function test_an_unknown_driver_blows_up_at_build_time(): void
    {
        // `SMS_DRIVER=kavengar` e typo-dar nabayad ta avvalin login e vaghei
        // penhan bemune.
        $this->expectException(SmsException::class);

        SmsManager::make('kavengar', $this->app);
    }

    public function test_the_port_resolves_to_fake_in_tests(): void
    {
        self::assertInstanceOf(FakeSms::class, $this->app->make(SmsPort::class));
    }

    /**
     * 🔴 Port va `FakeSms` bayad HAMUN instance bashan — vagarna action be
     * yek instance mifereste va test ru instance ye dige assert mikone، va
     * har assert «hichi ferestade nashode» mide.
     */
    public function test_the_port_and_the_fake_are_the_same_singleton(): void
    {
        $port = $this->app->make(SmsPort::class);
        $fake = $this->app->make(FakeSms::class);

        self::assertSame($port, $fake);
        self::assertSame($port, $this->app->make(SmsPort::class), 'port bayad memoize beshe.');
    }

    public function test_every_configured_driver_can_be_built(): void
    {
        // Bind-i ke resolve nashe shekast e saket ast — har 3 driver sakhte
        // mishan (kavenegar bedun e ersal، faghat sakht).
        foreach (SmsManager::drivers() as $driver) {
            self::assertInstanceOf(SmsPort::class, SmsManager::make($driver, $this->app), $driver);
        }
    }
}
