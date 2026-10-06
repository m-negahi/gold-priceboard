<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Cache;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\HotCachePort;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Store e dagh — chizi ke har bazdid-konande mikhune.
 *
 * ── 🔴 Se ghanun ─────────────────────────────────────────────────────
 * 1. Ru connection e `prices` (instance e `redis-prices`), na Redis e omumi.
 *    Redis e omumi `allkeys-lru` ast: tu feshar e memory HAR kelidi ro
 *    mindaze — shamel e gheymat ha — dar hali ke khodesh ro salem gozaresh
 *    mide, va tablo bedun e hich error khali mishe.
 * 2. Kelid ha BEDUN e TTL. Hamrah e `noeviction` ya'ni gheymat hargez
 *    khod-be-khod nemire.
 * 3. Model e hydrate-shode HARGEZ cache nemishe — faghat array e kham.
 *    Zire Octane + Redis, model e serialize-shode `__PHP_Incomplete_Class`
 *    mide.
 *
 * ── Chera kol e tablo tu YEK kelid ast ───────────────────────────────
 * Reader (tablo, jadval e moghayese, `/api/board.json`) hamishe hame ro
 * mikhad. Yek `GET` jaye N ta — yek round-trip be jaye 50 ta.
 */
final readonly class RedisHotCache implements HotCachePort
{
    private const CONNECTION = 'prices';

    private const BOARD_KEY = 'pb:board';

    private const VERSION_KEY = 'pb:board:v';

    private const HEALTH_KEY = 'pb:health';

    private const PROVIDERS_KEY = 'pb:providers';

    private const SPREAD_VERSION_KEY = 'pb:spreads:v';

    private const HEARTBEAT_KEY = 'pb:board:hb';

    private const TICK_REPORTS_KEY = 'pb:ticks:reports';

    /** Saghf e daftarche ye monitoring — ~50 tick ≈ 12.5 daghighe. */
    private const MAX_TICK_REPORTS = 50;

    public function board(): array
    {
        $raw = $this->connection()->get(self::BOARD_KEY);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        /** @var array<string, array<string, mixed>>|null $decoded */
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $snapshots = [];

        foreach ($decoded as $key => $row) {
            $dto = $this->toDto((array) $row);

            if ($dto !== null) {
                $snapshots[$key] = $dto;
            }
        }

        return $snapshots;
    }

    public function putBoard(array $snapshots): void
    {
        if ($snapshots === []) {
            return;
        }

        $payload = [];

        foreach ($snapshots as $snapshot) {
            $payload[$snapshot->key()] = $this->toArray($snapshot);
        }

        // Yek pipeline: neveshtan e tablo va bump e version ba ham mirаn, pas
        // reader hargez version e jadid ba data ye ghadim nemibine.
        // ⚠️ Hich `expire` inja nist — amdi.
        $this->connection()->pipeline(function ($pipe) use ($payload): void {
            $pipe->set(self::BOARD_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
            $pipe->incr(self::VERSION_KEY);
        });
    }

    public function boardVersion(): int
    {
        return (int) ($this->connection()->get(self::VERSION_KEY) ?? 0);
    }

    public function putProviders(array $providers): void
    {
        // Bedun e TTL — in fallback e ghat'i ye Postgres ast.
        $this->connection()->set(
            self::PROVIDERS_KEY,
            (string) json_encode($providers, JSON_UNESCAPED_UNICODE),
        );
    }

    public function providers(): ?array
    {
        $raw = $this->connection()->get(self::PROVIDERS_KEY);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function spreadVersion(): int
    {
        return (int) ($this->connection()->get(self::SPREAD_VERSION_KEY) ?? 0);
    }

    public function bumpSpreadVersion(): int
    {
        return (int) $this->connection()->incr(self::SPREAD_VERSION_KEY);
    }

    public function putHeartbeat(int $at): void
    {
        // Bedun e TTL — mesl e baghi ye kelid haye gheymat.
        $this->connection()->set(self::HEARTBEAT_KEY, (string) $at);
    }

    public function heartbeatAt(): ?int
    {
        $raw = $this->connection()->get(self::HEARTBEAT_KEY);

        return is_string($raw) && $raw !== '' ? (int) $raw : null;
    }

    public function putHealth(ProviderHealthDto $health): void
    {
        $this->connection()->hset(
            self::HEALTH_KEY,
            $health->providerKey,
            (string) json_encode($health->toArray(), JSON_UNESCAPED_UNICODE),
        );
    }

    public function health(): array
    {
        /** @var array<string, string> $rows */
        $rows = $this->connection()->hgetall(self::HEALTH_KEY) ?: [];

        $health = [];

        foreach ($rows as $providerKey => $json) {
            $data = json_decode((string) $json, true);

            if (! is_array($data)) {
                continue;
            }

            $health[$providerKey] = new ProviderHealthDto(
                providerKey: (string) ($data['provider_key'] ?? $providerKey),
                succeeded: (bool) ($data['succeeded'] ?? false),
                attemptedAt: CarbonImmutable::parse((string) ($data['attempted_at'] ?? 'now')),
                latencyMs: (int) ($data['latency_ms'] ?? 0),
                symbolCount: (int) ($data['symbol_count'] ?? 0),
                rejectedCount: (int) ($data['rejected_count'] ?? 0),
                error: $data['error'] ?? null,
                consecutiveFailures: (int) ($data['consecutive_failures'] ?? 0),
                payloadHash: $data['payload_hash'] ?? null,
            );
        }

        return $health;
    }

    public function putTickReport(array $report): void
    {
        // Jadid tarin aval + saghf — yek daftarche ye gozara ye monitoring.
        $this->connection()->pipeline(function ($pipe) use ($report): void {
            $pipe->lpush(self::TICK_REPORTS_KEY, (string) json_encode($report, JSON_UNESCAPED_UNICODE));
            $pipe->ltrim(self::TICK_REPORTS_KEY, 0, self::MAX_TICK_REPORTS - 1);
        });
    }

    public function tickReports(int $limit = 20): array
    {
        $rows = $this->connection()->lrange(self::TICK_REPORTS_KEY, 0, max(0, $limit - 1));

        if (! is_array($rows)) {
            return [];
        }

        $reports = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row, true);

            if (is_array($decoded)) {
                $reports[] = $decoded;
            }
        }

        return $reports;
    }

    /**
     * ⚠️ In method HARGEZ throw nemikone.
     *
     * Caller bahash tasmim migire: tablo be Postgres fallback kone, va tick
     * bedun e lock edame bede. (Lock guard e performance ast na correctness —
     * hame ye sink ha idempotent-and.) Agar inja throw konim, hamun ghat'i-i
     * ke mikhastim tahammol konim kol e tick ro mikoshe.
     */
    public function isAvailable(): bool
    {
        try {
            $this->connection()->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Connection e taze tu har farakhani.
     *
     * Zire Swoole, negah dashtan e yek connection tu property e class mitune
     * beyn e coroutine ha ghati beshe. Manager e khod e Laravel pool ro
     * modiriat mikone.
     */
    private function connection(): mixed
    {
        return Redis::connection(self::CONNECTION);
    }

    /** @return array<string, mixed> */
    private function toArray(SnapshotDto $snapshot): array
    {
        return [
            'p' => $snapshot->providerKey,
            's' => $snapshot->instrument->value,
            'sd' => $snapshot->side->value,
            // `scale` hamrah mire ta reader (JS, API) baraye tafsir e `minor`
            // be enum e PHP e ma niaz nadashte bashe.
            'q' => $snapshot->price->quote->value,
            'sc' => $snapshot->price->quote->scale(),
            'v' => $snapshot->price->minor,
            'pv' => $snapshot->previous?->minor,
            'do' => $snapshot->dayOpen?->minor,
            'dh' => $snapshot->dayHigh?->minor,
            'dl' => $snapshot->dayLow?->minor,
            'cp' => $snapshot->changePercent,
            'd' => $snapshot->direction->value,
            'po' => $snapshot->pendingOutlier?->minor,
            'ua' => $snapshot->upstreamAt?->toIso8601String(),
            'ul' => $snapshot->upstreamLagSeconds,
            'qa' => $snapshot->quotedAt->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $row */
    private function toDto(array $row): ?SnapshotDto
    {
        $instrument = Instrument::tryFrom((string) ($row['s'] ?? ''));
        $side = Side::tryFrom((string) ($row['sd'] ?? ''));
        $quote = QuoteCurrency::tryFrom((string) ($row['q'] ?? ''));

        if ($instrument === null || $side === null || $quote === null) {
            return null;
        }

        $price = static fn (mixed $minor): ?Price => $minor === null
            ? null
            : new Price((int) $minor, $quote);

        return new SnapshotDto(
            providerKey: (string) ($row['p'] ?? ''),
            instrument: $instrument,
            side: $side,
            price: new Price((int) ($row['v'] ?? 0), $quote),
            quotedAt: CarbonImmutable::parse((string) ($row['qa'] ?? 'now')),
            previous: $price($row['pv'] ?? null),
            dayOpen: $price($row['do'] ?? null),
            dayHigh: $price($row['dh'] ?? null),
            dayLow: $price($row['dl'] ?? null),
            changePercent: (float) ($row['cp'] ?? 0),
            direction: Direction::tryFrom((string) ($row['d'] ?? '')) ?? Direction::Flat,
            upstreamAt: isset($row['ua']) ? CarbonImmutable::parse((string) $row['ua']) : null,
            upstreamLagSeconds: (int) ($row['ul'] ?? 0),
            pendingOutlier: $price($row['po'] ?? null),
        );
    }
}
