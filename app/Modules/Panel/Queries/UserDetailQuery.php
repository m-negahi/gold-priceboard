<?php

declare(strict_types=1);

namespace App\Modules\Panel\Queries;

use App\Modules\Api\Application\Queries\ApiClientQuery;
use App\Modules\Api\Application\Queries\ApiUsageQuery;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Auth\Application\Queries\UserQuery;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * View-model e safhe ye yek karbar (P6-03) — FAGHAT jam'-bandi ye khoruji
 * ye Query haye Auth/Wallet/Api. Hich neveshtan.
 *
 * 🔴 Har bakhsh ba `user_id` e HAMIN karbar filter mishe (site ha، wallet)
 * — test e «nasht e karbar e digar» negahban esh ast.
 */
final readonly class UserDetailQuery
{
    private const LEDGER_LIMIT = 30;

    private const USAGE_DAYS = 30;

    /** Label e Farsi e `reason` haye ledger (reason string e azad ast). */
    private const REASONS = [
        'topup' => 'شارژ',
        'api_call' => 'فراخوانی API',
        'refund' => 'بازگشت وجه',
        'plan_purchase' => 'خرید پلن',
        'adjustment' => 'اصلاح',
        'admin_adjust' => 'اصلاح دستی ادمین',
    ];

    public function __construct(
        private UserQuery $users,
        private WalletQuery $wallets,
        private ApiClientQuery $clients,
        private ApiUsageQuery $usage,
        private TicketQuery $tickets,
    ) {}

    /** @return array<string, mixed> */
    public function show(int $userId): array
    {
        /** @var User $user */
        $user = $this->users->findOrFail($userId);

        return [
            'profile' => $this->profile($user),
            'wallet' => $this->wallet($user),
            // Kelid e idempotency e form e adjust (P6-06): HAR render taze —
            // ersal e dobare ye HAMUN form yek radif misaze. Biroon az
            // `wallet` chun karbar e bedun e kif ham sharj mishe.
            'walletAdjustKey' => (string) Str::uuid(),
            'usage' => $this->usage($user->id),
            'sites' => $this->clients->forUser($user->id)
                ->map(fn (ApiClient $client): array => $this->site($client))
                ->all(),
            'tickets' => $this->tickets->listForOwner($user->id),
        ];
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => PhoneHelper::toLocal($user->phone) ?? $user->phone,
            'email' => $user->email,
            'isAdmin' => (bool) $user->is_admin,
            'status' => $user->status,
            'disabledAt' => Jalali::dateTime($user->disabled_at),
            'disabledReason' => $user->disabled_reason,
            'level' => $user->level,
            'customerType' => $user->customer_type,
            'nationalCode' => $user->national_code,
            'createdAt' => Jalali::dateTime($user->created_at),
            'lastLoginAt' => Jalali::dateTime($user->last_login_at),
        ];
    }

    /** @return array<string, mixed>|null */
    private function wallet(User $user): ?array
    {
        $wallet = $this->wallets->findForOwner($user->id);

        if ($wallet === null) {
            return null;
        }

        $rows = $this->wallets->ledger($wallet, self::LEDGER_LIMIT);
        $admins = $this->adminLabels(array_filter(array_column($rows, 'adminId')));

        $ledger = array_map(fn (array $tx): array => [
            'id' => $tx['id'],
            'credit' => $tx['direction'] === 'credit',
            'amountToman' => Money::rialToToman((int) $tx['amountMinor']),
            'balanceAfterToman' => Money::rialToToman((int) $tx['balanceAfterMinor']),
            'reason' => self::REASONS[$tx['reason']] ?? $tx['reason'],
            'reference' => $tx['reference'],
            'note' => $tx['note'] ?? null,
            'admin' => $tx['adminId'] === null ? null : ($admins[$tx['adminId']] ?? '#'.$tx['adminId']),
            'at' => $tx['createdAt'] === null ? null : Jalali::dateTime(CarbonImmutable::parse($tx['createdAt'])),
        ], $rows);

        return [
            'id' => $wallet->id,
            'balanceToman' => Money::rialToToman((int) $wallet->balance_minor),
            'isBlocked' => (bool) $wallet->is_blocked,
            'ledger' => $ledger,
        ];
    }

    /**
     * Chart e usage e 30 rooz (P6-08) — bar = call haye yek rooz e Tehran.
     * Label faghat har 5 rooz + emruz (30 label ru 375px ja nemishe).
     *
     * @return array<string, mixed>
     */
    private function usage(int $userId): array
    {
        $usage = $this->usage->dailyForUser($userId, self::USAGE_DAYS);
        $last = count($usage['days']) - 1;

        $bars = array_map(static function (array $day, int $i) use ($last): array {
            $at = CarbonImmutable::parse($day['day'].' 12:00:00', Jalali::TIMEZONE);

            return [
                'label' => ($last - $i) % 5 === 0 ? (string) Jalali::format($at, 'm/d') : '',
                'value' => $day['calls'],
                'active' => $i === $last,
                'title' => Jalali::date($at).' — '.$day['calls'].' فراخوانی، '.Money::rialToToman($day['costMinor']).' تومان',
            ];
        }, $usage['days'], array_keys($usage['days']));

        return [
            'bars' => $bars,
            'totalCalls' => $usage['totalCalls'],
            'totalCostToman' => Money::rialToToman($usage['totalCostMinor']),
            'days' => self::USAGE_DAYS,
        ];
    }

    /**
     * Esm/mobile e admin haye sabt-konande ye adjust — YEK query baraye kol e
     * ledger (na N+1).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function adminLabels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->users->labelsFor(array_values(array_unique($ids)));
    }

    /** @return array<string, mixed> */
    private function site(ApiClient $client): array
    {
        $plan = $client->plan;

        return [
            'id' => $client->id,
            'name' => $client->name,
            'websiteUrl' => $client->getAttribute('website_url'),
            'clientId' => $client->client_id,
            'isActive' => (bool) $client->is_active,
            'deleted' => $client->trashed(),
            'plan' => $plan === null ? null : [
                'name' => $plan->name,
                'callsPerDay' => (int) $plan->calls_per_day,
                'costPerCallToman' => Money::rialToToman((int) $plan->cost_per_call_minor),
            ],
            'lastUsedAt' => Jalali::dateTime($client->getAttribute('last_used_at')),
        ];
    }
}
