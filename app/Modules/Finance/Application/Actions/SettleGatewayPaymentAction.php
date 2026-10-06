<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Actions;

use App\Modules\Billing\Infrastructure\Persistence\Models\WalletTopupPayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class SettleGatewayPaymentAction
{
    public function execute(int $paymentId, int $adminId, string $bankReference): int
    {
        $bankReference = trim($bankReference);
        if ($bankReference === '' || mb_strlen($bankReference) > 100) {
            throw new InvalidArgumentException('shenase ye variz e banki na-motabar ast');
        }

        return DB::transaction(static function () use ($paymentId, $adminId, $bankReference): int {
            /** @var WalletTopupPayment $payment */
            $payment = WalletTopupPayment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'verified') {
                throw new InvalidArgumentException('pardakht hanuz taeed nashode ast');
            }
            $source = 'settlement:payment:'.$payment->id;
            $memo = 'تسویه زیبال · '.$bankReference;
            $existing = DB::table('finance_journals')->where('source_key', $source)->first();
            if ($existing !== null) {
                if ($existing->memo !== $memo) {
                    throw new InvalidArgumentException('in pardakht ba shenase ye digar tasviye shode ast');
                }

                return (int) $existing->id;
            }

            $bank = (int) DB::table('finance_accounts')->where('code', '1000')->value('id');
            $clearing = (int) DB::table('finance_accounts')->where('code', '1100')->value('id');
            if ($bank === 0 || $clearing === 0) {
                throw new LogicException('hesab haye tasviye mojood nistand');
            }
            $journalId = DB::table('finance_journals')->insertGetId([
                'source_key' => $source, 'memo' => $memo, 'actor_id' => $adminId,
                'occurred_at' => now(), 'created_at' => now(),
            ]);
            DB::table('finance_journal_lines')->insert([
                [
                    'finance_journal_id' => $journalId, 'finance_account_id' => $bank,
                    'user_id' => $payment->user_id, 'debit_minor' => $payment->amount_minor, 'credit_minor' => 0,
                ],
                [
                    'finance_journal_id' => $journalId, 'finance_account_id' => $clearing,
                    'user_id' => $payment->user_id, 'debit_minor' => 0, 'credit_minor' => $payment->amount_minor,
                ],
            ]);

            return $journalId;
        });
    }
}
