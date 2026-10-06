<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit e ticket — faghat INSERT (`UPDATED_AT = null`). `kind`:
 * `created` | `status` | `decision` | `verification`.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $actor_id
 * @property string $kind
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $actor
 */
class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'actor_id',
        'kind',
        'from_status',
        'to_status',
        'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
