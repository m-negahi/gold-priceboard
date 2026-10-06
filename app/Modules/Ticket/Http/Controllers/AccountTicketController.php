<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Http\Controllers;

use App\Modules\Auth\Http\Support\UserProfileRules;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\CreateTicketRequestAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Http\Support\AttachmentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ticket haye KHOD e karbar (P7-01).
 *
 * 🔴 Har ticket az `findForOwnerOrFail` (filter e `user_id` e karbar e
 * login-shode) — ticket e digari 404. Darkhast e hoviat ba form e
 * sakhtar-dar va madarek e ejbari sakhte mishe; field e ghadimi `type`
 * az request e karbar masraf nemishe.
 */
final readonly class AccountTicketController
{
    public function __construct(
        private CreateTicketRequestAction $createTicket,
        private TicketQuery $tickets,
        private ReplyTicketAction $reply,
    ) {}

    public function index(Request $request): View
    {
        return view('ticket::account.index', [
            'tickets' => $this->tickets->listForOwner($this->userId($request)),
        ]);
    }

    public function create(): View
    {
        return view('ticket::account.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'request_type' => ['sometimes', Rule::in(['support', 'kyc_upgrade'])],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            ...AttachmentRules::rules($request->input('request_type') === 'kyc_upgrade'),
        ], AttachmentRules::messages());

        $profile = [];
        $type = TicketType::from($data['request_type'] ?? 'support');
        if ($type === TicketType::KycUpgrade) {
            $request->merge(['phone' => $request->user()->phone, 'name' => $request->input('name') ?: $request->user()->name]);
            $profile = UserProfileRules::validate($request, $this->userId($request));
        }
        try {
            $ticket = $this->createTicket->execute($request->user(), $type, $data['subject'], $data['body'], $profile, $request->file('files', []));
        } catch (TicketException $e) {
            return back()->withErrors(['subject' => $e->getMessage()])->withInput();
        }

        return redirect()->route('account.tickets.show', $ticket->id)->with('status', 'تیکت ثبت شد؛ پاسخ پشتیبانی همین‌جا نمایش داده می‌شود.');
    }

    public function show(Request $request, int $ticket): View
    {
        $model = $this->tickets->findForOwnerOrFail($ticket, $this->userId($request));

        return view('ticket::account.show', $this->tickets->detail($model, staff: false));
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $model = $this->tickets->findForOwnerOrFail($ticket, $this->userId($request));
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], ...AttachmentRules::rules()], AttachmentRules::messages());

        try {
            $this->reply->execute($model, $this->userId($request), $data['body'], files: $request->file('files', []));
        } catch (TicketException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->route('account.tickets.show', $model->id)->with('status', 'پیام شما ثبت شد.');
    }

    private function userId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return $user->id;
    }
}
