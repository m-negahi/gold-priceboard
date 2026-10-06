<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Api\Application\Actions\CreateSiteAction;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Application\Dto\VerificationResultDto;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeIdentityVerification;
use Tests\TestCase;

/**
 * Masir haye HTTP e «تأیید سایت‌ها» (SiteApprovalController): estelam e
 * enamad faghat ba dokme ye admin، tasmim e approve/reject، va gate ha.
 * Port e estelam fake ast — hich network-i be Zohal zade nemishe.
 */
final class SiteApprovalHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private int $ticketId;

    private int $clientId;

    private FakeIdentityVerification $identity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create();
        $created = app(CreateSiteAction::class)->execute($this->customer->id, 'Example', 'https://example.com');
        $this->ticketId = $created['ticket_id'];
        $this->clientId = $created['client']->id;
        $this->identity = new FakeIdentityVerification;
        $this->app->instance(IdentityVerificationPort::class, $this->identity);
    }

    public function test_the_index_lists_the_pending_site_with_its_controls(): void
    {
        $this->actingAs($this->admin)->get(route('panel.sites.index'))
            ->assertOk()
            ->assertSee('https://example.com')
            ->assertSee('هنوز استعلامی ثبت نشده است.')
            ->assertSee('استعلام نماد')->assertSee('تأیید سایت')->assertSee('رد سایت');
    }

    public function test_enamad_check_calls_the_port_once_and_stores_the_result_on_the_ticket(): void
    {
        $this->identity->results['enamad'] = new VerificationResultDto('passed', 'تأیید شد.', ['domain' => 'example.com', 'expired' => false]);

        $this->actingAs($this->admin)->post(route('panel.sites.enamad', $this->ticketId))
            ->assertRedirect(route('panel.sites.index'));

        self::assertCount(1, $this->identity->calls);
        self::assertSame(['check' => 'enamad', 'input' => ['website' => 'example.com']], $this->identity->calls[0]);
        $check = Ticket::query()->findOrFail($this->ticketId)->meta['enamad_check'];
        self::assertSame('passed', $check['status']);
        // Safhe natije ro neshun mide va client hanuz pending e (estelam ≠ tasmim).
        $this->get(route('panel.sites.index'))->assertOk()->assertSee('نماد: تأیید شد.');
        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'pending']);
    }

    public function test_approving_after_a_passed_check_activates_the_client(): void
    {
        $this->identity->results['enamad'] = new VerificationResultDto('passed', 'تأیید شد.', ['domain' => 'example.com', 'expired' => false]);
        $this->actingAs($this->admin)->post(route('panel.sites.enamad', $this->ticketId));

        $this->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'approve', 'note' => 'دامنه تأیید شد'])
            ->assertRedirect(route('panel.sites.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'approved', 'approved_by' => $this->admin->id]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticketId, 'status' => 'approved']);
    }

    public function test_approving_without_a_check_is_rejected_with_an_error(): void
    {
        $this->actingAs($this->admin)->from(route('panel.sites.index'))
            ->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'approve'])
            ->assertRedirect(route('panel.sites.index'))->assertSessionHasErrors('decision');

        self::assertCount(0, $this->identity->calls);
        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'pending']);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticketId, 'status' => 'open']);
    }

    public function test_approving_after_a_domain_mismatch_is_rejected(): void
    {
        $this->identity->results['enamad'] = new VerificationResultDto('passed', 'تأیید شد.', ['domain' => 'other.com', 'expired' => false]);
        $this->actingAs($this->admin)->post(route('panel.sites.enamad', $this->ticketId));

        $this->from(route('panel.sites.index'))
            ->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');

        $this->get(route('panel.sites.index'))->assertOk()->assertSee('دامنه نماد با سایت ثبت‌شده یکسان نیست.');
        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'pending']);
    }

    public function test_rejecting_requires_a_note_and_then_marks_client_and_ticket_rejected(): void
    {
        $this->actingAs($this->admin)->from(route('panel.sites.index'))
            ->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'reject', 'note' => '   '])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'pending']);

        $this->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'reject', 'note' => 'نماد ندارد'])
            ->assertRedirect(route('panel.sites.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'rejected', 'approved_by' => null]);
        $this->assertDatabaseHas('tickets', ['id' => $this->ticketId, 'status' => 'rejected']);
        // Tasmim e dovom ru hamun ticket rad mishe.
        $this->from(route('panel.sites.index'))
            ->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'reject', 'note' => 'دوباره'])
            ->assertSessionHasErrors('decision');
    }

    public function test_an_unknown_decision_value_fails_validation(): void
    {
        $this->actingAs($this->admin)->from(route('panel.sites.index'))
            ->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('api_clients', ['id' => $this->clientId, 'status' => 'pending']);
    }

    /** `EnsureAdmin` be gheyr-admin AMDAN 404 mide (masir e panel ro lo nemide)، na 403. */
    public function test_a_customer_cannot_reach_the_site_approval_routes(): void
    {
        $this->actingAs($this->customer)->get(route('panel.sites.index'))->assertNotFound();
        $this->actingAs($this->customer)->post(route('panel.sites.enamad', $this->ticketId))->assertNotFound();
        $this->actingAs($this->customer)->post(route('panel.sites.decision', $this->ticketId), ['decision' => 'approve'])->assertNotFound();
        self::assertCount(0, $this->identity->calls);
    }
}
