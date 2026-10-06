<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;

/**
 * Taghir e vaziat e OMUMI (baz/dar hal e barresi/baste).
 *
 * 🔴 `approved`/`rejected` inja RAD mishan: tasmim faghat az masir e
 * ta'eed/rad (P7-06) ke level e karbar va etela'at e factor ro ham ba
 * hamun transaction sabt mikone. Vagarna ticket «تأیید شد» neshun mide va
 * karbar hanuz `basic` ast.
 */
final readonly class ChangeTicketStatusAction
{
    public function __construct(private TransitionTicketAction $transition) {}

    public function execute(Ticket $ticket, TicketStatus $to, ?int $actorId, ?string $note = null): Ticket
    {
        if ($to->isDecision()) {
            throw TicketException::decisionRoute();
        }

        return $this->transition->execute($ticket, $to, $actorId, $note);
    }
}
