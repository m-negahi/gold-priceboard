<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Sms;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;

/**
 * Driver e FAKE — hich IO ye SMS؛ ersal ha tu hafeze sabt mishan VA tu log
 * chap mishan.
 *
 * ── Chera tu app ast va na `tests/Support/Fakes` ─────────────────────
 * Khaste ye sahebe projeh: `SMS_DRIVER=fake` yek gozine ye VAGHEI ye env
 * ast (dev/demo/stage bedun e SMS). Helper haye assert faghat tu test seda
 * mishan.
 *
 * ── Chera LOG ham mizane (va chera `LogSms` e joda hazf shod) ────────
 * Dev baraye login be CODE niaz dare. Ghablan yek driver e `LogSms` e joda
 * dashtim ke FAGHAT log mizad — vali «fake-i ke log ham bezane» hamun kar
 * ro mikone va yek driver va yek rah e config kamtar mishe. (Tashkhis e
 * khod e sahebe projeh، 2026-10-05.)
 * In tanha ja-i tu KOL e system ast ke code e OTP e plaintext sabt mishe —
 * amdi، va faghat vaghti `SMS_DRIVER=fake` ast.
 *
 * ⚠️ Bayad SINGLETON bind beshe — vagarna instance-i ke action behesh
 * ferestade ba instance-i ke test assert mikone yeki nist va har assert
 * e'lam mikone «hichi ferestade nashode».
 */
final class FakeSms implements SmsPort
{
    /**
     * Saghf e hafeze. Zire Octane e dev in singleton RUZHA zende mimune —
     * bedun e cap، har OTP yek entry e abadi mishe (nasht e aram).
     */
    private const MAX_KEPT = 100;

    /** @var array<int, array{phone: string, code: string}> */
    private array $sent = [];

    private ?string $failWith = null;

    public function __construct(private readonly ?LoggerInterface $logger = null) {}

    public function sendOtp(string $phone, string $code): void
    {
        if ($this->failWith !== null) {
            throw SmsException::apiStatus('fake', 0, $this->failWith);
        }

        $this->sent[] = ['phone' => $phone, 'code' => $code];

        if (count($this->sent) > self::MAX_KEPT) {
            array_shift($this->sent);
        }

        // Dev az hamin log login mikone.
        $this->logger?->info('sms.otp (driver e fake — SMS e vaghei ferestade NASHOD)', [
            'phone' => $phone,
            'code' => $code,
        ]);
    }

    // ── Kontrol (test) ──────────────────────────────────────────────

    public function failWith(string $message = 'ersal e sakhtegi shekast'): self
    {
        $this->failWith = $message;

        return $this;
    }

    public function repair(): self
    {
        $this->failWith = null;

        return $this;
    }

    // ── Assert ha ───────────────────────────────────────────────────

    public function assertSentTo(string $phone): void
    {
        Assert::assertNotNull(
            $this->lastCodeFor($phone),
            "hich SMS-i be {$phone} ferestade nashode.",
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'entezar miraft hich SMS-i ferestade nashe.');
    }

    public function lastCodeFor(string $phone): ?string
    {
        foreach (array_reverse($this->sent) as $message) {
            if ($message['phone'] === $phone) {
                return $message['code'];
            }
        }

        return null;
    }

    public function sentCount(): int
    {
        return count($this->sent);
    }
}
