<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\AdjustWalletAction;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Masir haye HTTP e ebtal/eslah e sanad e mali (FinanceController::reverse/correct).
 *
 * 🔴 Ledger append-only ast: ebtal = radif e JADID e `admin_reversal` ba jahat e
 * barax؛ eslah = ebtal + radif e jadid e `admin_adjust`. Ersal e dobare ye hamun
 * form radif e tekrari nemisaze (idempotent)، va faghat sanad e `admin_adjust`
 * ghabel e ebtal ast.
 */
final class FinanceReversalHttpTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '0b6c8f3e-1111-4a1b-9c2d-000000000101';

    private User $admin;

    private User $customer;

    private Wallet $wallet;

    private WalletTransaction $adjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create();
        $this->wallet = app(WalletAction::class)->forOwner($this->customer->id);
        $this->adjustment = app(AdjustWalletAction::class)->execute(
            $this->customer->id, 'credit', 125_000, 'جبران قطعی', $this->admin->id, self::KEY,
        );
    }

    // ── reverse ─────────────────────────────────────────────────

    public function test_reversing_an_adjustment_adds_an_opposite_row_and_keeps_the_original(): void
    {
        $this->actingAs($this->admin)
            ->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => 'اشتباه ثبت شده بود'])
            ->assertRedirect(route('panel.finance.show', $this->adjustment->id))->assertSessionHasNoErrors();

        /** @var WalletTransaction $reversal */
        $reversal = WalletTransaction::query()->where('reason', 'admin_reversal')->sole();
        self::assertSame('debit', $reversal->direction);
        self::assertSame(125_000, $reversal->amount_minor);
        self::assertSame((string) $this->adjustment->id, $reversal->reference);
        self::assertSame($this->admin->id, $reversal->meta['admin_id']);
        self::assertSame($this->adjustment->id, $reversal->meta['original_transaction_id']);
        self::assertSame('اشتباه ثبت شده بود', $reversal->meta['note']);
        $this->assertDatabaseHas('wallet_transactions', ['id' => $this->adjustment->id, 'reason' => 'admin_adjust', 'amount_minor' => 125_000]);
        self::assertSame(0, $this->wallet->refresh()->balance_minor);
        self::assertTrue(app(WalletQuery::class)->verifyBalance($this->wallet));
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_reversing_the_same_adjustment_twice_does_not_add_a_second_row(): void
    {
        $this->actingAs($this->admin)->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => 'بار اول']);

        $this->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => 'بار دوم'])
            ->assertRedirect(route('panel.finance.show', $this->adjustment->id));

        self::assertSame(1, WalletTransaction::query()->where('reason', 'admin_reversal')->count());
        self::assertSame(0, $this->wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_reversing_a_non_adjustment_row_is_rejected_with_an_error(): void
    {
        $topup = app(WalletAction::class)->credit($this->wallet, 10_000, 'topup', 'seed');

        $this->actingAs($this->admin)->from(route('panel.finance.show', $topup->id))
            ->post(route('panel.finance.reverse', $topup->id), ['note' => 'تلاش برای ابطال شارژ'])
            ->assertRedirect(route('panel.finance.show', $topup->id))->assertSessionHasErrors('note');

        $this->assertDatabaseMissing('wallet_transactions', ['reason' => 'admin_reversal']);
        self::assertSame(135_000, $this->wallet->refresh()->balance_minor);
    }

    public function test_reversing_an_adjustment_that_would_overdraw_is_rejected(): void
    {
        app(WalletAction::class)->debit($this->wallet, 100_000, 'api_call', 'used');

        $this->actingAs($this->admin)->from(route('panel.finance.show', $this->adjustment->id))
            ->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => 'ابطال'])
            ->assertRedirect(route('panel.finance.show', $this->adjustment->id))->assertSessionHasErrors('note');

        $this->assertDatabaseMissing('wallet_transactions', ['reason' => 'admin_reversal']);
        self::assertSame(25_000, $this->wallet->refresh()->balance_minor);
    }

    public function test_reverse_requires_a_note(): void
    {
        $this->actingAs($this->admin)->from(route('panel.finance.show', $this->adjustment->id))
            ->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    // ── correct ─────────────────────────────────────────────────

    public function test_correcting_creates_a_reversal_and_a_replacement_row(): void
    {
        $this->actingAs($this->admin)
            ->post(route('panel.finance.correct', $this->adjustment->id), [
                'direction' => 'credit', 'amount_rial' => '۱۰۰,۰۰۰', 'note' => 'مبلغ درست',
            ])
            ->assertRedirect(route('panel.finance.show', $this->adjustment->id))->assertSessionHasNoErrors();

        $reversal = WalletTransaction::query()->where('reason', 'admin_reversal')->sole();
        self::assertSame('debit', $reversal->direction);
        self::assertSame(125_000, $reversal->amount_minor);
        /** @var WalletTransaction $replacement */
        $replacement = WalletTransaction::query()->where('reference', 'correction:'.$this->adjustment->id)->sole();
        self::assertSame('admin_adjust', $replacement->reason);
        self::assertSame('credit', $replacement->direction);
        self::assertSame(100_000, $replacement->amount_minor);
        self::assertSame($this->adjustment->id, $replacement->meta['corrects_transaction_id']);
        self::assertSame(100_000, $this->wallet->refresh()->balance_minor);
        self::assertTrue(app(WalletQuery::class)->verifyBalance($this->wallet));
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_resubmitting_the_same_correction_is_idempotent(): void
    {
        $payload = ['direction' => 'credit', 'amount_rial' => '100,000', 'note' => 'مبلغ درست'];
        $this->actingAs($this->admin)->post(route('panel.finance.correct', $this->adjustment->id), $payload);

        $this->post(route('panel.finance.correct', $this->adjustment->id), $payload)
            ->assertRedirect(route('panel.finance.show', $this->adjustment->id))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('wallet_transactions', 3);
        self::assertSame(100_000, $this->wallet->refresh()->balance_minor);
    }

    public function test_a_different_correction_for_the_same_row_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('panel.finance.correct', $this->adjustment->id), [
            'direction' => 'credit', 'amount_rial' => '100,000', 'note' => 'مبلغ درست',
        ]);

        $this->from(route('panel.finance.show', $this->adjustment->id))
            ->post(route('panel.finance.correct', $this->adjustment->id), [
                'direction' => 'credit', 'amount_rial' => '90,000', 'note' => 'مبلغ درست',
            ])->assertSessionHasErrors('amount_rial');

        $this->assertDatabaseCount('wallet_transactions', 3);
        self::assertSame(100_000, $this->wallet->refresh()->balance_minor);
    }

    public function test_correction_with_an_invalid_amount_or_direction_changes_nothing(): void
    {
        $this->actingAs($this->admin)->from(route('panel.finance.show', $this->adjustment->id))
            ->post(route('panel.finance.correct', $this->adjustment->id), ['direction' => 'credit', 'amount_rial' => 'abc', 'note' => 'x'])
            ->assertSessionHasErrors('amount_rial');
        $this->from(route('panel.finance.show', $this->adjustment->id))
            ->post(route('panel.finance.correct', $this->adjustment->id), ['direction' => 'sideways', 'amount_rial' => '1,000', 'note' => 'x'])
            ->assertSessionHasErrors('direction');

        $this->assertDatabaseCount('wallet_transactions', 1);
        self::assertSame(125_000, $this->wallet->refresh()->balance_minor);
    }

    // ── gate ────────────────────────────────────────────────────

    /** `EnsureAdmin` be gheyr-admin AMDAN 404 mide (masir e panel ro lo nemide)، na 403. */
    public function test_a_customer_cannot_reverse_or_correct(): void
    {
        $this->actingAs($this->customer)
            ->post(route('panel.finance.reverse', $this->adjustment->id), ['note' => 'x'])->assertNotFound();
        $this->actingAs($this->customer)
            ->post(route('panel.finance.correct', $this->adjustment->id), ['direction' => 'credit', 'amount_rial' => '1,000', 'note' => 'x'])
            ->assertNotFound();
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
