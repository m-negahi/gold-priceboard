<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Gheymat e dasti ye yek symbol (fallback).
 *
 * @property int $id
 * @property string $symbol
 * @property int $minor
 * @property int $scale
 * @property string $quote_currency
 * @property string|null $source_hint
 * @property string|null $updated_by
 * @property CarbonImmutable|null $updated_at
 */
class ManualPrice extends Model
{
    protected $fillable = [
        'symbol',
        'minor',
        'scale',
        'quote_currency',
        'source_hint',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // bigint ro Postgres STRING mide — bedun e cast har `===` saket
            // mishekane (dars e P1-07).
            'minor' => 'integer',
            'scale' => 'integer',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
