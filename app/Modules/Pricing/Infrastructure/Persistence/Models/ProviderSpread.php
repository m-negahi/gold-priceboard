<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\ValueObjects\Spread;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spread e yek (provider, symbol). `symbol === '*'` ya'ni default e wildcard.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $symbol
 * @property SpreadMode $buy_mode
 * @property float $buy_value
 * @property SpreadMode $sell_mode
 * @property float $sell_value
 * @property bool $is_enabled
 * @property int|null $freshness_seconds
 * @property int|null $sort
 */
class ProviderSpread extends Model
{
    /** Symbol e wildcard — default e hame ye instrument haye in provider. */
    public const WILDCARD = '*';

    protected $fillable = [
        'provider_id', 'symbol',
        'buy_mode', 'buy_value', 'sell_mode', 'sell_value',
        'is_enabled', 'freshness_seconds', 'sort',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'buy_value' => 'float',
            'sell_value' => 'float',
            'is_enabled' => 'boolean',
            'freshness_seconds' => 'integer',
            'sort' => 'integer',
            'buy_mode' => SpreadMode::class,
            'sell_mode' => SpreadMode::class,
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function toPair(): SpreadPair
    {
        return new SpreadPair(
            new Spread($this->buy_mode, $this->buy_value),
            new Spread($this->sell_mode, $this->sell_value),
        );
    }
}
