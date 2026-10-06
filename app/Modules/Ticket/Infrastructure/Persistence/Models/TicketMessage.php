<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payam e yek ticket. `is_staff` = javab e poshtibani (admin) — karbar esm
 * e admin ro nemibine، faghat «پشتیبانی».
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property bool $is_staff
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $author
 */
class TicketMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'author_id',
        'is_staff',
        'body',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
