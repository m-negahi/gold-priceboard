<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Auth\Application\Queries\UserQuery;
use App\Modules\Auth\Http\Support\UserProfileRules;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Actions\DecideKycAction;
use App\Modules\Kyc\Application\Actions\QueueIdentityChecksAction;
use App\Modules\Kyc\Application\Queries\VerificationQuery;
use App\Modules\Ticket\Application\Actions\ChangeTicketStatusAction;
use App\Modules\Ticket\Application\Actions\CreateTicketRequestAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Http\Support\AttachmentRules;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ticket haye panel (P7-01) — inbox + safhe ye ticket. Controller e NAZOK:
 * filter ha validate (meghdar e nashenas khata، na nadide gereftan)، kol e
 * khundan az `TicketQuery`، neveshtan az Action ha.
 */
final readonly class TicketController
{
    public function __construct(
        private UserQuery $users,
        private CreateTicketRequestAction $createTicket,
        private QueueIdentityChecksAction $checks,
        private VerificationQuery $verification,
        private DecideKycAction $decideKyc,
        private TicketQuery $tickets,
        private ReplyTicketAction $reply,
        private ChangeTicketStatusAction $changeStatus,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', Rule::in(TicketType::values())],
            'status' => ['nullable', Rule::in(TicketStatus::values())],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return view('panel::tickets.index', [
            'tickets' => $this->tickets->adminInbox($filters, (int) ($filters['page'] ?? 1)),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'types' => TicketType::labels(),
            'statuses' => TicketStatus::labels(),
        ]);
    }

    public function show(int $ticket): View
    {
        /** @var Ticket $model */
        $model = $this->tickets->findOrFail($ticket);

        return view('panel::tickets.show', [
            ...$this->tickets->detail($model, staff: true),
            'verificationRuns' => $this->verification->forTicket($ticket),
            'canDecideKyc' => $model->type === TicketType::KycUpgrade && in_array($model->status, [TicketStatus::Open, TicketStatus::InReview], true),
            // Gozine haye form e vaziat e omumi: faghat gozar haye MOJAZ e gheyr-tasmim.
            'statusTargets' => collect($model->status->allowedTargets())
                ->reject(static fn (TicketStatus $s): bool => $s->isDecision())
                ->mapWithKeys(static fn (TicketStatus $s): array => [$s->value => $s->label()])
                ->all(),
        ]);
    }

    public function create(): View
    {
        return view('panel::tickets.create', ['ownerOptions' => $this->users->ticketOwnerOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(['support', 'kyc_upgrade'])],
            'subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000'],
            ...AttachmentRules::rules($request->input('type') === 'kyc_upgrade'),
        ], AttachmentRules::messages());
        $owner = $this->users->findOrFail((int) $data['user_id']);
        $profile = [];
        if ($data['type'] === 'kyc_upgrade') {
            $request->merge(['phone' => $owner->phone, 'name' => $request->input('name') ?: $owner->name]);
            $profile = UserProfileRules::validate($request, $owner->id);
        }
        try {
            $ticket = $this->createTicket->execute($owner, TicketType::from($data['type']), $data['subject'], $data['body'], $profile, $request->file('files', []), $request->user());
        } catch (TicketException $e) {
            return back()->withErrors(['subject' => $e->getMessage()])->withInput();
        }

        return redirect()->route('panel.tickets.show', $ticket->id);
    }

    public function rerun(Request $request, int $ticket): RedirectResponse
    {
        $model = $this->tickets->findOrFail($ticket);
        abort_unless($model->type === TicketType::KycUpgrade, 422);
        $owner = $this->users->findOrFail($model->user_id);
        $this->checks->execute($owner->id, [...($model->meta ?? []), 'mobile' => $owner->phone], 'rerun:'.Str::uuid(), $model->id, $request->user()->id);

        return back()->with('toast', ['kind' => 'success', 'title' => 'استعلام در صف قرار گرفت', 'message' => 'نتیجه پس از اجرا نمایش داده می‌شود.']);
    }

    public function decide(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500']]);
        try {
            $this->decideKyc->execute($this->tickets->findOrFail($ticket), $request->user(), $data['decision'] === 'approve', (string) ($data['reason'] ?? ''));
        } catch (TicketException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('toast', ['kind' => 'success', 'title' => 'تصمیم ثبت شد', 'message' => 'نتیجه برای کاربر قابل مشاهده است.']);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], ...AttachmentRules::rules()], AttachmentRules::messages());

        /** @var Ticket $model */
        $model = $this->tickets->findOrFail($ticket);
        /** @var User $admin */
        $admin = $request->user();

        try {
            $this->reply->execute($model, $admin->id, $data['body'], staff: true, files: $request->file('files', []));
        } catch (TicketException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->route('panel.tickets.show', $model->id)->with('toast', [
            'kind' => 'success', 'title' => 'پاسخ ثبت شد', 'message' => 'پاسخ برای کاربر قابل مشاهده است.',
        ]);
    }

    public function status(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(TicketStatus::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Ticket $model */
        $model = $this->tickets->findOrFail($ticket);
        /** @var User $admin */
        $admin = $request->user();

        try {
            $this->changeStatus->execute($model, TicketStatus::from($data['status']), $admin->id, $data['note'] ?? null);
        } catch (TicketException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('panel.tickets.show', $model->id)->with('toast', [
            'kind' => 'success', 'title' => 'وضعیت تغییر کرد', 'message' => TicketStatus::from($data['status'])->label(),
        ]);
    }
}
