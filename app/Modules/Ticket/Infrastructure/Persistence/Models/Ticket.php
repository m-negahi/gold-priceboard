<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ticket (P7-01) — har darkhast e karbar.
 *
 * 🔴 `status` FILLABLE NIST: vaziat faghat ba `ChangeTicketStatusAction`
 * (ya masir e ta'eed/rad) avaz mishe ta HAR taghir yek radif e audit
 * (`ticket_events`) dashte bashe.
 *
 * @property int $id
 * @property int $user_id
 * @property TicketType $type
 * @property TicketStatus $status
 * @property string $subject
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $last_activity_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
class Ticket extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'meta',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'meta' => 'array',
            'last_activity_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }
}
