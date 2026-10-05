<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Otp;

use App\Modules\Auth\Application\Dto\OtpEntryDto;
use App\Modules\Auth\Domain\Ports\OtpStorePort;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Adapter e Redis baraye OTP — ru connection e `default` (Redis e omumi).
 *
 * ── Chera Redis e OMUMI va na `redis-prices` ────────────────────────
 * OTP yek cache e VAGHEI-e: evict shodanesh (zire `allkeys-lru`) faghat
 * ya'ni «dobare befrest» — na az dast raftan e data. `redis-prices` ba
 * `noeviction` baraye data-i-e ke nabayad bere؛ OTP un nist.
 *
 * ── Yek kelid، na do ──────────────────────────────────────────────────
 * Code-hash va shomaresh e talash tu YEK hash e Redis-and، pas ba ham
 * expire/evict mishan. Hich halati nist ke shomaresh bemune vali code na
 * (talash e azad)، ya code bemune vali shomaresh na.
 */
final readonly class RedisOtpStore implements OtpStorePort
{
    private const KEY_PREFIX = 'otp:code:';

    private const COOLDOWN_PREFIX = 'otp:cooldown:';

    /**
     * 🔴 Increment e SHARTI — faghat agar kelid vojud dare.
     *
     * `HINCRBY` ye khali ru kelid e nabude، kelid ro MISAZE — ba `attempts`
     * e tanha va BEDUN e TTL: yek kelid e yatim e abadi. Lua check+increment
     * ro atomic mikone (beyn e `EXISTS` va `HINCRBY` e joda، yek `put` e
     * hamzaman mitune beshine).
     */
    private const INCREMENT_IF_EXISTS = <<<'LUA'
        if redis.call('EXISTS', KEYS[1]) == 1 then
            return redis.call('HINCRBY', KEYS[1], 'attempts', 1)
        end
        return -1
    LUA;

    public function __construct(private string $connection = 'default') {}

    public function put(string $phone, string $codeHash, int $ttlSeconds): void
    {
        $key = self::KEY_PREFIX.$phone;

        // MULTI: jaygozini + TTL ba ham — hich panjare-i ke entry e jadid
        // bedun e TTL bashe.
        $this->redis()->multi();
        $this->redis()->del($key);
        $this->redis()->hmset($key, ['hash' => $codeHash, 'attempts' => 0]);
        $this->redis()->expire($key, $ttlSeconds);
        $this->redis()->exec();
    }

    public function get(string $phone): ?OtpEntryDto
    {
        /** @var array<string, string>|false $row */
        $row = $this->redis()->hgetall(self::KEY_PREFIX.$phone);

        if (! is_array($row) || ! isset($row['hash']) || $row['hash'] === '') {
            return null;
        }

        return new OtpEntryDto(
            codeHash: (string) $row['hash'],
            attempts: (int) ($row['attempts'] ?? 0),
        );
    }

    public function recordFailedAttempt(string $phone): int
    {
        /*
         * 🔴 HICH prefix e dasti — phpredis khodesh `KEYS` haye EVAL ro ham
         * prefix mizane (`OPT_PREFIX` ru hame ye numkeys e'mal mishe).
         *
         * In ro HADS nazadam — test e gharardad gereft esh: ba prefix e
         * dasti، kelid DO bar prefix mikhord va `EXISTS` hichvaght peyda
         * nemikard (hamishe `-1`). Empiri check shod:
         *   EVAL bedun e prefix e dasti -> 1 (peyda shod)
         *   EVAL ba prefix e dasti     -> 0
         * Hamun dars e `RedisHotCache` (P1)، in bar tu variant e EVAL.
         */
        return (int) $this->redis()->eval(
            self::INCREMENT_IF_EXISTS,
            1,
            self::KEY_PREFIX.$phone,
        );
    }

    public function invalidate(string $phone): void
    {
        $this->redis()->del(self::KEY_PREFIX.$phone);
    }

    public function ttlRemaining(string $phone): int
    {
        return max(0, (int) $this->redis()->ttl(self::KEY_PREFIX.$phone));
    }

    public function startCooldown(string $phone, int $seconds): void
    {
        $this->redis()->setex(self::COOLDOWN_PREFIX.$phone, $seconds, '1');
    }

    public function clearCooldown(string $phone): void
    {
        $this->redis()->del(self::COOLDOWN_PREFIX.$phone);
    }

    public function cooldownRemaining(string $phone): int
    {
        $ttl = (int) $this->redis()->ttl(self::COOLDOWN_PREFIX.$phone);

        // `-2` = kelid nist، `-1` = bedun e TTL (nabayad pish biad) — har do
        // ya'ni cooldown-i dar kar nist.
        return max(0, $ttl);
    }

    private function redis(): Connection
    {
        return Redis::connection($this->connection);
    }
}
