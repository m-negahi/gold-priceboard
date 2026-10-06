<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Baz kardan e ticket (P7-01): ticket + payam e aval + event e `created`
 * dar YEK transaction — ticket e bedun e payam ya bedun e audit nemimune.
 */
final readonly class OpenTicketAction
{
    public function __construct(private StoreTicketAttachmentsAction $attachments) {}

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<int, UploadedFile>  $files  peyvast haye payam e aval (P7-02)
     */
    public function execute(int $userId, TicketType $type, string $subject, string $body, ?array $meta = null, array $files = [], ?int $actorId = null): Ticket
    {
        return DB::transaction(function () use ($userId, $type, $subject, $body, $meta, $files, $actorId): Ticket {
            $ticket = new Ticket([
                'user_id' => $userId,
                'type' => $type,
                'subject' => $subject,
                'meta' => $meta,
            ]);
            $ticket->forceFill(['last_activity_at' => CarbonImmutable::now()])->save();

            $message = $ticket->messages()->create([
                'author_id' => $actorId ?? $userId,
                'is_staff' => $actorId !== null,
                'body' => $body,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'actor_id' => $actorId ?? $userId,
                'kind' => 'created',
                'to_status' => TicketStatus::Open->value,
            ]);

            $this->attachments->execute($ticket, $message->id, $actorId ?? $userId, $files);

            return $ticket;
        });
    }
}
