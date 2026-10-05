<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use App\Modules\Pricing\Domain\Enums\HealthState;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Yek manba' e gheymat.
 *
 * ⚠️ Model faghat tu `Infrastructure/Persistence` zendegi mikone. Laye ye
 * Application faghat `SnapshotDto` mibine, na in class ro.
 *
 * @property int $id
 * @property string $key
 * @property string $driver
 * @property string $kind
 * @property string $name
 * @property string $name_fa
 * @property string|null $website
 * @property string|null $logo_path
 * @property bool $is_active
 * @property bool $in_comparison
 * @property bool $on_board
 * @property bool $in_chart
 * @property int $timeout
 * @property int $poll_interval_seconds
 * @property CarbonImmutable|null $next_poll_at
 * @property int $freshness_seconds
 * @property int $hide_after_seconds
 * @property UpstreamUnit $upstream_unit
 * @property int $sort
 * @property array<string, mixed> $options
 * @property CarbonImmutable|null $last_attempt_at
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property string|null $last_error
 * @property int|null $last_latency_ms
 * @property int|null $last_symbol_count
 * @property int|null $last_rejected_count
 * @property int $consecutive_failures
 * @property HealthState $health_state
 * @property string|null $last_payload_hash
 */
class Provider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key', 'driver', 'kind', 'name', 'name_fa', 'website', 'logo_path',
        'is_active', 'in_comparison', 'on_board', 'in_chart',
        'timeout', 'poll_interval_seconds', 'next_poll_at',
        'freshness_seconds', 'hide_after_seconds', 'upstream_unit', 'sort', 'options',
        'last_attempt_at', 'last_success_at', 'last_failure_at', 'last_error',
        'last_latency_ms', 'last_symbol_count', 'last_rejected_count',
        'consecutive_failures', 'health_state', 'last_payload_hash',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'in_comparison' => 'boolean',
            'on_board' => 'boolean',
            'in_chart' => 'boolean',
            'timeout' => 'integer',
            'poll_interval_seconds' => 'integer',
            'freshness_seconds' => 'integer',
            'hide_after_seconds' => 'integer',
            'sort' => 'integer',
            'consecutive_failures' => 'integer',
            'last_latency_ms' => 'integer',
            'last_symbol_count' => 'integer',
            'last_rejected_count' => 'integer',
            'options' => 'array',
            'upstream_unit' => UpstreamUnit::class,
            'health_state' => HealthState::class,
            'next_poll_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ProviderSpread, $this> */
    public function spreads(): HasMany
    {
        return $this->hasMany(ProviderSpread::class);
    }

    /** @return HasMany<PriceSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    /** @param Builder<Provider> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Provider hayi ke alan bayad poll shan.
     *
     * `next_poll_at` e null = hargez poll nashode (provider e taze) -> bayad
     * poll she. In halghe ye asli ye `pricing:tick` ast, pas index e
     * `(is_active, next_poll_at)` ru hamin ejra mishe.
     *
     * @param  Builder<Provider>  $query
     */
    public function scopeDueForPoll(Builder $query, \DateTimeInterface $now): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('next_poll_at')->orWhere('next_poll_at', '<=', $now);
            });
    }
}
