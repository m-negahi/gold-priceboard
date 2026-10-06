<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PostWalletJournalAction
{
    public function execute(WalletTransaction $transaction): void
    {
        $accountCode = match ($transaction->reason) {
            'topup' => '1100',
            'plan_purchase' => '4100',
            'api_call', 'refund' => '4200',
            'admin_adjust', 'adjustment', 'admin_reversal' => '3900',
            default => throw new LogicException('reason e mali e na-shenakhte: '.$transaction->reason),
        };
        $liability = (int) DB::table('finance_accounts')->where('code', '2100')->value('id');
        $opposite = (int) DB::table('finance_accounts')->where('code', $accountCode)->value('id');
        if ($liability === 0 || $opposite === 0) {
            throw new LogicException('hesab haye daftar kol peyda nashodand');
        }

        $reverses = null;
        if ($transaction->reason === 'admin_reversal') {
            $reverses = DB::table('finance_journals')
                ->where('source_key', 'wallet:'.$transaction->reference)
                ->value('id');
        }

        $journalId = DB::table('finance_journals')->insertGetId([
            'source_key' => 'wallet:'.$transaction->id,
            'memo' => $transaction->reason.' · '.$transaction->reference,
            'actor_id' => $transaction->meta['admin_id'] ?? null,
            'reverses_journal_id' => $reverses,
            'occurred_at' => $transaction->created_at,
            'created_at' => $transaction->created_at,
        ]);

        $creditWallet = $transaction->direction === 'credit';
        $amount = $transaction->amount_minor;
        $walletId = $transaction->wallet_id;
        $userId = (int) DB::table('wallets')->where('id', $walletId)->value('owner_id');

        DB::table('finance_journal_lines')->insert([
            [
                'finance_journal_id' => $journalId, 'finance_account_id' => $liability,
                'wallet_id' => $walletId, 'user_id' => $userId,
                'debit_minor' => $creditWallet ? 0 : $amount, 'credit_minor' => $creditWallet ? $amount : 0,
            ],
            [
                'finance_journal_id' => $journalId, 'finance_account_id' => $opposite,
                'wallet_id' => $walletId, 'user_id' => $userId,
                'debit_minor' => $creditWallet ? $amount : 0, 'credit_minor' => $creditWallet ? 0 : $amount,
            ],
        ]);
    }
}
