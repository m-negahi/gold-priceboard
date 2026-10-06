<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use InvalidArgumentException;

/**
 * Sharj/kasr e DASTI e kif e pul tavasot e admin (P6-06).
 *
 * Faghat yek pusht-e `WalletAction` ast — lock، append-only va idempotency
 * hamunja-and. Inja se chiz ezafe mishe:
 *
 * - `reason = admin_adjust` (ledger az sharj/masraf e API joda mimune).
 * - `meta.admin_id` + `meta.note`: KI va CHERA — audit e pul bedun e in do
 *   bi-ma'na-st.
 * - `reference = admin:{key}`: `key` yek UUID e form ast ke ba HAR render
 *   e safhe taze sakhte mishe؛ ersal e dobare ye HAMUN form (double-click،
 *   refresh e POST) hamun radif e avval ro bar migardune، na kasr/sharj e
 *   dovom.
 */
final readonly class AdjustWalletAction
{
    public const REASON = 'admin_adjust';

    public function __construct(private WalletAction $wallets) {}

    /** @throws WalletException mande kam / kif e masdud */
    public function execute(int $ownerId, string $direction, int $amountMinor, string $note, int $adminId, string $idempotencyKey): WalletTransaction
    {
        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw new InvalidArgumentException("jahat e na-motabar: {$direction}");
        }

        $wallet = $this->wallets->forOwner($ownerId);
        $reference = 'admin:'.$idempotencyKey;
        $meta = ['admin_id' => $adminId, 'note' => trim($note)];

        return $direction === 'credit'
            ? $this->wallets->credit($wallet, $amountMinor, self::REASON, $reference, $meta)
            : $this->wallets->debit($wallet, $amountMinor, self::REASON, $reference, $meta);
    }
}
