<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Common\Helpers\StringHelper;
use App\Modules\Common\Queries\BaseQuery;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketAttachment;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketEvent;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketMessage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\URL;

/**
 * Khundan e ticket ha (P7-01) — inbox e admin، lista/safhe ye karbar.
 *
 * 🔴 Masir e karbar FAGHAT az `findForOwnerOrFail` (filter e `user_id` tu
 * HAMUN query) — ticket e digari 404 ast، na 403 (vojud ro lo nemide).
 *
 * @extends BaseQuery<Ticket>
 */
final readonly class TicketQuery extends BaseQuery
{
    public const PER_PAGE = 25;

    /** Label e Farsi e noe e event e audit. */
    private const EVENT_LABELS = [
        'created' => 'ثبت تیکت',
        'status' => 'تغییر وضعیت',
        'decision' => 'تصمیم',
        'verification' => 'استعلام خودکار',
    ];

    /**
     * Label e kelid haye `meta` (form e sakhtar-dar e darkhast). Kelid e
     * nashenas ba hamun esm e kham neshun dade mishe — data gom nemishe.
     */
    private const META_LABELS = [
        'registered_name' => 'نام ثبتی',
        'company_national_id' => 'شناسه ملی شرکت',
        'economic_code' => 'کد اقتصادی',
        'postal_code' => 'کد پستی',
        'address' => 'نشانی',
        'phone' => 'تلفن ثابت',
        'representative_national_code' => 'کد ملی نماینده',
        'representative_birth_date' => 'تاریخ تولد نماینده',
    ];

    protected function model(): string
    {
        return Ticket::class;
    }

    /**
     * @param  array{q?: ?string, type?: ?string, status?: ?string}  $filters  ghablan validate shode
     */
    public function adminInbox(array $filters, int $page = 1): LengthAwarePaginator
    {
        $builder = $this->query()
            ->with('user:id,name,phone')
            ->withCount('messages');

        foreach (['type', 'status'] as $column) {
            if (($filters[$column] ?? '') !== '') {
                $builder->where($column, $filters[$column]);
            }
        }

        $q = trim((string) StringHelper::numbersToEnglish((string) ($filters['q'] ?? '')));

        if ($q !== '') {
            $phone = PhoneHelper::normalize($q);
            $builder->where(function ($w) use ($q, $phone): void {
                $w->where('subject', 'like', '%'.$q.'%');

                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }

                if ($phone !== null) {
                    $w->orWhereHas('user', static fn ($u) => $u->where('phone', $phone));
                }
            });
        }

        return $builder
            // Montazer-e-admin ha aval، ba'd jadid-tarin faaliat.
            ->orderByRaw("case when status in ('open', 'in_review') then 0 else 1 end")
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->withQueryString()
            ->through(fn (Ticket $ticket): array => $this->row($ticket));
    }

    /** @return list<array<string, mixed>> */
    public function listForOwner(int $userId): array
    {
        return $this->query()
            ->where('user_id', $userId)
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Ticket $ticket): array => $this->row($ticket))
            ->values()
            ->all();
    }

    /** Omr e link e download — kutah، chun safhe har bar link e taze misaze. */
    private const LINK_MINUTES = 30;

    /**
     * Peyvast baraye download: admin har peyvast؛ baghi faghat peyvast e
     * ticket e KHODESHUN (filter tu HAMUN query — 404 baraye digari).
     */
    public function attachmentForViewerOrFail(int $attachmentId, int $viewerId, bool $isAdmin): TicketAttachment
    {
        $query = TicketAttachment::query()->whereKey($attachmentId);

        if (! $isAdmin) {
            $query->whereHas('ticket', static fn ($t) => $t->where('user_id', $viewerId));
        }

        /** @var TicketAttachment */
        return $query->firstOrFail();
    }

    public function findForOwnerOrFail(int $ticketId, int $userId): Ticket
    {
        /** @var Ticket */
        return $this->query()->where('user_id', $userId)->findOrFail($ticketId);
    }

    /**
     * View-model e safhe ye ticket. `$staff` = namayesh e admin (esm e
     * admin ha + audit + profile e karbar)؛ baraye karbar admin «پشتیبانی»
     * ast va audit penhan.
     *
     * @return array<string, mixed>
     */
    public function detail(Ticket $ticket, bool $staff): array
    {
        $ticket->loadMissing(['user', 'messages.author:id,name,phone', 'attachments']);

        $expires = CarbonImmutable::now()->addMinutes(self::LINK_MINUTES);
        $files = $ticket->attachments->sortBy('id')->groupBy('message_id');

        $messages = $ticket->messages->sortBy('id')->values()->map(static fn (TicketMessage $m): array => [
            'id' => $m->id,
            'attachments' => ($files->get($m->id) ?? collect())
                ->map(static fn (TicketAttachment $a): array => self::attachment($a, $expires))
                ->values()
                ->all(),
            'body' => $m->body,
            'staff' => $m->is_staff,
            'author' => match (true) {
                $m->is_staff && ! $staff => 'پشتیبانی',
                $m->is_staff => 'پشتیبانی — '.($m->author?->name ?? PhoneHelper::toLocal((string) $m->author?->phone) ?? 'ادمین'),
                ! $staff => 'شما',
                default => $m->author?->name ?? PhoneHelper::toLocal((string) $m->author?->phone) ?? 'کاربر',
            },
            'at' => Jalali::dateTime($m->created_at),
        ])->all();

        $user = $ticket->user;

        return [
            'ticket' => [
                ...$this->row($ticket),
                'metaRows' => $this->metaRows($ticket->meta ?? []),
                'createdAt' => Jalali::dateTime($ticket->created_at),
                'canReply' => $ticket->status !== TicketStatus::Closed,
            ],
            'messages' => $messages,
            'events' => $staff ? $this->events($ticket) : [],
            'owner' => $staff && $user !== null ? [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => PhoneHelper::toLocal($user->phone) ?? $user->phone,
                'level' => $user->level,
                'customerType' => $user->customer_type,
            ] : null,
        ];
    }

    /** @return array{id: int, name: string, size: string, mime: string, url: string} */
    private static function attachment(TicketAttachment $a, CarbonImmutable $expires): array
    {
        $kb = $a->size_bytes / 1024;

        return [
            'id' => $a->id,
            'name' => $a->original_name,
            'size' => $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : max(1, (int) round($kb)).' KB',
            'mime' => $a->mime,
            'url' => URL::temporarySignedRoute('tickets.attachments.download', $expires, ['attachment' => $a->id], absolute: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{key: string, label: string, value: string}>
     */
    private function metaRows(array $meta): array
    {
        $rows = [];

        foreach ($meta as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => self::META_LABELS[$key] ?? (string) $key,
                'value' => is_bool($value) ? ($value ? 'بله' : 'خیر') : (string) $value,
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function events(Ticket $ticket): array
    {
        return $ticket->events()
            ->with('actor:id,name,phone')
            ->orderBy('id')
            ->get()
            ->map(static fn (TicketEvent $e): array => [
                'id' => $e->id,
                'kind' => $e->kind,
                'label' => self::EVENT_LABELS[$e->kind] ?? $e->kind,
                'from' => $e->from_status === null ? null : TicketStatus::from($e->from_status)->label(),
                'to' => $e->to_status === null ? null : TicketStatus::from($e->to_status)->label(),
                'note' => $e->note,
                'actor' => $e->actor === null ? 'سیستم' : ($e->actor->name ?? PhoneHelper::toLocal($e->actor->phone) ?? '#'.$e->actor->id),
                'at' => Jalali::dateTime($e->created_at),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function row(Ticket $ticket): array
    {
        $user = $ticket->relationLoaded('user') ? $ticket->user : null;

        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'type' => $ticket->type,
            'status' => $ticket->status,
            'userId' => $ticket->user_id,
            'userName' => $user?->name,
            'userPhone' => $user === null ? null : (PhoneHelper::toLocal($user->phone) ?? $user->phone),
            'messagesCount' => (int) ($ticket->getAttribute('messages_count') ?? 0),
            'lastActivityAt' => Jalali::dateTime($ticket->last_activity_at),
        ];
    }
}
