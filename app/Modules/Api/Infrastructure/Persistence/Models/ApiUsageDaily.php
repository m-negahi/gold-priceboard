<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Usage e ruzane ye yek client (P6-08) — rooz e TEHRAN.
 *
 * Faghat `RollupApiUsageAction` minevise (upsert e idempotent). `cost_minor`
 * az ledger e PG mohasebe mishe — bigint e rial، hargez float.
 *
 * @property int $id
 * @property int $api_client_id
 * @property string $day
 * @property int $calls
 * @property int $cost_minor
 */
class ApiUsageDaily extends Model
{
    protected $table = 'api_usage_daily';

    protected $fillable = [
        'api_client_id',
        'day',
        'calls',
        'cost_minor',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Driver e Postgres bigint ro STRING mide — bedun e cast `===` mishkane.
            'calls' => 'integer',
            'cost_minor' => 'integer',
        ];
    }
}
