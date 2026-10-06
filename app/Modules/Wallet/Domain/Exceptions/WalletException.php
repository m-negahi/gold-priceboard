<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Domain\Exceptions;

use RuntimeException;

/**
 * Khata haye kif e pul — payam ha baraye admin/log، na API e omumi.
 *
 * `code` e exception noe e khata ro moshakhas mikone (const haye paeen) ta
 * laye ye UI (P6-06) payam e Farsi e monaseb ro entekhab kone — na parse
 * kardan e matn e payam.
 */
final class WalletException extends RuntimeException
{
    public const INSUFFICIENT = 1;

    public const BLOCKED = 2;

    public const NON_POSITIVE = 3;

    public const BLANK_REFERENCE = 4;

    public const REFERENCE_REUSED = 5;

    public static function insufficientBalance(int $balance, int $amount): self
    {
        return new self("mande kafi nist: {$balance} < {$amount}.", self::INSUFFICIENT);
    }

    public static function blocked(int $walletId): self
    {
        return new self("kif e pul e {$walletId} masdud ast.", self::BLOCKED);
    }

    public static function nonPositiveAmount(int $amount): self
    {
        return new self("mablagh bayad mosbat bashe: {$amount}.", self::NON_POSITIVE);
    }

    public static function blankReference(): self
    {
        return new self('reference e tarakonesh nemitune khali bashe (idempotency).', self::BLANK_REFERENCE);
    }

    /**
     * Reference (kelid e idempotency) ghablan baraye yek darkhast e DIGAR
     * (jahat/mablagh/dalil e motefavet) masraf shode — radif e ghabli
     * javab e in darkhast NIST va nabayad «movaffagh» gozaresh beshe.
     */
    public static function referenceReused(string $reference): self
    {
        return new self("reference e {$reference} ghablan ba darkhast e digari sabt shode.", self::REFERENCE_REUSED);
    }
}
