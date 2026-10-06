<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Daftarche ye gozara ye call haye API (P4-11) — mini-Telescope.
 *
 * Redis list، jadid tarin aval، saghf e 200 (~chand sa'at traffic e
 * ma'muli). Monitoring e gozara-st: gom shodanesh zarar nadare، pas har
 * khata saket ghurt dade mishe — logging HARGEZ nabayad call ro bokoshe.
 */
final readonly class ApiRequestLog
{
    private const KEY = 'pb:api:requests';

    private const MAX = 200;

    /** @param array<string, mixed> $entry */
    public function push(array $entry): void
    {
        try {
            Redis::connection()->pipeline(function ($pipe) use ($entry): void {
                $pipe->lpush(self::KEY, (string) json_encode($entry, JSON_UNESCAPED_UNICODE));
                $pipe->ltrim(self::KEY, 0, self::MAX - 1);
            });
        } catch (Throwable) {
            // Monitoring e shekaste nabayad request ro beshkane.
        }
    }

    /** @return array<int, array<string, mixed>> jadid tarin aval */
    public function recent(int $limit = 50): array
    {
        try {
            $rows = Redis::connection()->lrange(self::KEY, 0, max(0, $limit - 1));
        } catch (Throwable) {
            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row, true);

            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return $entries;
    }
}
