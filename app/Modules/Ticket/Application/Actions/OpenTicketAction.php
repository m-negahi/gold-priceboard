<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Baz kardan e ticket (P7-01): ticket + payam e aval + event e `created`
 * dar YEK transaction — ticket e bedun e payam ya bedun e audit nemimune.
 */
final readonly class OpenTicketAction
{
    /** @param array<string, mixed>|null $meta */
    public function execute(int $userId, TicketType $type, string $subject, string $body, ?array $meta = null): Ticket
    {
        return DB::transaction(static function () use ($userId, $type, $subject, $body, $meta): Ticket {
            $ticket = new Ticket([
                'user_id' => $userId,
                'type' => $type,
                'subject' => $subject,
                'meta' => $meta,
            ]);
            $ticket->forceFill(['last_activity_at' => CarbonImmutable::now()])->save();

            $ticket->messages()->create([
                'author_id' => $userId,
                'is_staff' => false,
                'body' => $body,
            ]);

            TicketEvent::query()->create([
                'ticket_id' => $ticket->id,
                'actor_id' => $userId,
                'kind' => 'created',
                'to_status' => TicketStatus::Open->value,
            ]);

            return $ticket;
        });
    }
}
