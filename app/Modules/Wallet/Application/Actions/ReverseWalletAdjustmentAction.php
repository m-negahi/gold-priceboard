<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Actions;

use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReverseWalletAdjustmentAction
{
    public function __construct(
        private WalletAction $wallets,
        private ChargeApiCallAction $charger,
    ) {}

    public function reverse(int $transactionId, int $adminId, string $note): WalletTransaction
    {
        return DB::transaction(fn (): WalletTransaction => $this->reverseInsideTransaction($transactionId, $adminId, $note));
    }

    public function correct(int $transactionId, int $adminId, string $direction, int $amountMinor, string $note): WalletTransaction
    {
        if (! in_array($direction, ['credit', 'debit'], true) || $amountMinor <= 0) {
            throw new InvalidArgumentException('eslah e mali na-motabar ast');
        }

        return DB::transaction(function () use ($transactionId, $adminId, $direction, $amountMinor, $note): WalletTransaction {
            $reference = 'correction:'.$transactionId;
            $original = WalletTransaction::query()->findOrFail($transactionId);
            $existing = WalletTransaction::query()
                ->where('wallet_id', $original->wallet_id)->where('reason', 'admin_adjust')
                ->where('reference', $reference)->first();
            if ($existing !== null) {
                if ($existing->direction !== $direction || $existing->amount_minor !== $amountMinor || ($existing->meta['note'] ?? null) !== trim($note)) {
                    throw WalletException::referenceReused($reference);
                }

                return $existing;
            }

            $this->reverseInsideTransaction($transactionId, $adminId, $note);
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->findOrFail($original->wallet_id);
            $meta = ['admin_id' => $adminId, 'note' => trim($note), 'corrects_transaction_id' => $transactionId];
            $corrected = $direction === 'credit'
                ? $this->wallets->credit($wallet, $amountMinor, 'admin_adjust', $reference, $meta)
                : $this->wallets->debit($wallet, $amountMinor, 'admin_adjust', $reference, $meta);
            $this->charger->invalidateMirror($wallet);

            return $corrected;
        });
    }

    private function reverseInsideTransaction(int $transactionId, int $adminId, string $note): WalletTransaction
    {
        /** @var WalletTransaction $original */
        $original = WalletTransaction::query()->findOrFail($transactionId);
        if ($original->reason !== 'admin_adjust' || $original->reference === 'correction:'.$transactionId) {
            throw new InvalidArgumentException('faghat sanad e eslah e dasti ghabel e ebtal ast');
        }

        /** @var Wallet $wallet */
        $wallet = Wallet::query()->findOrFail($original->wallet_id);
        $meta = ['admin_id' => $adminId, 'note' => trim($note), 'original_transaction_id' => $original->id];
        $reversed = $original->direction === 'credit'
            ? $this->wallets->debit($wallet, $original->amount_minor, 'admin_reversal', (string) $original->id, $meta)
            : $this->wallets->credit($wallet, $original->amount_minor, 'admin_reversal', (string) $original->id, $meta);

        if ($reversed->direction === $original->direction || $reversed->amount_minor !== $original->amount_minor) {
            throw WalletException::referenceReused((string) $original->id);
        }

        $this->charger->invalidateMirror($wallet);

        return $reversed;
    }
}
