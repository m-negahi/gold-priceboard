<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tanha masir e neveshtan e kif e pul (P4-02).
 *
 * ── Se ghanun e pul ─────────────────────────────────────────────────
 * 1. Har credit/debit tu YEK transaction ba `lockForUpdate` e radif e
 *    wallet: do request e hamzaman (Octane!) nemitunan yek mande ro do
 *    bar kharj konan — dovomi posht e lock mimune va mande ye JADID ro
 *    mibine.
 * 2. 🔴 IDEMPOTENT ba `unique(wallet_id, reason, reference)`: retry e
 *    hamun request (client timeout khord va dobare ferestad) tarakonesh
 *    e DOVOM nemisaze — hamun radif e avval bar migarde. In tanha
 *    chizi-e ke «kasr e dobare» ro mohal mikone، na hosn e niyat e
 *    client.
 * 3. Ledger APPEND-ONLY ast؛ `balance_minor` e wallet faghat cache e
 *    hamun ledger ast va har do tu HAMUN transaction be-rooz mishan.
 */
final readonly class WalletAction
{
    public function forOwner(int $ownerId, string $currency = 'IRR'): Wallet
    {
        return Wallet::query()->firstOrCreate(['owner_id' => $ownerId, 'currency' => $currency]);
    }

    /** @param array<string, mixed> $meta */
    public function credit(Wallet $wallet, int $amountMinor, string $reason, string $reference, array $meta = []): WalletTransaction
    {
        return $this->apply($wallet, 'credit', $amountMinor, $reason, $reference, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     *
     * @throws WalletException mande kam ya kif e masdud
     */
    public function debit(Wallet $wallet, int $amountMinor, string $reason, string $reference, array $meta = []): WalletTransaction
    {
        return $this->apply($wallet, 'debit', $amountMinor, $reason, $reference, $meta);
    }

    /** @param array<string, mixed> $meta */
    private function apply(Wallet $wallet, string $direction, int $amountMinor, string $reason, string $reference, array $meta): WalletTransaction
    {
        if ($amountMinor <= 0) {
            throw WalletException::nonPositiveAmount($amountMinor);
        }

        if (trim($reference) === '') {
            throw WalletException::blankReference();
        }

        try {
            return DB::transaction(function () use ($wallet, $direction, $amountMinor, $reason, $reference, $meta): WalletTransaction {
                /** @var Wallet $locked */
                $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

                if ($locked->is_blocked) {
                    throw WalletException::blocked($locked->id);
                }

                if ($direction === 'debit' && $locked->balance_minor < $amountMinor) {
                    throw WalletException::insufficientBalance($locked->balance_minor, $amountMinor);
                }

                $after = $direction === 'credit'
                    ? $locked->balance_minor + $amountMinor
                    : $locked->balance_minor - $amountMinor;

                $transaction = WalletTransaction::query()->create([
                    'wallet_id' => $locked->id,
                    'direction' => $direction,
                    'amount_minor' => $amountMinor,
                    'balance_after_minor' => $after,
                    'reason' => $reason,
                    'reference' => $reference,
                    'meta' => $meta === [] ? null : $meta,
                    'created_at' => CarbonImmutable::now(),
                ]);

                $locked->forceFill(['balance_minor' => $after])->save();

                return $transaction;
            });
        } catch (QueryException $e) {
            // 🔴 Retry e hamun request: radif e ghabli bar migarde، hich
            // taghiri tu mande. Faghat unique violation e hamin se-tayi —
            // baghi ye khata ha bala miran.
            $existing = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('reason', $reason)
                ->where('reference', $reference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * Refund e yek debit — radif e JADID، na pak kardan e ghabli.
     *
     * Reference e khod e refund `refund:{reference}` mishe ta refund e
     * dobare ye hamun request ham idempotent bemune.
     *
     * @param  array<string, mixed>  $meta
     */
    public function refund(Wallet $wallet, int $amountMinor, string $originalReference, array $meta = []): WalletTransaction
    {
        return $this->credit($wallet, $amountMinor, 'refund', 'refund:'.$originalReference, $meta);
    }

    public function block(Wallet $wallet): Wallet
    {
        $wallet->forceFill(['is_blocked' => true])->save();

        return $wallet;
    }

    public function unblock(Wallet $wallet): Wallet
    {
        $wallet->forceFill(['is_blocked' => false])->save();

        return $wallet;
    }
}
