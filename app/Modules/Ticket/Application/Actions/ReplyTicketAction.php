<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Actions;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Javab ru ticket (karbar ya poshtibani). Ticket e `closed` javab nemigire
 * — aval bayad dobare baz she (ta taghir e vaziat audit dashte bashe).
 *
 * ⚠️ Malekiat inja check NEMISHE: controller ticket ro az `TicketQuery`
 * e mahdud be saheb (`findForOwner`) migire — IDOR unja baste mishe.
 */
final readonly class ReplyTicketAction
{
    public function execute(Ticket $ticket, int $authorId, string $body, bool $staff = false): TicketMessage
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw TicketException::closed();
        }

        return DB::transaction(static function () use ($ticket, $authorId, $body, $staff): TicketMessage {
            /** @var TicketMessage $message */
            $message = $ticket->messages()->create([
                'author_id' => $authorId,
                'is_staff' => $staff,
                'body' => $body,
            ]);

            $ticket->forceFill(['last_activity_at' => CarbonImmutable::now()])->save();

            return $message;
        });
    }
}
