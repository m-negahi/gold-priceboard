<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * P6-06 — sharj/kasr e DASTI e kif e pul tavasot e admin.
 *
 * 🔴 Har adjust = radif e JADID e ledger ba reason e `admin_adjust` + id e
 * admin + dalil (append-only). Ersal e dobare ye HAMUN form (double-click،
 * refresh) faghat yek radif misaze (idempotency key e form).
 */
final class WalletAdjustTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['phone' => '+989120000000']);
        $this->customer = User::factory()->create();
    }

    /** @param array<string, string> $overrides */
    private function postAdjust(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)
            ->from(route('panel.users.show', $this->customer->id))
            ->post(route('panel.users.wallet.adjust', $this->customer->id), array_merge([
                'direction' => 'credit',
                'amount' => '125,000',
                'note' => 'جبران قطعی سرویس',
                'idempotency_key' => '0b6c8f3e-1111-4a1b-9c2d-000000000001',
            ], $overrides));
    }

    private function wallet(): ?Wallet
    {
        return app(WalletQuery::class)->findForOwner($this->customer->id);
    }

    public function test_an_admin_credits_a_wallet_with_a_reason_and_their_id(): void
    {
        $this->postAdjust()->assertRedirect(route('panel.users.show', $this->customer->id))
            ->assertSessionHasNoErrors();

        $wallet = $this->wallet();
        $this->assertNotNull($wallet);
        // 125,000 toman = 1,250,000 rial
        $this->assertSame(1_250_000, $wallet->balance_minor);

        /** @var WalletTransaction $tx */
        $tx = WalletTransaction::query()->where('wallet_id', $wallet->id)->sole();
        $this->assertSame('credit', $tx->direction);
        $this->assertSame('admin_adjust', $tx->reason);
        $this->assertSame(1_250_000, $tx->amount_minor);
        $this->assertSame($this->admin->id, $tx->meta['admin_id']);
        $this->assertSame('جبران قطعی سرویس', $tx->meta['note']);
        $this->assertTrue(app(WalletQuery::class)->verifyBalance($wallet));
    }

    public function test_an_admin_debits_as_a_new_ledger_row(): void
    {
        $this->postAdjust();
        $this->postAdjust(['direction' => 'debit', 'amount' => '25000', 'idempotency_key' => '0b6c8f3e-1111-4a1b-9c2d-000000000002'])
            ->assertSessionHasNoErrors();

        $wallet = $this->wallet();
        $this->assertSame(1_000_000, $wallet->balance_minor);
        // Append-only: radif e avval dast nakhorde، radif e dovom ezafe shode.
        $rows = WalletTransaction::query()->where('wallet_id', $wallet->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1_250_000, $rows[0]->amount_minor);
        $this->assertSame('debit', $rows[1]->direction);
        $this->assertSame(1_000_000, $rows[1]->balance_after_minor);
    }

    public function test_the_same_form_submitted_twice_creates_one_row(): void
    {
        $this->postAdjust();
        $this->postAdjust()->assertSessionHasNoErrors();

        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(1_250_000, $this->wallet()->balance_minor);
    }

    public function test_a_debit_above_the_balance_is_refused(): void
    {
        $this->postAdjust(['direction' => 'debit', 'amount' => '1'])
            ->assertSessionHasErrors(['amount' => 'موجودی کیف پول کافی نیست.']);

        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_a_blocked_wallet_is_refused(): void
    {
        $wallets = app(WalletAction::class);
        $wallets->block($wallets->forOwner($this->customer->id));

        $this->postAdjust()->assertSessionHasErrors(['amount' => 'کیف پول مسدود است.']);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_a_reason_is_required(): void
    {
        $this->postAdjust(['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        foreach (['0', '-5', '12.34', 'abc', ''] as $bad) {
            $this->postAdjust(['amount' => $bad])->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_an_invalid_direction_is_rejected(): void
    {
        $this->postAdjust(['direction' => 'steal'])->assertSessionHasErrors('direction');
    }

    /** 🔴 Hich float: adad e bozorg e toman daghigh be rial mire. */
    public function test_large_and_persian_amounts_are_exact(): void
    {
        $this->postAdjust(['amount' => '99999999999999.5'])->assertSessionHasNoErrors();
        $this->assertSame(999_999_999_999_995, $this->wallet()->balance_minor);

        $this->postAdjust(['amount' => '۱۲۵٬۰۰۰', 'idempotency_key' => '0b6c8f3e-1111-4a1b-9c2d-000000000003'])->assertSessionHasNoErrors();
        $this->assertSame(999_999_999_999_995 + 1_250_000, $this->wallet()->balance_minor);
    }

    public function test_customers_cannot_adjust_wallets(): void
    {
        $this->postAdjust([], $this->customer)->assertNotFound();
        $this->assertNull($this->wallet());
    }

    public function test_a_missing_idempotency_key_is_rejected(): void
    {
        $this->postAdjust(['idempotency_key' => ''])->assertSessionHasErrors('idempotency_key');
    }

    public function test_the_user_page_has_the_form_and_shows_the_note_and_admin(): void
    {
        $page = $this->actingAs($this->admin)->get(route('panel.users.show', $this->customer->id))->assertOk();
        $page->assertSee(route('panel.users.wallet.adjust', $this->customer->id), false)
            ->assertSee('name="idempotency_key"', false);

        $this->postAdjust();

        $this->actingAs($this->admin)->get(route('panel.users.show', $this->customer->id))
            ->assertOk()
            ->assertSee('اصلاح دستی ادمین')
            ->assertSee('جبران قطعی سرویس')
            ->assertSee('09120000000');
    }

    public function test_every_render_gets_a_fresh_idempotency_key(): void
    {
        $first = (string) $this->actingAs($this->admin)->get(route('panel.users.show', $this->customer->id))->getContent();
        $second = (string) $this->actingAs($this->admin)->get(route('panel.users.show', $this->customer->id))->getContent();

        preg_match('/name="idempotency_key" value="([^"]+)"/', $first, $a);
        preg_match('/name="idempotency_key" value="([^"]+)"/', $second, $b);

        $this->assertNotEmpty($a[1] ?? null);
        $this->assertNotSame($a[1], $b[1] ?? null);
    }
}
