<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Queries;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Common\Helpers\StringHelper;
use App\Modules\Common\Queries\BaseQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Khundan e karbaran baraye panel (P6-02/P6-03).
 *
 * Mande ye wallet va tedad e site ba SUBQUERY miad (na relation): `User`
 * tu module e Auth relation be model haye Api/Wallet nadare va nabayad
 * dashte bashe — in yek read-model e panel ast.
 *
 * @extends BaseQuery<User>
 */
final readonly class UserQuery extends BaseQuery
{
    public const PER_PAGE = 25;

    protected function model(): string
    {
        return User::class;
    }

    /**
     * @param  array{q?: ?string, role?: ?string, status?: ?string, level?: ?string}  $filters  ghablan validate shode
     */
    public function panelList(array $filters, int $page = 1): LengthAwarePaginator
    {
        $builder = $this->query()
            ->select('users.*')
            ->selectSub(
                DB::table('wallets')->select('balance_minor')
                    ->whereColumn('wallets.owner_id', 'users.id')
                    ->where('currency', 'IRR')
                    ->limit(1),
                'wallet_balance_minor',
            )
            ->selectSub(
                DB::table('api_clients')->selectRaw('count(*)')
                    ->whereColumn('api_clients.user_id', 'users.id')
                    ->whereNull('deleted_at'),
                'sites_count',
            );

        $this->applySearch($builder, (string) ($filters['q'] ?? ''));

        match ($filters['role'] ?? null) {
            'admin' => $builder->where('is_admin', true),
            'customer' => $builder->where('is_admin', false),
            default => null,
        };

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $builder->where('status', $filters['status']);
        }

        if (($filters['level'] ?? null) !== null && $filters['level'] !== '') {
            $builder->where('level', $filters['level']);
        }

        return $builder
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->withQueryString()
            ->through(fn (User $user): array => $this->listRow($user));
    }

    /**
     * Search: mobile (har shekl — `0912…`، `912…`، raghame Farsi) ya esm.
     *
     * @param  Builder<User>  $builder
     */
    private function applySearch(Builder $builder, string $raw): void
    {
        $term = trim((string) StringHelper::numbersToEnglish($raw));

        if ($term === '') {
            return;
        }

        // Vorudi ye «adadi» (ragham + fasele/khat-tire/+/parantez) = search e
        // mobile؛ har chiz e digar = search e esm.
        if (preg_match('/^[\d\s\-+()]+$/', $term) === 1) {
            $digits = preg_replace('/\D+/', '', $term) ?? '';
            // `0912…` / `98912…` / `0098912…` -> `912…` ta ba `+98912…` match she.
            $needle = ltrim(preg_replace('/^(0098|98)/', '', $digits) ?? '', '0');

            $builder->where('phone', 'like', '%'.($needle === '' ? $digits : $needle).'%');

            return;
        }

        $builder->whereRaw('lower(name) like ?', ['%'.addcslashes(mb_strtolower($term), '%_\\').'%']);
    }

    /** @return array<string, mixed> */
    private function listRow(User $user): array
    {
        $balance = $user->getAttribute('wallet_balance_minor');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => PhoneHelper::toLocal($user->phone) ?? $user->phone,
            'isAdmin' => (bool) $user->is_admin,
            'status' => $user->status,
            'level' => $user->level,
            'customerType' => $user->customer_type,
            'balanceToman' => $balance === null ? null : Money::rialToToman((int) $balance),
            'sitesCount' => (int) $user->getAttribute('sites_count'),
            'lastLoginAt' => Jalali::dateTime($user->last_login_at),
            'createdAt' => Jalali::date($user->created_at),
        ];
    }
}
