<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Auth\Infrastructure\Sms\KavenegarSms;
use App\Modules\Auth\Infrastructure\Sms\SmsManager;
use Illuminate\Support\Facades\Http;
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

    // ── Log az tarigh e khod e fake ─────────────────────────────────

    /** Dev az hamin log login mikone — karkard e `LogSms` e hazf-shode. */
    public function test_fake_also_writes_the_code_to_the_log(): void
    {
        Log::shouldReceive('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['code'] === '042531'
                && $context['phone'] === '+989121234567',
        );

        (new FakeSms(Log::getFacadeRoot()))->sendOtp('+989121234567', '042531');
    }

    public function test_fake_without_a_logger_still_works(): void
    {
        // Test haye vahed logger nemidan — nabayad beterke.
        $fake = new FakeSms;
        $fake->sendOtp('+989121234567', '042531');

        $fake->assertSentTo('+989121234567');
    }

    /** Zire Octane e dev singleton RUZHA zende-st — hafeze cap dare. */
    public function test_fake_memory_is_capped(): void
    {
        $fake = new FakeSms;

        for ($i = 0; $i < 150; $i++) {
            $fake->sendOtp('+989121234567', str_pad((string) $i, 6, '0', STR_PAD_LEFT));
        }

        self::assertSame(100, $fake->sentCount());
        // Jadid tarin ha mimunan، na ghadimi tarin ha.
        self::assertSame('000149', $fake->lastCodeFor('+989121234567'));
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

    /**
     * 🔴 Negahban e default e config: `sms.php` yek bar default esh `log`
     * bud — driver-i ke HAZF shode bud. Natije: har deploy-i ke `SMS_DRIVER`
     * nadasht، avvalin OTP ba `unknownDriver` miterkid. Default bayad
     * hamishe ozv e `SmsManager::drivers()` bashe.
     */
    public function test_the_config_default_driver_is_a_known_driver(): void
    {
        // Env e OS (container `.env` ro export mikone) mitune default ro
        // MASK kone va test ro bi-ma'ni — movaghatan pak mishe ta vaghe'an
        // DEFAULT e file khunde beshe.
        $backup = [getenv('SMS_DRIVER'), $_ENV['SMS_DRIVER'] ?? null, $_SERVER['SMS_DRIVER'] ?? null];
        putenv('SMS_DRIVER');
        unset($_ENV['SMS_DRIVER'], $_SERVER['SMS_DRIVER']);

        try {
            $config = require __DIR__.'/../../../app/Modules/Auth/Config/sms.php';

            self::assertContains(
                $config['driver'],
                SmsManager::drivers(),
                'default e `sms.driver` bayad yek driver e sabt-shode bashe.',
            );
        } finally {
            if ($backup[0] !== false) {
                putenv('SMS_DRIVER='.$backup[0]);
            }
            if ($backup[1] !== null) {
                $_ENV['SMS_DRIVER'] = $backup[1];
            }
            if ($backup[2] !== null) {
                $_SERVER['SMS_DRIVER'] = $backup[2];
            }
        }
    }

    public function test_ticket_status_uses_separate_template_without_sending_real_sms(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.kavenegar.com/v1/test-key/verify/lookup.json' => Http::response(['return' => ['status' => 200]])]);
        (new KavenegarSms(['api_key' => 'test-key', 'template' => 'otp', 'ticket_template' => 'ticket-status']))->sendTicketStatus('+989121234567', 42, 'approved');
        Http::assertSent(static fn ($request): bool => $request['receptor'] === '09121234567' && $request['token'] === '42' && $request['token2'] === 'approved' && $request['template'] === 'ticket-status');
        Http::assertSentCount(1);
    }
}
