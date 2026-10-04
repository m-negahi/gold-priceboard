<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\Enums\Side;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daftarche ye append-only — faghat vaghti gheymat VAGHE'AN avaz shode.
 *
 * ⚠️ HARGEZ `update`/`delete`. `$timestamps = false` chun `observed_at`
 * (zaman e tick e floor-shode) manba' e haghighat ast, na `created_at`.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $symbol
 * @property Side $side
 * @property QuoteCurrency $quote_currency
 * @property int $scale
 * @property int $raw_minor
 * @property CarbonImmutable|null $upstream_at
 * @property CarbonImmutable $observed_at
 */
class PriceChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider_id', 'symbol', 'side', 'quote_currency', 'scale',
        'raw_minor', 'upstream_at', 'observed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scale' => 'integer',
            'raw_minor' => 'integer',
            'side' => Side::class,
            'quote_currency' => QuoteCurrency::class,
            'upstream_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
