<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Jobs;

use App\Modules\Auth\Domain\Ports\SmsPort;
use App\Modules\Common\Enums\QueueName;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class SendTicketStatus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function handle(SmsPort $sms): void
    {
        DB::transaction(function () use ($sms): void {
            $notice = DB::table('ticket_notifications')->where('event_id', $this->eventId)->lockForUpdate()->first();
            if ($notice === null || $notice->sent_at !== null) {
                return;
            }
            $event = TicketEvent::query()->findOrFail($this->eventId);
            $phone = DB::table('tickets')->join('users', 'users.id', '=', 'tickets.user_id')->where('tickets.id', $event->ticket_id)->value('users.phone');
            if ($phone !== null) {
                $sms->sendTicketStatus($phone, $event->ticket_id, (string) $event->to_status);
                DB::table('ticket_notifications')->where('id', $notice->id)->update(['sent_at' => now()]);
            }
        });
    }
}
