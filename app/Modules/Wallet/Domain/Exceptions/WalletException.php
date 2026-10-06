<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Domain\Exceptions;

use RuntimeException;

/** Khata haye kif e pul — payam ha baraye admin/log، na API e omumi. */
final class WalletException extends RuntimeException
{
    public static function insufficientBalance(int $balance, int $amount): self
    {
        return new self("mande kafi nist: {$balance} < {$amount}.");
    }

    public static function blocked(int $walletId): self
    {
        return new self("kif e pul e {$walletId} masdud ast.");
    }

    public static function nonPositiveAmount(int $amount): self
    {
        return new self("mablagh bayad mosbat bashe: {$amount}.");
    }

    public static function blankReference(): self
    {
        return new self('reference e tarakonesh nemitune khali bashe (idempotency).');
    }
}
