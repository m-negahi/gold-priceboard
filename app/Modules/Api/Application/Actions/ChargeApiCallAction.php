<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Api\Infrastructure\Persistence\Models\SiteSubscription;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Kasr e hazine ye yek call e API (P4-05).
 *
 * ── Memari: tasmim ba Lua ru Redis، haghighat tu ledger e PG ──────────
 * Octane concurrent ast: do call e hamzaman ba read-then-write e sade
 * har do az yek mande rad mishan. Lua ye `GET+DECRBY` e atomic in ro
 * mibande. Mirror e Redis faghat CACHE e tasmim ast؛ har kasr belafasele
 * tu ledger e PG (idempotent ba `reference`) sabt mishe — PG manba' e
 * haghighat mimune.
 *
 * ── 🔴 FAIL-CLOSED ──────────────────────────────────────────────────
 * Redis ya PG ghat' bashan → 503، service NEMIDIM (bar khalaf e model e
 * quota ke fail-open bud): nemitunim kasr konim = nemitunim befrushim،
 * vagarna majani dadim. (PLAN §Phase 4.)
 *
 * ── Mirror kay batel mishe ──────────────────────────────────────────
 * Har taghir e KHAREJ az in masir (topup e panel، refund e dasti) bayad
 * `invalidateMirror()` ro seda bezane؛ mirror e pak-shode ba avvalin
 * call az LEDGER (na cache e wallet) az no seed mishe. Evict shodan e
 * kelid (Redis e omumi `allkeys-lru` ast) ham faghat yek re-seed e.
 */
