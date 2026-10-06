<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Infrastructure\Persistence\Models\WalletTopupPayment;
use App\Modules\Finance\Application\Actions\SettleGatewayPaymentAction;
use App\Modules\Finance\Application\Queries\FinanceLedgerQuery;
use App\Modules\Wallet\Application\Actions\AdjustWalletAction;
use App\Modules\Wallet\Application\Actions\ReverseWalletAdjustmentAction;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FinanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_postings_balance_and_reversal_keeps_original(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($customer->id);
        $wallets->credit($wallet, 1_000, 'topup', 'seed');
        $original = app(AdjustWalletAction::class)->execute($customer->id, 'credit', 500, 'credit note', $admin->id, 'd88b064b-0ebd-4f7d-b092-f2646074e11a');

        $reversal = app(ReverseWalletAdjustmentAction::class)->reverse($original->id, $admin->id, 'wrong amount');

        self::assertSame(1_000, $wallet->refresh()->balance_minor);
        self::assertSame('credit', $original->fresh()->direction);
        self::assertSame(500, $original->fresh()->amount_minor);
        self::assertSame('debit', $reversal->direction);
        self::assertSame((string) $original->id, $reversal->reference);
        $this->assertDatabaseCount('wallet_transactions', 3);
        $journals = DB::table('finance_journal_lines')->groupBy('finance_journal_id')
            ->selectRaw('finance_journal_id, sum(debit_minor) as debits, sum(credit_minor) as credits')->get();
        self::assertCount(3, $journals);
        foreach ($journals as $journal) {
            self::assertSame((int) $journal->debits, (int) $journal->credits);
        }
        $trial = app(FinanceLedgerQuery::class)->trialBalance();
        self::assertSame('1,000', collect($trial)->firstWhere('code', '2100')['balance']);
    }

    public function test_a_second_reversal_is_idempotent_and_cannot_overdraw(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($customer->id);
        $original = app(AdjustWalletAction::class)->execute($customer->id, 'credit', 500, 'seed', $admin->id, 'd88b064b-0ebd-4f7d-b092-f2646074e11a');
        $wallets->debit($wallet, 500, 'api_call', 'used');

        try {
            app(ReverseWalletAdjustmentAction::class)->reverse($original->id, $admin->id, 'cancel');
            self::fail('mande ye manfi bayad rad she');
        } catch (WalletException) {
            self::assertSame(0, $wallet->refresh()->balance_minor);
            $this->assertDatabaseMissing('wallet_transactions', ['reason' => 'admin_reversal']);
        }

        $wallets->credit($wallet, 500, 'topup', 'refill');
        $first = app(ReverseWalletAdjustmentAction::class)->reverse($original->id, $admin->id, 'cancel');
        $second = app(ReverseWalletAdjustmentAction::class)->reverse($original->id, $admin->id, 'cancel');
        self::assertSame($first->id, $second->id);
        self::assertSame(0, $wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 4);
    }

    public function test_wallet_reference_cannot_be_reused_for_another_amount_or_direction(): void
    {
        $customer = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($customer->id);
        $wallets->credit($wallet, 1_000, 'topup', 'same-reference');

        foreach ([
            static fn (): WalletTransaction => $wallets->credit($wallet, 2_000, 'topup', 'same-reference'),
            static fn (): WalletTransaction => $wallets->debit($wallet, 1_000, 'topup', 'same-reference'),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Kelid e idempotency nabayad baraye darkhast e digar estefade shavad');
            } catch (WalletException $exception) {
                self::assertSame(WalletException::REFERENCE_REUSED, $exception->getCode());
            }
        }
        self::assertSame(1_000, $wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_correction_rolls_back_both_rows_if_replacement_would_overdraw(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $wallet = app(WalletAction::class)->forOwner($customer->id);
        $original = app(AdjustWalletAction::class)->execute($customer->id, 'credit', 1_000, 'initial', $admin->id, 'd88b064b-0ebd-4f7d-b092-f2646074e11a');

        try {
            app(ReverseWalletAdjustmentAction::class)->correct($original->id, $admin->id, 'debit', 100, 'replacement');
            self::fail('kasr e bedun e mande bayad rad she');
        } catch (WalletException) {
            self::assertSame(1_000, $wallet->refresh()->balance_minor);
            $this->assertDatabaseCount('wallet_transactions', 1);
        }

        $corrected = app(ReverseWalletAdjustmentAction::class)->correct($original->id, $admin->id, 'credit', 600, 'replacement');
        $retry = app(ReverseWalletAdjustmentAction::class)->correct($original->id, $admin->id, 'credit', 600, 'replacement');
        self::assertSame($corrected->id, $retry->id);
        self::assertSame(600, $wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_verified_gateway_receipt_settles_once_into_bank_account(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $payment = WalletTopupPayment::query()->create([
            'user_id' => $customer->id,
            'request_key' => '72db8735-67d0-4bc0-88d4-9227ee6b2c37',
            'amount_minor' => 25_000,
            'track_id' => 'track-25',
            'status' => 'verified',
        ]);
        app(WalletAction::class)->credit(app(WalletAction::class)->forOwner($customer->id), 25_000, 'topup', 'zibal:track-25');

        $settlements = app(SettleGatewayPaymentAction::class);
        $first = $settlements->execute($payment->id, $admin->id, 'bank-123');
        self::assertSame($first, $settlements->execute($payment->id, $admin->id, 'bank-123'));
        $this->assertDatabaseCount('finance_journals', 2);
        $this->assertDatabaseHas('finance_journal_lines', [
            'finance_journal_id' => $first,
            'finance_account_id' => DB::table('finance_accounts')->where('code', '1000')->value('id'),
            'debit_minor' => 25_000,
        ]);
        $this->assertDatabaseHas('finance_journal_lines', [
            'finance_journal_id' => $first,
            'finance_account_id' => DB::table('finance_accounts')->where('code', '1100')->value('id'),
            'credit_minor' => 25_000,
        ]);
        self::assertSame('0', collect(app(FinanceLedgerQuery::class)->trialBalance())->firstWhere('code', '1100')['balance']);
    }

    public function test_global_wallet_and_account_ledgers_filter_by_user_and_search(): void
    {
        $target = User::factory()->create(['name' => 'Ali Finance']);
        $other = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallets->credit($wallets->forOwner($target->id), 1_200, 'topup', 'finance-search-target');
        $wallets->credit($wallets->forOwner($other->id), 900, 'topup', 'finance-search-other');

        $query = app(FinanceLedgerQuery::class);
        $walletRows = $query->ledger(['user_id' => $target->id, 'q' => 'finance-search-target']);
        self::assertSame(1, $walletRows->total());
        self::assertSame($target->id, $walletRows->first()['ownerId']);

        $journalRows = $query->generalLedger(['user_id' => $target->id, 'account_code' => '2100', 'q' => 'finance-search-target']);
        self::assertSame(1, $journalRows->total());
        self::assertSame('2100', $journalRows->first()['code']);
        self::assertSame('1,200', $journalRows->first()['creditRial']);
    }

    public function test_admin_finance_page_renders_journal_and_export_escapes_spreadsheet_formula(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallets->credit($wallets->forOwner($customer->id), 1_200, 'topup', '=1+2');

        $this->actingAs($admin)->get(route('panel.finance.index', [
            'user_id' => $customer->id, 'account_code' => '2100', 'q' => '=1+2',
        ]))->assertOk()->assertSee('دفتر کل حساب‌ها')->assertSee('1,200');

        $date = now()->format('Y-m-d');
        $response = $this->actingAs($admin)->get(route('panel.finance.export', ['from' => $date, 'to' => $date]));
        $response->assertOk();
        self::assertStringContainsString("'=1+2", $response->streamedContent());
    }
}
