<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Group e gheymat-gozari ye customer ha — hazine ye har call.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $cost_per_call_minor
 * @property bool $is_default
 */
class ApiPriceGroup extends Model
{
    protected $fillable = [
        'key',
        'name',
        'cost_per_call_minor',
        'is_default',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost_per_call_minor' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
