<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yek pele az zanjire ye olaviat e yek symbol (P5-07).
 *
 * @property int $id
 * @property string $symbol
 * @property int $provider_id
 * @property int $position 0 = aval
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SymbolProviderPriority extends Model
{
    protected $fillable = ['symbol', 'provider_id', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
