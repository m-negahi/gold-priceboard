<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AKHARIN gheymat — yek radif be ezaye har (provider, symbol, side).
 *
 * ⚠️ Tarikhche nist. `raw_minor` BEDUN e spread ast.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $symbol
 * @property Side $side
 * @property QuoteCurrency $quote_currency
 * @property int $scale
 * @property int $raw_minor
 * @property int|null $prev_minor
 * @property int|null $day_open_minor
 * @property int|null $day_high_minor
 * @property int|null $day_low_minor
 * @property float $change_pct
 * @property Direction $direction
 * @property int|null $pending_outlier_minor
 * @property bool $has_native_sides
 * @property CarbonImmutable|null $upstream_at
 * @property int|null $upstream_lag_seconds
 * @property CarbonImmutable $quoted_at
 */
class PriceSnapshot extends Model
{
    protected $fillable = [
        'provider_id', 'symbol', 'side', 'quote_currency', 'scale',
        'raw_minor', 'prev_minor', 'day_open_minor', 'day_high_minor', 'day_low_minor',
        'change_pct', 'direction', 'pending_outlier_minor', 'has_native_sides',
        'upstream_at', 'upstream_lag_seconds', 'quoted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scale' => 'integer',
            // ⚠️ `integer` cast lazem ast: driver e Postgres `bigint` ro be
            // sorat e STRING bar migardune va bedun e in, `raw_minor` yek
            // reshte mishe va har moghayese ye `===` saket shekast mikhore.
            'raw_minor' => 'integer',
            'prev_minor' => 'integer',
            'day_open_minor' => 'integer',
            'day_high_minor' => 'integer',
            'day_low_minor' => 'integer',
            'pending_outlier_minor' => 'integer',
            'upstream_lag_seconds' => 'integer',
            'change_pct' => 'float',
            'has_native_sides' => 'boolean',
            'side' => Side::class,
            'quote_currency' => QuoteCurrency::class,
            'direction' => Direction::class,
            'upstream_at' => 'immutable_datetime',
            'quoted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function instrument(): ?Instrument
    {
        return Instrument::tryFrom($this->symbol);
    }

    /** @param Builder<PriceSnapshot> $query */
    public function scopeForSymbol(Builder $query, Instrument $instrument): void
    {
        $query->where('symbol', $instrument->value);
    }
}
