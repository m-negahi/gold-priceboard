<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sabt e manba' (provenance) ye data-i ke backfill shode tu Influx.
 *
 * @property int $id
 * @property string $bucket
 * @property int $provider_id
 * @property string $symbol
 * @property string $source
 * @property CarbonImmutable $range_start
 * @property CarbonImmutable $range_stop
 * @property int $points
 */
class InfluxBackfill extends Model
{
    protected $fillable = [
        'bucket', 'provider_id', 'symbol', 'source',
        'range_start', 'range_stop', 'points',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'range_start' => 'immutable_datetime',
            'range_stop' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
