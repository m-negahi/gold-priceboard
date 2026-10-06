<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Yek radif e ledger — APPEND-ONLY.
 *
 * 🔴 Hich `update`، hich `delete` (ghanun e proje). `UPDATED_AT = null`
 * chun jadval aslan soton esh ro nadare. Eslah e eshtebah = yek radif e
 * `adjustment`/`refund` e jadid، na dast zadan be gozashte.
 *
 * @property int $id
 * @property int $wallet_id
 * @property string $direction
 * @property int $amount_minor
 * @property int $balance_after_minor
 * @property string $reason
 * @property string $reference
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'direction',
        'amount_minor',
        'balance_after_minor',
        'reason',
        'reference',
        'meta',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
