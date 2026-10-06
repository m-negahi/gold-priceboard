<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Ticket\Application\Actions\TransitionTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DecideSiteAction
{
    public function __construct(
        private IdentityVerificationPort $identity,
        private TransitionTicketAction $transition,
    ) {}

    /** @return array<string, mixed> */
    public function checkEnamad(int $ticketId): array
    {
        /** @var Ticket $ticket */
        $ticket = Ticket::query()->findOrFail($ticketId);
        $client = $this->clientFor($ticket);
        $domain = $this->domain($client->website_url);
        $result = $this->identity->verify('enamad', ['website' => $domain]);
        $reported = strtolower(trim((string) ($result->data['domain'] ?? '')));
        $domainMismatch = $result->status === 'passed' && $reported !== $domain;
        $status = $domainMismatch ? 'failed' : $result->status;
        $check = [
            'status' => $status,
            'message' => $domainMismatch ? 'دامنه نماد با سایت ثبت‌شده یکسان نیست.' : $result->message,
            'data' => $result->data,
            'checked_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        $ticket->forceFill(['meta' => [...($ticket->meta ?? []), 'enamad_check' => $check]])->save();

        return $check;
    }

    public function decide(int $ticketId, int $adminId, bool $approve, string $note): ApiClient
    {
        return DB::transaction(function () use ($ticketId, $adminId, $approve, $note): ApiClient {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticketId);
            $client = $this->clientFor($ticket);
            if (! $ticket->status->isPending() || $client->status !== 'pending') {
                throw new InvalidArgumentException('darkhast ghablan tasmim gerefte shode ast');
            }
            if ($approve) {
                $check = $ticket->meta['enamad_check'] ?? null;
                if (! is_array($check) || ($check['status'] ?? null) !== 'passed'
                    || ! isset($check['checked_at'])
                    || CarbonImmutable::parse($check['checked_at'])->lt(CarbonImmutable::now()->subDay())
                    || strtolower((string) ($check['data']['domain'] ?? '')) !== $this->domain($client->website_url)) {
                    throw new InvalidArgumentException('estelam e motabar e enamad lazem ast');
                }
            } elseif (trim($note) === '') {
                throw new InvalidArgumentException('dalil e rad lazem ast');
            }

            $this->transition->execute($ticket, $approve ? TicketStatus::Approved : TicketStatus::Rejected, $adminId, $note, 'site_decision');
            $client->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_at' => $approve ? CarbonImmutable::now() : null,
                'approved_by' => $approve ? $adminId : null,
            ])->save();

            return $client;
        });
    }

    private function clientFor(Ticket $ticket): ApiClient
    {
        if ($ticket->type !== TicketType::SiteApproval || ! isset($ticket->meta['api_client_id'])) {
            throw new InvalidArgumentException('ticket e site nist');
        }

        return ApiClient::query()
            ->where('user_id', $ticket->user_id)
            ->findOrFail((int) $ticket->meta['api_client_id']);
    }

    private function domain(?string $websiteUrl): string
    {
        $host = parse_url((string) $websiteUrl, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('domain e site na-motabar ast');
        }

        return strtolower($host);
    }
}
