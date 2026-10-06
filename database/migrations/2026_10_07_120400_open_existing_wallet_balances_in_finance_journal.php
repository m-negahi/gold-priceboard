<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $liability = (int) DB::table('finance_accounts')->where('code', '2100')->value('id');
        $opening = (int) DB::table('finance_accounts')->where('code', '3000')->value('id');

        DB::transaction(static function () use ($liability, $opening): void {
            DB::table('wallets')->orderBy('id')->chunkById(100, static function ($wallets) use ($liability, $opening): void {
                foreach ($wallets as $wallet) {
                    $balance = (int) DB::table('wallet_transactions')
                        ->where('wallet_id', $wallet->id)
                        ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END), 0) AS balance")
                        ->value('balance');

                    if ($balance !== (int) $wallet->balance_minor || $balance < 0) {
                        throw new RuntimeException("wallet {$wallet->id} balance mismatch; opening journal stopped");
                    }

                    if ($balance === 0) {
                        continue;
                    }

                    $journalId = DB::table('finance_journals')->insertGetId([
                        'source_key' => 'opening:wallet:'.$wallet->id,
                        'memo' => 'مانده افتتاحیه کیف پول',
                        'occurred_at' => now(),
                        'created_at' => now(),
                    ]);
                    DB::table('finance_journal_lines')->insert([
                        ['finance_journal_id' => $journalId, 'finance_account_id' => $opening, 'wallet_id' => $wallet->id, 'user_id' => $wallet->owner_id, 'debit_minor' => $balance, 'credit_minor' => 0],
                        ['finance_journal_id' => $journalId, 'finance_account_id' => $liability, 'wallet_id' => $wallet->id, 'user_id' => $wallet->owner_id, 'debit_minor' => 0, 'credit_minor' => $balance],
                    ]);
                }
            });
        });
    }

    public function down(): void
    {
        // Sanad e mali append-only ast; bazgasht faghat ba sanad e mokhalef.
    }
};
