<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Queries;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;

final readonly class SiteApprovalQuery
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $tickets = Ticket::query()->where('type', TicketType::SiteApproval->value)
            ->orderByDesc('id')->limit(200)->get();
        $clientIds = $tickets->map(static fn (Ticket $ticket): int => (int) ($ticket->meta['api_client_id'] ?? 0));
        $clients = ApiClient::query()->whereIn('id', $clientIds)->get()->keyBy('id');

        return $tickets->map(static function (Ticket $ticket) use ($clients): array {
            $client = $clients->get((int) ($ticket->meta['api_client_id'] ?? 0));

            return [
                'ticketId' => $ticket->id,
                'status' => $ticket->status->value,
                'userId' => $ticket->user_id,
                'name' => $client?->name ?? '—',
                'websiteUrl' => $client?->website_url,
                'clientStatus' => $client?->status,
                'enamad' => $ticket->meta['enamad_check'] ?? null,
            ];
        })->all();
    }
}
