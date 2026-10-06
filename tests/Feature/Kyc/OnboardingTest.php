<?php

declare(strict_types=1);

namespace Tests\Feature\Kyc;

use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Auth\Infrastructure\Sms\FakeSms;
use App\Modules\Ticket\Domain\Enums\TicketStatus;
use App\Modules\Ticket\Domain\Enums\TicketType;
use App\Modules\Ticket\Infrastructure\Persistence\Models\Ticket;
use App\Modules\Ticket\Infrastructure\Persistence\Models\TicketAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P7-03 — onboarding ba'd az avvalin login: «مایل به دریافت فاکتور رسمی
 * هستید؟» → kheir: basic va tamum؛ bale: form e hoghoghi + madarek →
 * ticket e `kyc_upgrade`.
 */
final class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tickets');
        // Estelam e khodkar (P7-05) tu in test ejra nashe.
        Queue::fake();

        // Kelid haye OTP e Redis (db 13) beyn e ejra ha MIMUNAN — cooldown e
        // ejra ye ghabli login e in test ro 429 mikard (felaki). Hamun olgu ye
        // `LoginFlowTest`.
        foreach (Redis::connection('default')->keys('otp:*') as $key) {
            Redis::connection('default')->del(str_replace((string) config('database.redis.options.prefix', ''), '', $key));
        }
    }

    /** @return array<string, mixed> */
    private function legalForm(array $overrides = []): array
    {
        return [
            'answer' => 'yes',
            'registered_name' => 'شرکت طلای نمونه',
            'company_national_id' => '10380284790',
            'economic_code' => '411111111111',
            'postal_code' => '1234567890',
            'address' => 'تهران، خیابان نمونه، پلاک ۱',
            'phone' => '02112345678',
            'representative_national_code' => '0499370899',
            'representative_birth_date' => '1370/01/15',
            'files' => [UploadedFile::fake()->create('javaz.pdf', 50, 'application/pdf')],
            ...$overrides,
        ];
    }

    private function verifyLogin(string $phone): TestResponse
    {
        $this->postJson(route('login.code'), ['phone' => $phone])->assertOk();
        $code = app(FakeSms::class)->lastCodeFor('+98'.substr($phone, 1));

        return $this->postJson(route('login.verify'), ['phone' => $phone, 'code' => $code]);
    }

    public function test_a_new_customer_lands_on_onboarding_after_the_first_login(): void
    {
        $this->verifyLogin('09121231234')->assertOk()->assertJson(['redirect' => route('onboarding.show')]);
    }

    public function test_an_onboarded_customer_goes_home(): void
    {
        User::factory()->create(['phone' => '+989121231235'])->forceFill(['onboarded_at' => now()])->save();

        $this->verifyLogin('09121231235')->assertJson(['redirect' => route('home')]);
    }

    public function test_an_admin_never_sees_onboarding(): void
    {
        User::factory()->admin()->create(['phone' => '+989121231236']);

        $this->verifyLogin('09121231236')->assertJson(['redirect' => route('panel.dashboard')]);
    }

    public function test_the_page_asks_the_invoice_question(): void
    {
        $this->actingAs(User::factory()->create())->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('مایل به دریافت فاکتور رسمی هستید؟')
            ->assertSee('data-onboarding-legal', false);
    }

    public function test_admins_are_sent_to_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('onboarding.show'))->assertRedirect(route('panel.dashboard'));
    }

    public function test_answering_no_finishes_onboarding_at_basic_level_without_a_ticket(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), ['answer' => 'no'])->assertRedirect(route('home'));

        $user->refresh();
        $this->assertNotNull($user->onboarded_at);
        $this->assertFalse($user->invoice_requested);
        $this->assertSame(UserLevel::Basic, $user->level);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertFalse($user->needsOnboarding());
    }

    public function test_answering_yes_opens_a_kyc_ticket_with_the_form_and_documents(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm());

        $ticket = Ticket::query()->sole();
        $response->assertRedirect(route('account.tickets.show', $ticket->id));
        $this->assertSame(TicketType::KycUpgrade, $ticket->type);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame([
            'registered_name' => 'شرکت طلای نمونه',
            'company_national_id' => '10380284790',
            'economic_code' => '411111111111',
            'postal_code' => '1234567890',
            // `NormalizeStringMiddleware` raghame Farsi e HAR vorudi ro Latin mikone.
            'address' => 'تهران، خیابان نمونه، پلاک 1',
            'phone' => '02112345678',
            'representative_national_code' => '0499370899',
            'representative_birth_date' => '1370/01/15',
        ], $ticket->meta);
        $this->assertSame(1, TicketAttachment::query()->where('ticket_id', $ticket->id)->count());

        $user->refresh();
        $this->assertNotNull($user->onboarded_at);
        $this->assertTrue($user->invoice_requested);
        // 🔴 Level faghat ba ta'eed e admin (P7-06) bala mire، na ba ersal.
        $this->assertSame(UserLevel::Basic, $user->level);
    }

    public function test_persian_digits_are_normalized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm([
            'company_national_id' => '۱۰۳۸۰۲۸۴۷۹۰',
            'postal_code' => '۱۲۳۴۵۶۷۸۹۰',
            'representative_national_code' => '۰۴۹۹۳۷۰۸۹۹',
            'representative_birth_date' => '۱۳۷۰/۱/۵',
        ]))->assertRedirect();

        $meta = Ticket::query()->sole()->meta;
        $this->assertSame('10380284790', $meta['company_national_id']);
        $this->assertSame('1234567890', $meta['postal_code']);
        $this->assertSame('0499370899', $meta['representative_national_code']);
        $this->assertSame('1370/01/05', $meta['representative_birth_date']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidFields(): array
    {
        return [
            'shenase e sherkat checksum' => [['company_national_id' => '10380284791'], 'company_national_id'],
            'code melli checksum' => [['representative_national_code' => '0499370898'], 'representative_national_code'],
            'code posti 9 ragham' => [['postal_code' => '123456789'], 'postal_code'],
            'tarikh e na-motabar' => [['representative_birth_date' => '1370/13/01'], 'representative_birth_date'],
            'tarikh e ayande' => [['representative_birth_date' => '1499/01/01'], 'representative_birth_date'],
            'code eghtesadi' => [['economic_code' => '12ab'], 'economic_code'],
            'telephone' => [['phone' => '12345'], 'phone'],
            'bedun e madrak' => [['files' => []], 'files'],
            'esm e sabti khali' => [['registered_name' => ''], 'registered_name'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_legal_fields_are_rejected(array $override, string $field): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm($override))->assertSessionHasErrors($field);

        $this->assertSame(0, Ticket::query()->count());
        $this->assertNull($user->fresh()->onboarded_at);
    }

    public function test_a_second_request_while_one_is_pending_is_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm())->assertRedirect();

        $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm())->assertSessionHasErrors('answer');

        $this->assertSame(1, Ticket::query()->count());
        // Safhe vaziat e darkhast e baz ro neshun mide، na form.
        $this->actingAs($user)->get(route('onboarding.show'))->assertOk()->assertSee('data-onboarding-pending', false)->assertDontSee('data-onboarding-legal', false);
    }

    public function test_an_answer_is_required(): void
    {
        $this->actingAs(User::factory()->create())->post(route('onboarding.store'), ['answer' => 'maybe'])->assertSessionHasErrors('answer');
    }

    public function test_a_basic_user_who_said_no_can_still_request_the_upgrade_later(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('onboarding.store'), ['answer' => 'no']);

        $this->actingAs($user)->post(route('onboarding.store'), $this->legalForm())->assertRedirect();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertTrue($user->fresh()->invoice_requested);
    }
}
