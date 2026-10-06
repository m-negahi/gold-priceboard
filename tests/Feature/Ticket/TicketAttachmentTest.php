<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Modules\Access\Application\Actions\ManageAccessAction;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Ticket\Application\Actions\OpenTicketAction;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P7-02 — madarek ru disk e PRIVATE: validate e mime/size، esm e tasadofi،
 * download e emza-dar FAGHAT baraye saheb + admin.
 */
final class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tickets');
    }

    private function ticketFor(User $user): Ticket
    {
        return app(OpenTicketAction::class)->execute($user->id, TicketType::Support, 'مدارک', 'متن');
    }

    private function pdf(string $name = 'license.pdf', int $kb = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function uploadAs(User $user, Ticket $ticket, array $files): TestResponse
    {
        return $this->actingAs($user)->post(route('account.tickets.reply', $ticket->id), [
            'body' => 'مدارک پیوست شد',
            'files' => $files,
        ]);
    }

    private function downloadUrl(TicketAttachment $attachment): string
    {
        return URL::temporarySignedRoute('tickets.attachments.download', now()->addMinutes(5), ['attachment' => $attachment->id], absolute: false);
    }

    public function test_the_tickets_disk_is_private_and_outside_public(): void
    {
        $disk = config('filesystems.disks.tickets');

        $this->assertSame('local', $disk['driver']);
        $this->assertSame('private', $disk['visibility']);
        $this->assertFalse($disk['serve'] ?? false);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertStringStartsNotWith(storage_path('app/public'), $disk['root']);
        $this->assertStringStartsNotWith(storage_path('app/private'), $disk['root']);
        $this->assertStringStartsNotWith(public_path(), $disk['root']);
    }

    public function test_a_reply_with_files_stores_them_privately_under_a_random_name(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);

        $this->uploadAs($me, $ticket, [$this->pdf('../../etc/passwd.pdf'), UploadedFile::fake()->image('scan.png')])
            ->assertRedirect(route('account.tickets.show', $ticket->id));

        $attachments = TicketAttachment::query()->orderBy('id')->get();
        $this->assertCount(2, $attachments);

        $first = $attachments[0];
        $this->assertSame($ticket->id, $first->ticket_id);
        $this->assertSame($ticket->messages()->orderByDesc('id')->first()->id, $first->message_id);
        $this->assertSame($me->id, $first->uploaded_by);
        $this->assertSame('tickets', $first->disk);
        $this->assertSame('application/pdf', $first->mime);
        $this->assertSame('passwd.pdf', $first->original_name);
        $this->assertMatchesRegularExpression('#^'.$ticket->id.'/[0-9a-f-]{36}\.pdf$#', $first->path);
        $this->assertSame(64, strlen($first->sha256));
        Storage::disk('tickets')->assertExists($first->path);
        $this->assertStringEndsWith('.png', $attachments[1]->path);
    }

    public function test_a_disallowed_type_is_rejected_and_nothing_is_stored(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);

        $this->uploadAs($me, $ticket, [UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
            ->assertSessionHasErrors(['files.0' => 'نوع فایل مجاز نیست (فقط PDF، JPG، PNG، WEBP).']);
        $this->uploadAs($me, $ticket, [UploadedFile::fake()->create('fake.pdf', 1, 'text/html')])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, TicketAttachment::query()->count());
        $this->assertSame([], Storage::disk('tickets')->allFiles());
        // Payam ham sabt nashod (validation ghabl az har neveshtan).
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_size_and_count_limits(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);

        $this->uploadAs($me, $ticket, [$this->pdf('big.pdf', 5121)])->assertSessionHasErrors('files.0');
        $this->uploadAs($me, $ticket, array_map(fn (int $i): UploadedFile => $this->pdf("d{$i}.pdf", 10), range(1, 6)))
            ->assertSessionHasErrors('files');

        $this->assertSame(0, TicketAttachment::query()->count());
    }

    public function test_the_owner_and_an_admin_download_through_a_signed_link(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);
        $this->uploadAs($me, $ticket, [UploadedFile::fake()->image('scan.png')]);
        $attachment = TicketAttachment::query()->sole();

        $mine = $this->actingAs($me)->get($this->downloadUrl($attachment))->assertOk();
        $mine->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $mine->headers->get('Content-Disposition'));
        $this->assertStringContainsString('scan.png', (string) $mine->headers->get('Content-Disposition'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get($this->downloadUrl($attachment))->assertOk();
    }

    /** 🔴 IDOR: link e emza-dar e SAHEB dast e karbar e digar 404 mide. */
    public function test_another_customer_cannot_download_even_with_a_valid_signature(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);
        $this->uploadAs($me, $ticket, [$this->pdf()]);
        $attachment = TicketAttachment::query()->sole();

        $this->actingAs(User::factory()->create())->get($this->downloadUrl($attachment))->assertNotFound();
    }

    public function test_unsigned_expired_and_guest_requests_are_refused(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);
        $this->uploadAs($me, $ticket, [$this->pdf()]);
        $attachment = TicketAttachment::query()->sole();

        $this->actingAs($me)->get(route('tickets.attachments.download', $attachment->id))->assertForbidden();

        $expired = URL::temporarySignedRoute('tickets.attachments.download', now()->subMinute(), ['attachment' => $attachment->id], absolute: false);
        $this->actingAs($me)->get($expired)->assertForbidden();

        auth()->logout();
        $this->get($this->downloadUrl($attachment))->assertRedirect(route('login'));
    }

    public function test_ticket_pages_list_attachments_with_signed_links(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);
        $this->uploadAs($me, $ticket, [$this->pdf('javaz.pdf')]);

        $mine = (string) $this->actingAs($me)->get(route('account.tickets.show', $ticket->id))->assertOk()->getContent();
        $this->assertStringContainsString('javaz.pdf', $mine);
        $this->assertMatchesRegularExpression('#/tickets/attachments/\d+\?expires=\d+&amp;signature=#', $mine);

        $admin = User::factory()->admin()->create();
        $panel = (string) $this->actingAs($admin)->get(route('panel.tickets.show', $ticket->id))->assertOk()->getContent();
        $this->assertStringContainsString('javaz.pdf', $panel);
        $this->assertStringContainsString('data-attachment', $panel);
    }

    public function test_an_admin_reply_can_carry_files_and_a_new_ticket_too(): void
    {
        $me = User::factory()->create();
        $ticket = $this->ticketFor($me);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('panel.tickets.reply', $ticket->id), [
            'body' => 'فرم امضاشده',
            'files' => [$this->pdf('form.pdf')],
        ])->assertRedirect();

        $this->actingAs($me)->post(route('account.tickets.store'), [
            'subject' => 'با پیوست',
            'body' => 'متن',
            'files' => [$this->pdf('first.pdf')],
        ])->assertRedirect();

        $this->assertSame(['form.pdf', 'first.pdf'], TicketAttachment::query()->orderBy('id')->pluck('original_name')->all());
        $this->assertSame($admin->id, TicketAttachment::query()->orderBy('id')->first()->uploaded_by);
    }

    public function test_admin_without_ticket_permission_cannot_download_someone_elses_private_document(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $restricted = User::factory()->admin()->create();
        $access = app(ManageAccessAction::class);
        $role = $access->saveRole($admin, null, ['name' => 'خواننده کاربران', 'permissions' => ['users.view']]);
        $access->assign($admin, $restricted->id, [$role->id], false);
        $ticket = $this->ticketFor($owner);
        $this->uploadAs($owner, $ticket, [$this->pdf()])->assertRedirect();
        $attachment = TicketAttachment::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $this->actingAs($restricted)->get($this->downloadUrl($attachment))->assertNotFound();
        $this->actingAs($owner)->get($this->downloadUrl($attachment))->assertDownload('license.pdf');
    }
}
