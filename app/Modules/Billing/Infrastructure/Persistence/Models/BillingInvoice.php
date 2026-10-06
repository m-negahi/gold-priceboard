<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    protected $fillable = ['user_id', 'wallet_topup_payment_id', 'site_subscription_id', 'kind', 'number', 'amount_minor', 'buyer_snapshot', 'lines_snapshot', 'issued_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'buyer_snapshot' => 'array',
            'lines_snapshot' => 'array',
            'issued_at' => 'immutable_datetime',
        ];
    }
}
