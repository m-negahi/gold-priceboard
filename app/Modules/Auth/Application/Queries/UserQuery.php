<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Queries;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\Money;
use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Common\Helpers\StringHelper;
use App\Modules\Common\Queries\BaseQuery;
use Carbon\CarbonImmutable;
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

    /** @return array<string, mixed> */
    /**
     * Karbar ba SHOMARE ye mobile ya ID — baraye form haye admin ke yek field
     * e «کاربر» daran (sharj e kif pool az safhe ye mali).
     */
    public function findByPhoneOrId(string $value): ?User
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,9}$/', $value) === 1) {
            return User::query()->find((int) $value);
        }

        $phone = PhoneHelper::normalize($value);

        return $phone === null ? null : User::query()->where('phone', $phone)->first();
    }

    public function profile(int $id): array
    {
        $user = $this->findOrFail($id);

        return [...($user->identity_profile ?? []), 'id' => $user->id, 'phone' => $user->phone, 'name' => $user->name, 'email' => $user->email, 'national_code' => $user->national_code, 'customer_type' => $user->customer_type->value, 'level' => $user->level->value];
    }

    /** @return array<int, string> */
    public function ticketOwnerOptions(): array
    {
        return $this->query()->orderByDesc('id')->get(['id', 'name', 'phone'])->mapWithKeys(static fn (User $user): array => [$user->id => ($user->name ?? 'کاربر').' — '.(PhoneHelper::toLocal($user->phone) ?? $user->phone)])->all();
    }

    /** @return list<array<string, mixed>> */
    public function profileAudit(int $id): array
    {
        return DB::table('user_profile_audits')->leftJoin('users as actor', 'actor.id', '=', 'user_profile_audits.actor_id')
            ->where('user_id', $id)->orderByDesc('user_profile_audits.id')->limit(30)->get(['user_profile_audits.*', 'actor.name as actor_name'])
            ->map(static fn ($row): array => ['id' => $row->id, 'actor' => $row->actor_name ?? '#'.$row->actor_id,
                'at' => Jalali::dateTime(CarbonImmutable::parse($row->created_at)),
                'before' => json_encode(json_decode($row->before, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'after' => json_encode(json_decode($row->after, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ])->all();
    }

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

    /**
     * id -> «esm (mobile)» baraye namayesh e kesi ke kari karde (masalan
     * admin e adjust e wallet — P6-06). Yek query.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public function labelsFor(array $ids): array
    {
        return $this->query()
            ->whereKey($ids)
            ->get(['id', 'name', 'phone'])
            ->mapWithKeys(static function (User $user): array {
                $phone = PhoneHelper::toLocal($user->phone) ?? $user->phone;

                return [$user->id => $user->name === null || $user->name === '' ? $phone : "{$user->name} ({$phone})"];
            })
            ->all();
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
            'balanceRial' => $balance === null ? null : Money::rialDisplay((int) $balance),
            'sitesCount' => (int) $user->getAttribute('sites_count'),
            'lastLoginAt' => Jalali::dateTime($user->last_login_at),
            'createdAt' => Jalali::date($user->created_at),
        ];
    }
}
