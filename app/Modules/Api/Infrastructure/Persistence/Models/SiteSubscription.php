<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSubscription extends Model
{
    protected $fillable = [
        'api_client_id', 'api_plan_id', 'user_id', 'starts_at', 'ends_at',
        'calls_included', 'calls_per_day', 'price_minor', 'cost_per_call_minor',
        'purchase_reference',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'calls_included' => 'integer',
            'calls_per_day' => 'integer',
            'price_minor' => 'integer',
            'cost_per_call_minor' => 'integer',
            'calls_used' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApiPlan::class, 'api_plan_id');
    }
}
