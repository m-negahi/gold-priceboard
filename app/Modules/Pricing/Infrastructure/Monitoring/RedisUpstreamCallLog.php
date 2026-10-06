<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Monitoring;

use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Daftarche ye call haye ma be provider ha (P4-14) — ru Redis e DEFAULT
 * (hamun ja ye `ApiRequestLog`)، na `redis-prices`: monitoring e gozara-st
 * va `allkeys-lru` mitune evict esh kone — zarar nadare.
 *
 * Har khata saket ghurt dade mishe: log-i ke tick ro beshkane az nabudan e
 * log badtar ast (gharardad e `UpstreamLogPort`).
 */
final readonly class RedisUpstreamCallLog implements UpstreamLogPort
{
    private const KEY = 'pb:upstream:requests';

    /**
     * ~2 provider × ~2 tick dar daghighe => 300 entry ≈ yek sa'at e akhir.
     * Baraye debug e «alan chera khata mide» kafi-e؛ tarikh-che ye boland
     * kar e Influx/log file ast، na Redis.
     */
    public const MAX = 300;

    public function push(UpstreamCallDto $call): void
    {
        try {
            Redis::connection()->pipeline(function ($pipe) use ($call): void {
                $pipe->lpush(self::KEY, (string) json_encode($call->toArray(), JSON_UNESCAPED_UNICODE));
                $pipe->ltrim(self::KEY, 0, self::MAX - 1);
            });
        } catch (Throwable) {
            // Monitoring e shekaste nabayad tick ro beshkane.
        }
    }

    public function recent(int $limit = 100): array
    {
        try {
            $rows = Redis::connection()->lrange(self::KEY, 0, max(0, $limit - 1));
        } catch (Throwable) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $calls = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row, true);

            if (! is_array($decoded)) {
                // Entry e kharab list ro nemishkane — faghat rad mishe.
                continue;
            }

            try {
                $calls[] = UpstreamCallDto::fromArray($decoded);
            } catch (Throwable) {
                continue;
            }
        }

        return $calls;
    }
}