final readonly class ChargeApiCallAction
{
    private const MIRROR_PREFIX = 'api:wallet:bal:';

    /**
     * 🔴 Saf e PAYDAR e refund haye oftade (checkpoint e 2026-10-06).
     *
     * Vaghti customer charge shode vali pasokh error/304 bud va DOROST dar
     * hamun lahze PG ghat' shod، refund nemitune sabt she — ghablan faghat
     * mirror invalidate mishod va pul e customer baraye HAMISHE gom bud
     * (mirror az ledger seed mishe ke hanuz kasr ro dare). Hala entry tu
     * in list e Redis (AOF) mimune va scheduler har daghighe ba
     * `api:refunds:drain` replay esh mikone. Refund idempotent ast
     * (`unique(wallet, reason, reference)`)، pas replay e dobare amn e.
     */
    private const PENDING_REFUNDS_KEY = 'api:refunds:pending';

    /**
     * `-1` = mande kam، `-2` = mirror nist (seed lazem)، >=0 = mande ye jadid.
     */
    private const RESERVE_LUA = <<<'LUA'
        local bal = redis.call('GET', KEYS[1])
        if not bal then return -2 end
        local cost = tonumber(ARGV[1])
        if tonumber(bal) < cost then return -1 end
        return redis.call('DECRBY', KEYS[1], cost)
    LUA;

    public function __construct(
        private WalletAction $wallets,
        private WalletQuery $walletQuery,
    ) {}

    /**
     * @return array{status: 'ok'|'free'|'included'|'insufficient'|'blocked'|'unavailable'|'over_quota', cost: int, balance: int|null}
     */
    public function charge(ApiClient $client, string $reference): array
    {
        try {
            $wallet = $this->wallets->forOwner($client->user_id);
        } catch (Throwable) {
            return ['status' => 'unavailable', 'cost' => 0, 'balance' => null];
        }
        if ($wallet->is_blocked) {
            return ['status' => 'blocked', 'cost' => 0, 'balance' => null];
        }

        try {
            $subscription = $this->activeSubscription($client);
        } catch (Throwable) {
            return ['status' => 'unavailable', 'cost' => 0, 'balance' => null];
        }
        if ($subscription !== null) {
            try {
                if ($this->reserveIncluded($subscription, $reference)) {
                    return ['status' => 'included', 'cost' => 0, 'balance' => null];
                }
            } catch (Throwable) {
                return ['status' => 'unavailable', 'cost' => 0, 'balance' => null];
            }
        }
        $cost = $subscription === null ? $this->costFor($client) : $subscription->cost_per_call_minor;
        if ($subscription !== null && $cost === 0) {
            return ['status' => 'over_quota', 'cost' => 0, 'balance' => null];
        }

        if ($cost === 0) {
            return ['status' => 'free', 'cost' => 0, 'balance' => null];
        }

        try {
            $reserved = $this->reserve($wallet, $cost);
        } catch (Throwable) {
            // Redis ghat' — fail-closed.
            return ['status' => 'unavailable', 'cost' => $cost, 'balance' => null];
        }

        if ($reserved === -1) {
            return ['status' => 'insufficient', 'cost' => $cost, 'balance' => null];
        }

        // ── Ledger e PG — manba' e haghighat، idempotent ────────────
        try {
            $transaction = $this->wallets->debit($wallet, $cost, 'api_call', $reference, [
                'client_id' => $client->client_id,
            ]);
        } catch (Throwable) {
            // PG nashod -> reserve pas dade mishe va service nemidim.
            $this->releaseMirror($wallet, $cost);

            return ['status' => 'unavailable', 'cost' => $cost, 'balance' => null];
        }

        if (! $transaction->wasRecentlyCreated) {
            // Retry e hamun reference: ledger kasr e jadid nakard، pas
            // reserve ye Redis ham bayad pas dade she (yek bar kasr).
            $this->releaseMirror($wallet, $cost);
        }

        return ['status' => 'ok', 'cost' => $cost, 'balance' => $transaction->balance_after_minor];
    }

    /**
     * Refund e yek call (error/304 — PLAN): radif e jadid e ledger +
     * bargardandan e mirror. Idempotent.
     */
    public function refundCall(ApiClient $client, string $reference, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        $wallet = null;

        try {
            $wallet = $this->wallets->forOwner($client->user_id);
            $refund = $this->wallets->refund($wallet, $cost, $reference, [
                'client_id' => $client->client_id,
            ]);

            if ($refund->wasRecentlyCreated) {
                $this->releaseMirror($wallet, $cost);
            }
        } catch (Throwable) {
            // Refund alan nashod (ehtemalan PG ghat') → compensation e
            // PAYDAR: entry tu saf mimune va scheduler replay mikone.
            $this->queuePendingRefund($client, $reference, $cost);

            if ($wallet !== null) {
                // Mirror batel ta call e ba'di az ledger e dorost seed she —
                // customer hich vaght az ledger jolo-tar kasr nemishe.
                $this->invalidateMirror($wallet);
            }
        }
    }

    /**
     * Replay e saf e refund haye oftade — masraf: `api:refunds:drain`
     * (scheduler، har daghighe).
     *
     * @return array{drained: int, requeued: int}
     */
    public function drainPendingRefunds(int $max = 500): array
    {
        $drained = 0;

        for ($i = 0; $i < $max; $i++) {
            try {
                $raw = Redis::connection()->rpop(self::PENDING_REFUNDS_KEY);
            } catch (Throwable) {
                // Redis ghat' — talash e ba'di ye scheduler.
                return ['drained' => $drained, 'requeued' => 0];
            }

            if (! is_string($raw) || $raw === '') {
                return ['drained' => $drained, 'requeued' => 0];
            }

            /** @var array{user_id?: int, client_id?: string, reference?: string, cost?: int}|null $entry */
            $entry = json_decode($raw, true);

            if (! is_array($entry) || (int) ($entry['cost'] ?? 0) <= 0 || ($entry['reference'] ?? '') === '') {
                // Entry e kharab ro negah dashtan faghat saf ro ghofl mikone.
                continue;
            }

            try {
                $wallet = $this->wallets->forOwner((int) $entry['user_id']);
                $refund = $this->wallets->refund($wallet, (int) $entry['cost'], (string) $entry['reference'], [
                    'client_id' => (string) ($entry['client_id'] ?? ''),
                    'replayed' => true,
                ]);

                if ($refund->wasRecentlyCreated) {
                    // Mirror ba'd az refund e ta'khiri az ledger seed mishe.
                    $this->invalidateMirror($wallet);
                }

                $drained++;
            } catch (Throwable) {
                // Hanuz nashod — entry bar migarde tu saf va motevaghef
                // mishim (PG ehtemalan hanuz ghat' e).
                try {
                    Redis::connection()->rpush(self::PENDING_REFUNDS_KEY, $raw);
                } catch (Throwable) {
                    Log::warning('api.refund_requeue_failed', ['entry' => $raw]);
                }

                return ['drained' => $drained, 'requeued' => 1];
            }
        }

        return ['drained' => $drained, 'requeued' => 0];
    }

    private function queuePendingRefund(ApiClient $client, string $reference, int $cost): void
    {
        $entry = json_encode([
            'user_id' => $client->user_id, 'client_id' => $client->client_id,
            'reference' => $reference, 'cost' => $cost,
        ]);

        try {
            Redis::connection()->lpush(self::PENDING_REFUNDS_KEY, (string) $entry);
        } catch (Throwable) {
            // PG VA Redis har do ghat' — tanha kari ke mimune sabt e boland e.
            Log::warning('api.refund_queue_failed', ['entry' => $entry]);
        }
    }

    public function releaseIncluded(ApiClient $client, string $reference): void
    {
        DB::transaction(static function () use ($client, $reference): void {
            $call = DB::table('site_subscription_calls')->where('reference', $reference)->first();
            if ($call === null) {
                return;
            }
            $subscription = SiteSubscription::query()
                ->where('api_client_id', $client->id)
                ->lockForUpdate()->find($call->site_subscription_id);
            if ($subscription === null) {
                return;
            }
            $deleted = DB::table('site_subscription_calls')->where('id', $call->id)->delete();
            if ($deleted === 0) {
                return;
            }
            $subscription->decrement('calls_used');
            DB::table('site_subscription_days')
                ->where('site_subscription_id', $subscription->id)
                ->where('day', $call->day)
                ->decrement('calls_used');
        });
    }

    /** Topup/taghir e dasti ye mande bayad in ro seda bezane. */
    public function invalidateMirror(Wallet $wallet): void
    {
        try {
            Redis::connection()->del(self::MIRROR_PREFIX.$wallet->id);
        } catch (Throwable) {
            // Mirror e kohne badtarin halatesh yek 402 e eshtebah ta
            // avvalin re-seed ast؛ TTL e seed in ro mahdud mikone.
        }
    }

    private function reserve(Wallet $wallet, int $cost): int
    {
        $key = self::MIRROR_PREFIX.$wallet->id;

        $result = (int) Redis::connection()->eval(self::RESERVE_LUA, 1, $key, $cost);

        if ($result !== -2) {
            return $result;
        }

        // Seed az LEDGER (manba' e haghighat)، na cache e wallet. `NX` ta
        // do seed e hamzaman ru ham nanevisan؛ TTL ta mirror e yatim
        // (masalan ba'd az taghir e dasti ye DB) abadi nashe.
        $balance = $this->walletQuery->balanceFromLedger($wallet);
        Redis::connection()->set($key, (string) $balance, 'EX', 6 * 3600, 'NX');

        return (int) Redis::connection()->eval(self::RESERVE_LUA, 1, $key, $cost);
    }

    private function releaseMirror(Wallet $wallet, int $cost): void
    {
        try {
            // Faghat age mirror hanuz hast — INCRBY ru kelid e nabude yek
            // mande ye ja'li misaze.
            Redis::connection()->eval(<<<'LUA'
                if redis.call('EXISTS', KEYS[1]) == 0 then return 0 end
                return redis.call('INCRBY', KEYS[1], ARGV[1])
                LUA, 1, self::MIRROR_PREFIX.$wallet->id, $cost);
        } catch (Throwable) {
            $this->invalidateMirror($wallet);
        }
    }

    private function activeSubscription(ApiClient $client): ?SiteSubscription
    {
        $now = CarbonImmutable::now();

        return SiteSubscription::query()
            ->where('api_client_id', $client->id)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderByDesc('starts_at')->first();
    }

    private function reserveIncluded(SiteSubscription $subscription, string $reference): bool
    {
        return DB::transaction(static function () use ($subscription, $reference): bool {
            /** @var SiteSubscription $locked */
            $locked = SiteSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $existing = DB::table('site_subscription_calls')->where('reference', $reference)->first();
            if ($existing !== null) {
                return $existing->site_subscription_id === $locked->id;
            }
            $day = CarbonImmutable::now('Asia/Tehran')->format('Y-m-d');
            DB::table('site_subscription_days')->insertOrIgnore([
                'site_subscription_id' => $locked->id, 'day' => $day,
                'calls_used' => 0,
            ]);
            $daily = DB::table('site_subscription_days')
                ->where('site_subscription_id', $locked->id)->where('day', $day)->first();
            if ($locked->calls_used >= $locked->calls_included
                || ($locked->calls_per_day > 0 && $daily->calls_used >= $locked->calls_per_day)) {
                return false;
            }
            DB::table('site_subscription_calls')->insert([
                'site_subscription_id' => $locked->id,
                'reference' => $reference, 'day' => $day, 'created_at' => now(),
            ]);
            $locked->increment('calls_used');
            DB::table('site_subscription_days')->where('id', $daily->id)->increment('calls_used');

            return true;
        });
    }

    private function costFor(ApiClient $client): int
    {
        $group = $client->price_group_id !== null
            ? $client->priceGroup
            : ApiPriceGroup::query()->where('is_default', true)->first();

        return max(0, (int) ($group?->cost_per_call_minor ?? 0));
    }
}
