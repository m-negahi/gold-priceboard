<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketEvent;
use App\Modules\Ticket\Jobs\SendTicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hasteye MOSHTARAK e har taghir e vaziat: lock e radif، check e gozar،
 * update، event e audit. Masraf-konande ha: `ChangeTicketStatusAction`
 * (vaziat e omumi) va masir e ta'eed/rad (P7-06).
 *
 * 🔴 Radif ba `lockForUpdate` DOBARE khunde mishe: do admin ke hamzaman
 * «تأیید» va «رد» mizanan nabayad har do ru vaziat e `open` e ghadimi
 * tasmim begiran — dovvomi vaziat e jadid ro mibine va gozar rad mishe.
 */
final readonly class TransitionTicketAction
{
    public function execute(Ticket $ticket, TicketStatus $to, ?int $actorId, ?string $note = null, string $kind = 'status'): Ticket
    {
        DB::transaction(static function () use ($ticket, $to, $actorId, $note, $kind): void {
            /** @var Ticket $locked */
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                throw TicketException::sameStatus();
            }

            if (! $from->canMoveTo($to)) {
                throw TicketException::transition($from->label(), $to->label());
            }

            $locked->forceFill([
                'status' => $to,
                'last_activity_at' => CarbonImmutable::now(),
            ])->save();

            $event = TicketEvent::query()->create([
                'ticket_id' => $locked->id,
                'actor_id' => $actorId,
                'kind' => $kind,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);
            DB::table('ticket_notifications')->insert(['event_id' => $event->id]);
            SendTicketStatus::dispatch($event->id)->afterCommit();
        });

        return $ticket->refresh();
    }
}
