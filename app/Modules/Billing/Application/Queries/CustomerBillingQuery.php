<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Queries;

use App\Modules\Api\Application\Queries\ApiUsageQuery;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Api\Infrastructure\Persistence\Models\SiteSubscription;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingInvoice;
use App\Modules\Common\Helpers\Money;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Carbon\CarbonImmutable;

final readonly class CustomerBillingQuery
{
    public function __construct(
        private WalletQuery $wallets,
        private ApiUsageQuery $usage,
        private TicketQuery $tickets,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(int $userId): array
    {
        $wallet = $this->wallets->findForOwner($userId);
        $usage = $this->usage->dailyForUser($userId, 30);

        return [
            'walletBalance' => Money::rialDisplay((int) ($wallet?->balance_minor ?? 0)),
            'siteCount' => ApiClient::query()->where('user_id', $userId)->count(),
            'activeSubscriptionCount' => SiteSubscription::query()->where('user_id', $userId)
                ->where('starts_at', '<=', CarbonImmutable::now())->where('ends_at', '>', CarbonImmutable::now())->count(),
            'totalCalls' => $usage['totalCalls'],
            'totalCost' => Money::rialDisplay($usage['totalCostMinor']),
            'tickets' => array_slice($this->tickets->listForOwner($userId), 0, 5),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function sites(int $userId): array
    {
        $subscriptions = SiteSubscription::query()->where('user_id', $userId)
            ->where('starts_at', '<=', CarbonImmutable::now())
            ->where('ends_at', '>', CarbonImmutable::now())
            ->orderByDesc('starts_at')->get()->keyBy('api_client_id');

        return ApiClient::query()->where('user_id', $userId)->orderByDesc('id')->get()
            ->map(static function (ApiClient $client) use ($subscriptions): array {
                $subscription = $subscriptions->get($client->id);

                return [
                    'id' => $client->id, 'name' => $client->name,
                    'websiteUrl' => $client->website_url, 'clientId' => $client->client_id,
                    'status' => $client->status, 'isActive' => $client->is_active,
                    'subscriptionEndsAt' => $subscription?->ends_at?->format('Y-m-d'),
                    'callsRemaining' => $subscription === null ? null : max(0, $subscription->calls_included - $subscription->calls_used),
                ];
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function plans(): array
    {
        return ApiPlan::query()->where('is_active', true)->orderBy('sort')->get()
            ->map(static fn (ApiPlan $plan): array => [
                'id' => $plan->id, 'name' => $plan->name,
                'durationDays' => $plan->duration_days,
                'callsIncluded' => $plan->calls_included,
                'callsPerDay' => $plan->calls_per_day,
                'priceRial' => Money::rialDisplay($plan->price_minor),
                'overageRial' => Money::rialDisplay($plan->cost_per_call_minor),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function subscriptions(int $userId): array
    {
        return SiteSubscription::query()->where('user_id', $userId)
            ->with('plan')->orderByDesc('id')->get()
            ->map(static fn (SiteSubscription $subscription): array => [
                'id' => $subscription->id,
                'siteId' => $subscription->api_client_id,
                'planName' => $subscription->plan?->name ?? '—',
                'startsAt' => $subscription->starts_at?->format('Y-m-d'),
                'endsAt' => $subscription->ends_at?->format('Y-m-d'),
                'callsUsed' => $subscription->calls_used,
                'callsIncluded' => $subscription->calls_included,
                'priceRial' => Money::rialDisplay($subscription->price_minor),
            ])->all();
    }

    /** @return array<string, mixed> */
    public function wallet(int $userId): array
    {
        $wallet = $this->wallets->findForOwner($userId);
        $ledger = $wallet === null ? [] : $this->wallets->ledger($wallet, 50);

        return [
            'balanceRial' => Money::rialDisplay((int) ($wallet?->balance_minor ?? 0)),
            'paymentConfigured' => match ((string) config('billing.gateway', 'vandar')) {
                'zibal' => (string) config('billing.zibal.merchant', '') !== '',
                default => (string) config('billing.vandar.api_key', '') !== '',
            },
            'ledger' => array_map(static fn (array $row): array => [
                'id' => $row['id'], 'direction' => $row['direction'],
                'amountRial' => Money::rialDisplay($row['amountMinor']),
                'balanceRial' => Money::rialDisplay($row['balanceAfterMinor']),
                'reason' => $row['reason'], 'reference' => $row['reference'],
                'at' => $row['createdAt'],
            ], $ledger),
            'invoices' => BillingInvoice::query()->where('user_id', $userId)->orderByDesc('id')
                ->get(['id', 'number', 'amount_minor', 'issued_at'])
                ->map(static fn (BillingInvoice $invoice): array => [
                    'id' => $invoice->id, 'number' => $invoice->number,
                    'amountRial' => Money::rialDisplay($invoice->amount_minor),
                    'issuedAt' => $invoice->issued_at?->format('Y-m-d'),
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function usage(int $userId): array
    {
        $data = $this->usage->dailyForUser($userId, 30);

        return [
            'totalCalls' => $data['totalCalls'],
            'totalCostRial' => Money::rialDisplay($data['totalCostMinor']),
            'days' => $data['days'],
        ];
    }

    /** @return array<string, mixed> */
    public function invoice(int $userId, int $invoiceId): array
    {
        /** @var BillingInvoice $invoice */
        $invoice = BillingInvoice::query()->where('user_id', $userId)->findOrFail($invoiceId);

        return [
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'amountRial' => Money::rialDisplay($invoice->amount_minor),
            'buyer' => $invoice->buyer_snapshot,
            'lines' => array_map(static fn (array $line): array => [
                ...$line,
                'amountRial' => Money::rialDisplay((int) $line['amount_rial']),
            ], $invoice->lines_snapshot),
            'issuedAt' => $invoice->issued_at?->format('Y-m-d H:i'),
        ];
    }
}
