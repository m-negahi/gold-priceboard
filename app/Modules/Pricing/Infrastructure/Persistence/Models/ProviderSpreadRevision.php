<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use App\Modules\Pricing\Domain\Enums\SpreadMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarikhche ye append-only ye taghirat e spread.
 *
 * ⚠️ HARGEZ `update` ya `delete` nemishe. `$timestamps = false` chun khodesh
 * `created_at` ro dasti mizare va `updated_at` ma'ni nadare.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $symbol
 * @property SpreadMode $buy_mode
 * @property float $buy_value
 * @property SpreadMode $sell_mode
 * @property float $sell_value
 * @property string|null $changed_by
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable $created_at
 */
class ProviderSpreadRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider_id', 'symbol',
        'buy_mode', 'buy_value', 'sell_mode', 'sell_value',
        'changed_by', 'effective_from', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'buy_value' => 'float',
            'sell_value' => 'float',
            'buy_mode' => SpreadMode::class,
            'sell_mode' => SpreadMode::class,
            'effective_from' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
