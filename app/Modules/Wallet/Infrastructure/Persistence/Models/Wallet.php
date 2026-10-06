<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kif e pul.
 *
 * 🔴 `balance_minor` CACHE ast — manba' e haghighat ledger
 * (`wallet_transactions`) ast. Har neveshtan FAGHAT az `WalletAction` (ba
 * lock e radif) rad mishe؛ `balance_minor` hich vaght fillable nist.
 *
 * @property int $id
 * @property int $owner_id
 * @property int $balance_minor
 * @property string $currency
 * @property bool $is_blocked
 */
class Wallet extends Model
{
    protected $fillable = [
        'owner_id',
        'currency',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Driver e Postgres bigint ro STRING mide — bedun e cast har
            // `===` saket mishekane (dars e P1-07).
            'balance_minor' => 'integer',
            'is_blocked' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
