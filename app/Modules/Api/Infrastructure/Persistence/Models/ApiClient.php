<?php

declare(strict_types=1);

namespace App\Modules\Api\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Client e API — saheb e kelid e HMAC.
 *
 * `secret_encrypted` tu `$hidden` ast: in model hich vaght nabayad tu
 * payload/JSON biad، vali yek ghofl e dovvom zarar nadare.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $price_group_id
 * @property string $name
 * @property string $client_id
 * @property string $secret_encrypted
 * @property bool $is_active
 */
class ApiClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'price_group_id',
        'name',
        'website_url',
        'client_id',
        'secret_encrypted',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(ApiPriceGroup::class, 'price_group_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApiPlan::class, 'plan_id');
    }
}
