<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Queries;

use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Carbon\CarbonImmutable;

/**
 * Khundan e kif e pul (P4-02) — bedun e side effect.
 *
 * `balanceFromLedger()` manba' e HAGHIGHAT ast (sum e ledger)؛
 * `balance_minor` e wallet faghat cache. `verifyBalance()` in do ro
 * moghayese mikone — ekhtelaf ya'ni bug، va bayad GHABL az customer
 * kashf beshe.
 */
final readonly class WalletQuery
{
    public function findForOwner(int $ownerId, string $currency = 'IRR'): ?Wallet
    {
        return Wallet::query()
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->first();
    }

    /** Mande az LEDGER — na az cache. */
    public function balanceFromLedger(Wallet $wallet): int
    {
        $sums = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->selectRaw("coalesce(sum(case when direction = 'credit' then amount_minor else -amount_minor end), 0) as balance")
            ->value('balance');

        return (int) $sums;
    }

    /** `true` ya'ni cache va ledger yeki-and. */
    public function verifyBalance(Wallet $wallet): bool
    {
        return $this->balanceFromLedger($wallet) === (int) $wallet->refresh()->balance_minor;
    }

    /**
     * Safhe-i az ledger — jadid tarin aval.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ledger(Wallet $wallet, int $limit = 50, int $offset = 0): array
    {
        return WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->skip($offset)
            ->take(min(200, max(1, $limit)))
            ->get()
            ->map(static fn (WalletTransaction $tx): array => [
                'id' => $tx->id,
                'direction' => $tx->direction,
                'amountMinor' => $tx->amount_minor,
                'balanceAfterMinor' => $tx->balance_after_minor,
                'reason' => $tx->reason,
                'reference' => $tx->reference,
                // Adjust e dasti (P6-06): dalil + kodum admin.
                'note' => is_array($tx->meta) && isset($tx->meta['note']) ? (string) $tx->meta['note'] : null,
                'adminId' => is_array($tx->meta) && isset($tx->meta['admin_id']) ? (int) $tx->meta['admin_id'] : null,
                'createdAt' => $tx->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Hazine ye KHALES e API dar yek baze (P6-08): kasr haye `api_call` −
     * `refund` haye hamun client (304/khata). Kelid = `client_id` e omumi
     * (az `meta`) — ledger PK e `api_clients` ro nemidune.
     *
     * Baze `[from, to)` be UTC (hamun shekli ke `created_at` zakhire mishe).
     *
     * @return array<string, int> client_id => net cost (minor)
     */
    public function netApiCostByClient(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return WalletTransaction::query()
            ->whereIn('reason', ['api_call', 'refund'])
            ->whereNotNull('meta->client_id')
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->utc())
            ->groupBy('meta->client_id')
            ->select('meta->client_id as client')
            ->selectRaw("sum(case when reason = 'api_call' then amount_minor else -amount_minor end) as net")
            ->toBase()
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(string) $row->client => (int) $row->net])
            ->all();
    }
}
