<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTopupPayment extends Model
{
    protected $fillable = ['user_id', 'amount_minor', 'request_key', 'track_id', 'status', 'gateway_reference', 'verified_at', 'verify_data'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'verified_at' => 'immutable_datetime',
            'verify_data' => 'array',
        ];
    }
}
