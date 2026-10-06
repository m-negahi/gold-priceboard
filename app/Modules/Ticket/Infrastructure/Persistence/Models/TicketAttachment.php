<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peyvast e ticket (P7-02) — file ru disk e private e `tickets`.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $message_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property string $sha256
 * @property CarbonImmutable|null $created_at
 * @property-read Ticket|null $ticket
 */
class TicketAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'message_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'sha256',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
