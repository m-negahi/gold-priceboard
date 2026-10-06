<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Controllers;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\ChangeTicketStatusAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Application\Queries\TicketQuery;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // Gozine haye form e vaziat e omumi: faghat gozar haye MOJAZ e gheyr-tasmim.
            'statusTargets' => collect($model->status->allowedTargets())
                ->reject(static fn (TicketStatus $s): bool => $s->isDecision())
                ->mapWithKeys(static fn (TicketStatus $s): array => [$s->value => $s->label()])
                ->all(),
        ]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        /** @var Ticket $model */
        $model = $this->tickets->findOrFail($ticket);
        /** @var User $admin */
        $admin = $request->user();

        try {
            $this->reply->execute($model, $admin->id, $data['body'], staff: true);
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
