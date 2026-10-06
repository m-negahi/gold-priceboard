<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Application\Actions\ReplyTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P7-01 — ticket haye KHOD e karbar. 🔴 IDOR: karbar ticket e digari ro na
 * mibine، na javab mide (404 — vojud ro ham lo nemide).
 */
final class AccountTicketPagesTest extends TestCase
{
    use RefreshDatabase;

    private function openTicket(User $user, string $subject): Ticket
    {
        return app(OpenTicketAction::class)->execute($user->id, TicketType::Support, $subject, 'متن '.$subject);
    }

    public function test_guests_go_to_login(): void
    {
        $this->get(route('account.tickets.index'))->assertRedirect(route('login'));
    }

    public function test_the_list_shows_only_my_tickets(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->openTicket($me, 'تیکت من');
        $this->openTicket($other, 'تیکت دیگری');

        $response = $this->actingAs($me)->get(route('account.tickets.index'))->assertOk();

        $response->assertSee('تیکت من')->assertDontSee('تیکت دیگری');
    }

    public function test_another_users_ticket_is_404_for_show_and_reply(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $this->openTicket($other, 'تیکت دیگری');

        $this->actingAs($me)->get(route('account.tickets.show', $theirs->id))->assertNotFound();
        $this->actingAs($me)->post(route('account.tickets.reply', $theirs->id), ['body' => 'نفوذ'])->assertNotFound();

        $this->assertSame(1, $theirs->messages()->count());
    }

    public function test_i_open_a_support_ticket(): void
    {
        $me = User::factory()->create();

        $response = $this->actingAs($me)->post(route('account.tickets.store'), [
            'subject' => 'سوال درباره پلن',
            'body' => 'پلن ۱۲۰۰ چند است؟',
            // Noe/vaziat e dast-kari-shode nadide gerefte mishan.
            'type' => 'kyc_upgrade',
            'status' => 'approved',
        ]);

        $ticket = Ticket::query()->sole();
        $response->assertRedirect(route('account.tickets.show', $ticket->id));
        $this->assertSame($me->id, $ticket->user_id);
        $this->assertSame(TicketType::Support, $ticket->type);
        $this->assertSame('open', $ticket->status->value);
    }

    public function test_opening_needs_a_subject_and_body(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('account.tickets.store'), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);
    }

    public function test_my_ticket_page_shows_staff_replies_as_support_and_hides_the_audit(): void
    {
        $me = User::factory()->create();
        $admin = User::factory()->admin()->named('ادمین مخفی')->create();
        $ticket = $this->openTicket($me, 'تیکت من');
        app(ReplyTicketAction::class)->execute($ticket, $admin->id, 'پاسخ پشتیبانی', staff: true);

        $response = $this->actingAs($me)->get(route('account.tickets.show', $ticket->id))->assertOk();

        $response->assertSee('پاسخ پشتیبانی')->assertSee('پشتیبانی')->assertDontSee('ادمین مخفی');
        $this->assertStringNotContainsString('data-ticket-event', (string) $response->getContent());
    }

    /** Header e site: moshtari link e panel (ke baraye u 404 ast) nemibine. */
    public function test_the_site_header_links_a_customer_to_tickets_not_the_panel(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('account.tickets.index'))->assertOk();

        $response->assertSee('data-account-link', false)->assertDontSee(route('panel.dashboard'));
    }

    public function test_i_reply_to_my_ticket(): void
    {
        $me = User::factory()->create();
        $ticket = $this->openTicket($me, 'تیکت من');

        $this->actingAs($me)
            ->post(route('account.tickets.reply', $ticket->id), ['body' => 'توضیح بیشتر'])
            ->assertRedirect(route('account.tickets.show', $ticket->id));

        $last = $ticket->messages()->orderByDesc('id')->first();
        $this->assertSame('توضیح بیشتر', $last->body);
        $this->assertFalse($last->is_staff);
    }
}
