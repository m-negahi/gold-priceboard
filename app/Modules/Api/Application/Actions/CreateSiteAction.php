<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CreateSiteAction
{
    public function __construct(
        private ApiClientAction $clients,
        private OpenTicketAction $tickets,
    ) {}

    /** @return array{client: ApiClient, secret: string, ticket_id: int} */
    public function execute(int $userId, string $name, string $websiteUrl): array
    {
        $host = parse_url($websiteUrl, PHP_URL_HOST);
        $scheme = parse_url($websiteUrl, PHP_URL_SCHEME);
        if (! is_string($host) || $host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('adres e site na-motabar ast');
        }

        return DB::transaction(function () use ($userId, $name, $websiteUrl, $host): array {
            $created = $this->clients->create($userId, $name, status: 'pending');
            $created['client']->forceFill(['website_url' => $websiteUrl])->save();
            $ticket = $this->tickets->execute(
                $userId,
                TicketType::SiteApproval,
                'درخواست تأیید سایت '.$host,
                'سایت '.$websiteUrl.' برای بررسی نماد اعتماد ثبت شد.',
                ['api_client_id' => $created['client']->id, 'domain' => strtolower($host)],
            );

            return [...$created, 'ticket_id' => $ticket->id];
        });
    }
}
