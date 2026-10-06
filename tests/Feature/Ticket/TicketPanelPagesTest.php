<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P7-01 — inbox e admin (filter e noe/vaziat) + safhe ye ticket (payam ha،
 * audit، javab، taghir e vaziat).
 */
final class TicketPanelPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->named('ادمین اصلی')->create(['phone' => '+989120000000']);
    }

    private function openTicket(User $user, TicketType $type, string $subject): Ticket
    {
        return app(OpenTicketAction::class)->execute($user->id, $type, $subject, 'متن '.$subject);
    }

    public function test_guests_go_to_login_and_customers_get_404(): void
    {
        $this->get(route('panel.tickets.index'))->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $ticket = $this->openTicket($customer, TicketType::Support, 'سوال');
        $this->actingAs($customer)->get(route('panel.tickets.index'))->assertNotFound();
        $this->actingAs($customer)->get(route('panel.tickets.show', $ticket->id))->assertNotFound();
        $this->actingAs($customer)->post(route('panel.tickets.status', $ticket->id), ['status' => 'closed'])->assertNotFound();
    }

    public function test_the_inbox_lists_tickets_with_user_and_filters_by_type_and_status(): void
    {
        $customer = User::factory()->named('مریم احمدی')->create(['phone' => '+989121112233']);
        $this->openTicket($customer, TicketType::Support, 'مشکل کلید API');
        $kyc = $this->openTicket($customer, TicketType::KycUpgrade, 'درخواست ارتقای سطح');
        $kyc->forceFill(['status' => TicketStatus::InReview])->save();
        $admin = $this->makeAdmin();

        $all = $this->actingAs($admin)->get(route('panel.tickets.index'))->assertOk();
        $all->assertSee('مشکل کلید API')->assertSee('درخواست ارتقای سطح')->assertSee('مریم احمدی')->assertSee('09121112233');
        $this->assertSame(2, substr_count((string) $all->getContent(), 'data-ticket-row'));

        $byType = $this->actingAs($admin)->get(route('panel.tickets.index', ['type' => 'kyc_upgrade']))->assertOk();
        $byType->assertSee('درخواست ارتقای سطح')->assertDontSee('مشکل کلید API');

        $byStatus = $this->actingAs($admin)->get(route('panel.tickets.index', ['status' => 'open']))->assertOk();
        $byStatus->assertSee('مشکل کلید API')->assertDontSee('درخواست ارتقای سطح');
    }

    public function test_an_unknown_filter_value_is_an_error_not_ignored(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('panel.tickets.index', ['status' => 'hacked']))
            ->assertSessionHasErrors('status');
    }

    public function test_the_ticket_page_shows_messages_meta_and_the_audit_trail(): void
    {
        $customer = User::factory()->named('مریم احمدی')->create();
        $ticket = app(OpenTicketAction::class)->execute($customer->id, TicketType::KycUpgrade, 'ارتقا', 'لطفاً بررسی کنید', meta: ['registered_name' => 'شرکت طلای نمونه']);

        $this->actingAs($this->makeAdmin())->get(route('panel.tickets.show', $ticket->id))
            ->assertOk()
            ->assertSee('ارتقا')
            ->assertSee('لطفاً بررسی کنید')
            ->assertSee('مریم احمدی')
            ->assertSee('شرکت طلای نمونه')
            ->assertSee('ارتقای سطح')
            ->assertSee('data-ticket-event', false);
    }

    public function test_an_unknown_ticket_is_404(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('panel.tickets.show', 99_999))->assertNotFound();
    }

    public function test_the_admin_replies_as_staff(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->openTicket($customer, TicketType::Support, 'سوال');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('panel.tickets.reply', $ticket->id), ['body' => 'کلید جدید صادر شد.'])
            ->assertRedirect(route('panel.tickets.show', $ticket->id));

        $message = $ticket->messages()->orderByDesc('id')->first();
        $this->assertSame('کلید جدید صادر شد.', $message->body);
        $this->assertTrue($message->is_staff);
        $this->assertSame($admin->id, $message->author_id);
    }

    public function test_the_admin_changes_status_and_it_is_audited(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->openTicket($customer, TicketType::Support, 'سوال');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('panel.tickets.status', $ticket->id), ['status' => 'in_review', 'note' => 'بررسی'])
            ->assertRedirect(route('panel.tickets.show', $ticket->id));

        $this->assertSame(TicketStatus::InReview, $ticket->fresh()->status);
        $this->assertSame($admin->id, $ticket->events()->orderByDesc('id')->first()->actor_id);
    }

    public function test_a_decision_through_the_status_form_is_refused_with_a_message(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->openTicket($customer, TicketType::KycUpgrade, 'ارتقا');

        $this->actingAs($this->makeAdmin())
            ->from(route('panel.tickets.show', $ticket->id))
            ->post(route('panel.tickets.status', $ticket->id), ['status' => 'approved'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_the_user_page_lists_that_users_tickets_only(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->openTicket($customer, TicketType::Support, 'تیکت مشتری اول');
        $this->openTicket($other, TicketType::Support, 'تیکت مشتری دوم');

        $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))
            ->assertOk()
            ->assertSee('تیکت مشتری اول')
            ->assertDontSee('تیکت مشتری دوم');
    }
}
