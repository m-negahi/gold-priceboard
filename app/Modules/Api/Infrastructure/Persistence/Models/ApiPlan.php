<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plan e API (P4-09) — masalan «1200 call dar rooz».
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $price_minor
 * @property int $calls_per_day
 * @property int $cost_per_call_minor
 * @property bool $is_active
 */
class ApiPlan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'price_minor',
        'calls_per_day',
        'cost_per_call_minor',
        'is_active',
        'sort',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'calls_per_day' => 'integer',
            'cost_per_call_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
