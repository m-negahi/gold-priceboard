<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Usage;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Shomarande ye ruzane ye call haye API (P6-08) — yek HASH be ezaye har
 * rooz e TEHRAN: `api:usage:{Y-m-d}` → field = `api_clients.id`.
 *
 * Yek hash baraye kol e rooz (na kelid e jodagane baraye har client) ta
 * rollup ba YEK `HGETALL` hame ro begire.
 *
 * TTL 8 rooz: rollup har 15 daghighe minevise، pas Redis faghat panjere ye
 * kutah ast؛ tarikhche ye paydar tu `api_usage_daily` e PG ast.
 *
 * 🔴 Shekast e shomaresh HARGEZ call ro nemikoshe (monitoring ast، na
 * billing — pul az ledger miad).
 */
final readonly class RedisUsageCounter
{
    public const TIMEZONE = 'Asia/Tehran';

    private const PREFIX = 'api:usage:';

    private const TTL_SECONDS = 8 * 86_400;

    public function hit(int $clientPk, ?CarbonImmutable $at = null): void
    {
        $key = self::PREFIX.($at ?? CarbonImmutable::now())->setTimezone(self::TIMEZONE)->format('Y-m-d');

        try {
            $count = (int) Redis::connection()->hincrby($key, (string) $clientPk, 1);

            if ($count === 1) {
                // Avvalin call e in client dar in rooz — TTL (dobare set
                // kardan bi-zarar ast).
                Redis::connection()->expire($key, self::TTL_SECONDS);
            }
        } catch (Throwable) {
            // Redis ghat' — faghat yek call e shomorde-nashode.
        }
    }

    /**
     * @param  string  $day  `Y-m-d` e Tehran
     * @return array<int, int> clientPk => calls
     */
    public function forDay(string $day): array
    {
        try {
            $raw = Redis::connection()->hgetall(self::PREFIX.$day);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $counts = [];

        foreach ($raw as $clientPk => $calls) {
            $counts[(int) $clientPk] = (int) $calls;
        }

        return $counts;
    }
}
