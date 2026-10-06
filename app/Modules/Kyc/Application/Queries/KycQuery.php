<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Queries;

use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;

/**
 * Khundan e vaziat e KYC e yek karbar (P7-03).
 */
final readonly class KycQuery
{
    /** Darkhast e ertegha ye BAZ (montazer e tasmim) — har karbar hade aksar yeki. */
    public function pendingTicketFor(int $userId): ?Ticket
    {
        /** @var Ticket|null */
        return Ticket::query()
            ->where('user_id', $userId)
            ->where('type', TicketType::KycUpgrade->value)
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InReview->value])
            ->orderByDesc('id')
            ->first();
    }
}
