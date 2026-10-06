<?php

declare(strict_types=1);

namespace Tests\Feature\Kyc;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Kyc\Application\Actions\DecideKycAction;
use App\Modules\Kyc\Application\Actions\QueueIdentityChecksAction;
use App\Modules\Kyc\Application\Actions\RunIdentityChecksAction;
use App\Modules\Kyc\Application\Dto\VerificationResultDto;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Kyc\Jobs\RunIdentityChecks;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Jobs\SendTicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\FakeIdentityVerification;
use Tests\TestCase;

final class IdentityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_and_duplicate_job_claim_execute_checks_once(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $user = User::factory()->create();
        $fake = new FakeIdentityVerification;
        $this->app->instance(IdentityVerificationPort::class, $fake);
        $input = ['mobile' => $user->phone, 'national_code' => '0499370899', 'birth_date' => '1370/01/15', 'postal_code' => '1234567890'];
        $id = app(QueueIdentityChecksAction::class)->execute($user->id, $input, 'submission:1');
        self::assertSame($id, app(QueueIdentityChecksAction::class)->execute($user->id, $input, 'submission:1'));
        app(RunIdentityChecksAction::class)->execute($id);
        app(RunIdentityChecksAction::class)->execute($id);
        self::assertCount(3, $fake->calls);
        $this->assertDatabaseHas('identity_verification_runs', ['id' => $id, 'status' => 'completed']);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_approval_updates_invoice_profile_and_queues_notification_once(): void
    {
        Queue::fake([SendTicketStatus::class]);
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $meta = ['registered_name' => 'شرکت نمونه', 'company_national_id' => '10380284790', 'postal_code' => '1234567890'];
        $ticket = app(OpenTicketAction::class)->execute($user->id, TicketType::KycUpgrade, 'احراز هویت', 'مدارک', $meta);
        app(DecideKycAction::class)->execute($ticket, $admin, true, 'مدارک بررسی شد');
        self::assertSame('invoice_verified', $user->refresh()->level->value);
        self::assertSame($meta, $user->invoice_profile);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'approved']);
        $this->assertDatabaseHas('user_profile_audits', ['user_id' => $user->id, 'actor_id' => $admin->id]);
        Queue::assertPushed(SendTicketStatus::class, 1);
        $eventId = DB::table('ticket_events')->where('ticket_id', $ticket->id)->where('kind', 'decision')->value('id');
        $sms = new FakeSms;
        (new SendTicketStatus($eventId))->handle($sms);
        (new SendTicketStatus($eventId))->handle($sms);
        self::assertSame([['phone' => $user->phone, 'ticket_id' => $ticket->id, 'status' => 'approved']], $sms->ticketMessages);
    }

    public function test_rejection_requires_reason_and_leaves_user_basic(): void
    {
        Queue::fake([SendTicketStatus::class]);
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $ticket = app(OpenTicketAction::class)->execute($user->id, TicketType::KycUpgrade, 'احراز هویت', 'مدارک');
        $this->actingAs($admin)->post(route('panel.tickets.decide', $ticket->id), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
        $this->actingAs($admin)->post(route('panel.tickets.decide', $ticket->id), ['decision' => 'reject', 'reason' => 'مدرک ناقص است'])->assertRedirect();
        self::assertSame('basic', $user->refresh()->level->value);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'kind' => 'decision', 'note' => 'مدرک ناقص است']);
        $this->actingAs($admin)->post(route('panel.tickets.decide', $ticket->id), ['decision' => 'approve'])->assertSessionHasErrors('decision');
        self::assertSame('basic', $user->refresh()->level->value);
        Queue::assertPushed(SendTicketStatus::class, 1);
    }

    public function test_customer_cannot_decide_or_rerun_and_admin_rerun_is_explicit(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $user = User::factory()->create();
        $ticket = app(OpenTicketAction::class)->execute($user->id, TicketType::KycUpgrade, 'احراز هویت', 'مدارک');
        $this->actingAs($user)->post(route('panel.tickets.decide', $ticket->id), ['decision' => 'approve'])->assertNotFound();
        $this->actingAs($user)->post(route('panel.tickets.rerun', $ticket->id))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->post(route('panel.tickets.rerun', $ticket->id))->assertRedirect();
        $this->assertDatabaseCount('identity_verification_runs', 1);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_company_name_and_postal_mismatch_are_reported_without_repeating_checks(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        $user = User::factory()->create();
        $fake = new FakeIdentityVerification;
        $fake->results['company'] = new VerificationResultDto('passed', 'استعلام موفق', ['name' => 'شرکت دیگری', 'national_id' => '10380284790', 'postal_code' => '9999999999', 'address' => 'نشانی دیگری']);
        $this->app->instance(IdentityVerificationPort::class, $fake);
        $id = app(QueueIdentityChecksAction::class)->execute($user->id, ['mobile' => $user->phone, 'representative_national_code' => '0499370899', 'representative_birth_date' => '1370/01/15', 'company_national_id' => '10380284790', 'registered_name' => 'شرکت نمونه', 'postal_code' => '1234567890', 'address' => 'تهران'], 'legal:1');
        app(RunIdentityChecksAction::class)->execute($id);
        $results = json_decode(DB::table('identity_verification_runs')->where('id', $id)->value('results'), true);
        self::assertSame('failed', $results['company']['status']);
        self::assertSame('warning', $results['address_match']['status']);
        self::assertCount(4, $fake->calls);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }
}
