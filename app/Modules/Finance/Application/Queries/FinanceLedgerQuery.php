<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries;

use App\Modules\Common\Helpers\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class FinanceLedgerQuery
{
    /** @param array<string, mixed> $filters */
    public function ledger(array $filters, int $page = 1): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->select('wallet_transactions.*', 'wallets.owner_id', 'users.name as owner_name', 'users.phone as owner_phone', 'reversals.id as reversal_id')
            ->orderByDesc('wallet_transactions.id')
            ->paginate(30, page: $page)->withQueryString()
            ->through(static fn (object $row): array => self::row($row));
    }

    /** @param array<string, mixed> $filters
     * @return array<string, int|string>
     */
    public function totals(array $filters): array
    {
        $row = $this->filtered($filters)
            ->selectRaw("COUNT(*) as count, COALESCE(SUM(CASE WHEN wallet_transactions.direction = 'credit' THEN wallet_transactions.amount_minor ELSE 0 END), 0) as credits, COALESCE(SUM(CASE WHEN wallet_transactions.direction = 'debit' THEN wallet_transactions.amount_minor ELSE 0 END), 0) as debits")
            ->first();

        return [
            'credits' => (int) $row->credits,
            'debits' => (int) $row->debits,
            'net' => (int) $row->credits - (int) $row->debits,
            'count' => (int) $row->count,
            'creditsRial' => Money::rialDisplay((int) $row->credits),
            'debitsRial' => Money::rialDisplay((int) $row->debits),
            'netRial' => Money::rialDisplay((int) $row->credits - (int) $row->debits),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function trialBalance(): array
    {
        return DB::table('finance_accounts as accounts')
            ->leftJoin('finance_journal_lines as lines', 'lines.finance_account_id', '=', 'accounts.id')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.normal_side')
            ->orderBy('accounts.code')
            ->select('accounts.code', 'accounts.name', 'accounts.normal_side')
            ->selectRaw('COALESCE(SUM(lines.debit_minor), 0) as debits, COALESCE(SUM(lines.credit_minor), 0) as credits')
            ->get()->map(static fn (object $row): array => [
                'code' => $row->code, 'name' => $row->name,
                'debits' => Money::rialDisplay((int) $row->debits),
                'credits' => Money::rialDisplay((int) $row->credits),
                'balance' => Money::rialDisplay(abs((int) $row->debits - (int) $row->credits)),
                'balanceSide' => (int) $row->debits >= (int) $row->credits ? 'بدهکار' : 'بستانکار',
            ])->all();
    }

    /** @param array<string, mixed> $filters */
    public function generalLedger(array $filters, int $page = 1): LengthAwarePaginator
    {
        $query = DB::table('finance_journal_lines as lines')
            ->join('finance_journals as journals', 'journals.id', '=', 'lines.finance_journal_id')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.finance_account_id')
            ->leftJoin('users', 'users.id', '=', 'lines.user_id');

        if (! empty($filters['account_code'])) {
            $query->where('accounts.code', $filters['account_code']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('lines.user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('journals.occurred_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('journals.occurred_at', '<=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $nested) use ($like, $search): void {
                $nested->where('journals.source_key', $like, $search)
                    ->orWhere('journals.memo', $like, $search)
                    ->orWhere('users.name', $like, $search)
                    ->orWhere('users.phone', $like, $search);
            });
        }

        return $query->orderByDesc('journals.id')->orderBy('lines.id')
            ->select('journals.id as journal_id', 'journals.source_key', 'journals.memo', 'journals.occurred_at', 'accounts.code', 'accounts.name as account_name', 'lines.user_id', 'users.name as user_name', 'lines.debit_minor', 'lines.credit_minor')
            ->paginate(30, ['*'], 'journal_page', $page)->withQueryString()
            ->through(static fn (object $row): array => [
                'id' => (int) $row->journal_id,
                'source' => $row->source_key,
                'memo' => $row->memo,
                'at' => (string) $row->occurred_at,
                'code' => $row->code,
                'account' => $row->account_name,
                'userId' => $row->user_id,
                'user' => $row->user_name,
                'debitRial' => Money::rialDisplay((int) $row->debit_minor),
                'creditRial' => Money::rialDisplay((int) $row->credit_minor),
            ]);
    }

    /** @return array<string, mixed>|null */
    public function transaction(int $id): ?array
    {
        $row = $this->filtered([])
            ->where('wallet_transactions.id', $id)
            ->select('wallet_transactions.*', 'wallets.owner_id', 'users.name as owner_name', 'users.phone as owner_phone', 'reversals.id as reversal_id')
            ->first();

        return $row === null ? null : self::row($row);
    }

    /** @return array<int, array<string, mixed>> */
    public function journalLines(int $transactionId): array
    {
        return DB::table('finance_journal_lines as lines')
            ->join('finance_journals as journals', 'journals.id', '=', 'lines.finance_journal_id')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.finance_account_id')
            ->where('journals.source_key', 'wallet:'.$transactionId)
            ->orderBy('lines.id')
            ->get(['accounts.code', 'accounts.name', 'lines.debit_minor', 'lines.credit_minor'])
            ->map(static fn (object $line): array => [
                'code' => $line->code, 'name' => $line->name,
                'debitRial' => Money::rialDisplay((int) $line->debit_minor),
                'creditRial' => Money::rialDisplay((int) $line->credit_minor),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function unsettledPayments(): array
    {
        return DB::table('wallet_topup_payments as payments')
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('finance_journals as settled', function ($join): void {
                $join->whereRaw("settled.source_key = 'settlement:payment:' || payments.id");
            })
            ->where('payments.status', 'verified')
            ->whereNull('settled.id')
            ->orderBy('payments.id')
            ->limit(100)
            ->get(['payments.id', 'payments.user_id', 'payments.amount_minor', 'payments.track_id', 'payments.gateway_reference', 'users.name', 'users.phone'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'userId' => (int) $row->user_id,
                'userName' => $row->name ?: $row->phone,
                'amountRial' => Money::rialDisplay((int) $row->amount_minor),
                'trackId' => $row->track_id,
                'gatewayReference' => $row->gateway_reference,
            ])->all();
    }

    /** @param array<string, mixed> $filters */
    public function exportRows(array $filters): iterable
    {
        return $this->filtered($filters)
            ->select('wallet_transactions.*', 'wallets.owner_id', 'users.name as owner_name', 'users.phone as owner_phone', 'reversals.id as reversal_id')
            ->orderBy('wallet_transactions.id')
            ->cursor();
    }

    /** @param array<string, mixed> $filters */
    private function filtered(array $filters): Builder
    {
        $query = DB::table('wallet_transactions')
            ->join('wallets', 'wallets.id', '=', 'wallet_transactions.wallet_id')
            ->join('users', 'users.id', '=', 'wallets.owner_id')
            ->leftJoin('wallet_transactions as reversals', function ($join): void {
                $join->on('reversals.wallet_id', '=', 'wallet_transactions.wallet_id')
                    ->where('reversals.reason', '=', 'admin_reversal')
                    ->whereRaw('reversals.reference = CAST(wallet_transactions.id AS VARCHAR)');
            });

        if (! empty($filters['user_id'])) {
            $query->where('wallets.owner_id', (int) $filters['user_id']);
        }
        if (! empty($filters['reason'])) {
            $query->where('wallet_transactions.reason', $filters['reason']);
        }
        if (! empty($filters['direction'])) {
            $query->where('wallet_transactions.direction', $filters['direction']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('wallet_transactions.created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('wallet_transactions.created_at', '<=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $nested) use ($like, $search): void {
                $nested->where('wallet_transactions.reference', $like, $search)
                    ->orWhere('users.name', $like, $search)
                    ->orWhere('users.phone', $like, $search);
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private static function row(object $row): array
    {
        $meta = is_string($row->meta) ? json_decode($row->meta, true) : $row->meta;

        return [
            'id' => (int) $row->id,
            'ownerId' => (int) $row->owner_id,
            'ownerName' => $row->owner_name ?: $row->owner_phone,
            'ownerPhone' => $row->owner_phone,
            'direction' => $row->direction,
            'amountRial' => Money::rialDisplay((int) $row->amount_minor),
            'balanceRial' => Money::rialDisplay((int) $row->balance_after_minor),
            'reason' => $row->reason, 'reference' => $row->reference,
            'note' => $meta['note'] ?? null,
            'adminId' => $meta['admin_id'] ?? null,
            'at' => (string) $row->created_at,
            'isReversed' => $row->reversal_id !== null,
            'reversalId' => $row->reversal_id,
        ];
    }
}
