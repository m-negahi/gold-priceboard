<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Kyc\Jobs\RunIdentityChecks;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminTicketCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_ticket_for_user_with_multiple_private_files(): void
    {
        Storage::fake('tickets');
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $this->actingAs($admin)->post(route('panel.tickets.store'), ['user_id' => $owner->id, 'type' => 'support', 'subject' => 'مدارک', 'body' => 'دو فایل', 'files' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])->assertRedirect();
        $ticket = Ticket::query()->firstOrFail();
        self::assertSame($owner->id, $ticket->user_id);
        self::assertSame($admin->id, $ticket->messages()->first()->author_id);
        self::assertTrue($ticket->messages()->first()->is_staff);
        self::assertSame(2, TicketAttachment::query()->count());
        foreach (TicketAttachment::query()->get() as $attachment) {
            self::assertSame('tickets', $attachment->disk);
            Storage::disk('tickets')->assertExists($attachment->path);
        }
        $this->actingAs($owner)->get(route('account.tickets.show', $ticket->id))->assertSee('دو فایل');
        $this->actingAs(User::factory()->create())->post(route('panel.tickets.store'), [])->assertNotFound();
    }

    public function test_customer_submits_natural_identity_request_with_private_document(): void
    {
        Queue::fake([RunIdentityChecks::class]);
        Storage::fake('tickets');
        $user = User::factory()->create(['name' => null]);
        $this->actingAs($user)->post(route('account.tickets.store'), ['request_type' => 'kyc_upgrade', 'name' => 'نماینده', 'subject' => 'احراز هویت', 'body' => 'مدارک', 'customer_type' => 'individual', 'national_code' => '0499370899', 'birth_date' => '1370/01/15', 'postal_code' => '1234567890', 'address' => 'تهران', 'files' => [UploadedFile::fake()->image('a.png')]])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['user_id' => $user->id, 'type' => 'kyc_upgrade']);
        self::assertSame('basic', $user->refresh()->level->value);
        Queue::assertPushed(RunIdentityChecks::class, 1);
    }

    public function test_total_size_and_count_are_rejected_before_creating_ticket(): void
    {
        Storage::fake('tickets');
        $admin = User::factory()->admin()->create();
        $payload = ['user_id' => $admin->id, 'type' => 'support', 'subject' => 'مدارک', 'body' => 'فایل'];
        $files = array_map(static fn () => UploadedFile::fake()->image('a.png')->size(4096), range(1, 4));
        $this->actingAs($admin)->post(route('panel.tickets.store'), [...$payload, 'files' => $files])->assertSessionHasErrors('files');
        $this->post(route('panel.tickets.store'), [...$payload, 'files' => array_map(static fn () => UploadedFile::fake()->image('a.png'), range(1, 6))])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('tickets', 0);
    }
}
