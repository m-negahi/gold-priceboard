<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Sms;

use App\Modules\Auth\Domain\Exceptions\SmsException;
use App\Modules\Auth\Domain\Ports\SmsPort;
use PHPUnit\Framework\Assert;

/**
 * Driver e FAKE — hich IO؛ ersal ha tu hafeze sabt mishan.
 *
 * ── Chera tu app ast va na `tests/Support/Fakes` ─────────────────────
 * Khaste ye sahebe projeh: `SMS_DRIVER=fake` yek gozine ye VAGHEI ye env
 * ast (demo/stage bedun e SMS). Helper haye assert faghat tu test seda
 * mishan.
 *
 * ⚠️ Bayad SINGLETON bind beshe — vagarna instance-i ke action behesh
 * ferestade ba instance-i ke test assert mikone yeki nist va har assert
 * e'lam mikone «hichi ferestade nashode».
 */
final class FakeSms implements SmsPort
{
    /** @var array<int, array{phone: string, code: string}> */
    private array $sent = [];

    private ?string $failWith = null;

    public function sendOtp(string $phone, string $code): void
    {
        if ($this->failWith !== null) {
            throw SmsException::apiStatus('fake', 0, $this->failWith);
        }

        $this->sent[] = ['phone' => $phone, 'code' => $code];
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
