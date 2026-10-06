<?php

declare(strict_types=1);

namespace App\Modules\Ticket\Http\Controllers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Http\Support\AttachmentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ticket haye KHOD e karbar (P7-01).
 *
 * 🔴 Har ticket az `findForOwnerOrFail` (filter e `user_id` e karbar e
 * login-shode) — ticket e digari 404. Noe e ticket az in masir HAMISHE
 * `support` ast: `kyc_upgrade` faghat az onboarding (P7-03) ba form e
 * sakhtar-dar sakhte mishe.
 */
final readonly class AccountTicketController
{
    public function __construct(
        private TicketQuery $tickets,
        private OpenTicketAction $open,
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
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            ...AttachmentRules::rules(),
        ], AttachmentRules::messages());

        $ticket = $this->open->execute($this->userId($request), TicketType::Support, $data['subject'], $data['body'], files: $request->file('files', []));

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
