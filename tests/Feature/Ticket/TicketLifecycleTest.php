<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\ChangeTicketStatusAction;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Domain\Exceptions\TicketException;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P7-01 — charkhe ye ticket: baz shodan (payam e aval + event)، javab،
 * taghir e vaziat ba audit، va gozar haye GHEYR-mojaz.
 */
final class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function openFor(User $user, TicketType $type = TicketType::Support): Ticket
    {
        return app(OpenTicketAction::class)->execute($user->id, $type, 'سوال درباره API', 'سلام، کلید من کار نمی‌کند.');
    }

    public function test_opening_a_ticket_writes_the_first_message_and_a_created_event(): void
    {
        $user = User::factory()->create();

        $ticket = $this->openFor($user);

        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame(TicketType::Support, $ticket->type);
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertNotNull($ticket->last_activity_at);

        $this->assertSame(1, $ticket->messages()->count());
        $message = $ticket->messages()->first();
        $this->assertSame('سلام، کلید من کار نمی‌کند.', $message->body);
        $this->assertFalse($message->is_staff);
        $this->assertSame($user->id, $message->author_id);

        $events = $ticket->events()->get();
        $this->assertCount(1, $events);
        $this->assertSame('created', $events[0]->kind);
        $this->assertSame('open', $events[0]->to_status);
        $this->assertSame($user->id, $events[0]->actor_id);
    }

    public function test_meta_is_kept_as_structured_data(): void
    {
        $user = User::factory()->create();

        $ticket = app(OpenTicketAction::class)->execute($user->id, TicketType::KycUpgrade, 'ارتقا', 'متن', meta: ['postal_code' => '1234567890']);

        $this->assertSame(['postal_code' => '1234567890'], $ticket->fresh()->meta);
    }

    public function test_a_staff_reply_is_marked_and_bumps_activity(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = $this->openFor($user);
        $ticket->forceFill(['last_activity_at' => now()->subDay()])->save();

        app(ReplyTicketAction::class)->execute($ticket, $admin->id, 'بررسی شد.', staff: true);

        $last = $ticket->messages()->orderByDesc('id')->first();
        $this->assertTrue($last->is_staff);
        $this->assertSame($admin->id, $last->author_id);
        $this->assertTrue($ticket->fresh()->last_activity_at->isAfter(now()->subMinute()));
    }

    public function test_a_closed_ticket_takes_no_replies(): void
    {
        $user = User::factory()->create();
        $ticket = $this->openFor($user);
        app(ChangeTicketStatusAction::class)->execute($ticket, TicketStatus::Closed, $user->id);

        $this->expectException(TicketException::class);
        app(ReplyTicketAction::class)->execute($ticket->fresh(), $user->id, 'هنوز هستید؟');
    }

    public function test_a_status_change_is_audited_with_actor_from_to_and_note(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = $this->openFor($user);

        app(ChangeTicketStatusAction::class)->execute($ticket, TicketStatus::InReview, $admin->id, 'در صف بررسی');

        $this->assertSame(TicketStatus::InReview, $ticket->fresh()->status);
        $event = $ticket->events()->orderByDesc('id')->first();
        $this->assertSame('status', $event->kind);
        $this->assertSame('open', $event->from_status);
        $this->assertSame('in_review', $event->to_status);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('در صف بررسی', $event->note);
    }

    public function test_the_same_status_is_a_no_op_without_an_event(): void
    {
        $user = User::factory()->create();
        $ticket = $this->openFor($user);

        $this->expectException(TicketException::class);
        app(ChangeTicketStatusAction::class)->execute($ticket, TicketStatus::Open, $user->id);
    }

    public function test_reopening_a_closed_ticket_is_allowed(): void
    {
        $user = User::factory()->create();
        $ticket = $this->openFor($user);
        $status = app(ChangeTicketStatusAction::class);
        $status->execute($ticket, TicketStatus::Closed, $user->id);

        $status->execute($ticket->fresh(), TicketStatus::Open, $user->id);

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertSame(3, $ticket->events()->count());
    }

    /** @return array<string, array{0: TicketStatus}> */
    public static function decisions(): array
    {
        return ['approved' => [TicketStatus::Approved], 'rejected' => [TicketStatus::Rejected]];
    }

    /**
     * 🔴 Tasmim (ta'eed/rad) az masir e omumi NA — vagarna level e karbar
     * bedun e masir e ta'eed (P7-06) «approved» neshun dade mishe.
     */
    #[DataProvider('decisions')]
    public function test_decisions_cannot_go_through_the_generic_status_change(TicketStatus $decision): void
    {
        $user = User::factory()->create();
        $ticket = $this->openFor($user, TicketType::KycUpgrade);

        try {
            app(ChangeTicketStatusAction::class)->execute($ticket, $decision, $user->id);
            $this->fail('tasmim az masir e omumi rad nashod.');
        } catch (TicketException) {
            $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
            $this->assertSame(1, $ticket->events()->count());
        }
    }

    public function test_the_transition_table(): void
    {
        $this->assertTrue(TicketStatus::Open->canMoveTo(TicketStatus::InReview));
        $this->assertTrue(TicketStatus::Closed->canMoveTo(TicketStatus::Open));
        $this->assertFalse(TicketStatus::Closed->canMoveTo(TicketStatus::InReview));
        $this->assertFalse(TicketStatus::Approved->canMoveTo(TicketStatus::Open));
        $this->assertFalse(TicketStatus::Rejected->canMoveTo(TicketStatus::Approved));
        $this->assertTrue(TicketStatus::Approved->canMoveTo(TicketStatus::Closed));
        $this->assertTrue(TicketType::KycUpgrade->needsDecision());
        $this->assertFalse(TicketType::Support->needsDecision());
    }

    public function test_an_invalid_transition_is_refused(): void
    {
        $user = User::factory()->create();
        $ticket = $this->openFor($user);
        $status = app(ChangeTicketStatusAction::class);
        $status->execute($ticket, TicketStatus::Closed, $user->id);

        $this->expectException(TicketException::class);
        $status->execute($ticket->fresh(), TicketStatus::InReview, $user->id);
    }
}
