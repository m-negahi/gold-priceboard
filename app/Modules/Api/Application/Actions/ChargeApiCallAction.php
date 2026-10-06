<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPriceGroup;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
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
     * @return array{status: 'ok'|'free'|'insufficient'|'blocked'|'unavailable', cost: int, balance: int|null}
     */
    public function charge(ApiClient $client, string $reference): array
    {
        $cost = $this->costFor($client);

        if ($cost === 0) {
            return ['status' => 'free', 'cost' => 0, 'balance' => null];
        }

        $wallet = $this->wallets->forOwner($client->user_id);

        if ($wallet->is_blocked) {
            return ['status' => 'blocked', 'cost' => $cost, 'balance' => null];
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

        $wallet = $this->wallets->forOwner($client->user_id);

        try {
            $refund = $this->wallets->refund($wallet, $cost, $reference, [
                'client_id' => $client->client_id,
            ]);

            if ($refund->wasRecentlyCreated) {
                $this->releaseMirror($wallet, $cost);
            }
        } catch (Throwable) {
            // Refund nashod: mirror ro batel mikonim ta call e ba'di az
            // ledger e DOROST seed she — customer hich vaght az ledger
            // jolo-tar kasr nemishe.
            $this->invalidateMirror($wallet);
        }
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
            if ((bool) Redis::connection()->exists(self::MIRROR_PREFIX.$wallet->id)) {
                Redis::connection()->incrby(self::MIRROR_PREFIX.$wallet->id, $cost);
            }
        } catch (Throwable) {
            $this->invalidateMirror($wallet);
        }
    }

    private function costFor(ApiClient $client): int
    {
        $group = $client->price_group_id !== null
            ? $client->priceGroup
            : ApiPriceGroup::query()->where('is_default', true)->first();

        return max(0, (int) ($group?->cost_per_call_minor ?? 0));
    }
}
