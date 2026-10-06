<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingInvoice;
use App\Modules\Billing\Infrastructure\Persistence\Models\WalletTopupPayment;
use App\Modules\Common\Helpers\Jalali;
use App\Modules\Finance\Application\Queries\FinanceMonthlyReportQuery;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Safhe ye «امور مالی» ba 6 tab (2026-10-06): gozaresh e mahane ye Jalali،
 * sodur e factor e rasmi، sharj e kif pool az hamin safhe.
 */
final class FinancePageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * `wallet_transactions` append-only ast (trigger)، pas `created_at` ro
     * nemishe ba'dan avaz kard — zaman ro ghabl az sabt mibarim jolo/aghab.
     */
    private function credit(User $user, int $amount, string $reason, string $reference, CarbonImmutable $at): WalletTransaction
    {
        $this->travelTo($at);
        $wallets = app(WalletAction::class);

        return $wallets->credit($wallets->forOwner($user->id), $amount, $reason, $reference);
    }

    private function debit(User $user, int $amount, string $reason, string $reference, CarbonImmutable $at): void
    {
        $this->travelTo($at);
        $wallets = app(WalletAction::class);
        $wallets->debit($wallets->forOwner($user->id), $amount, $reason, $reference);
    }

    // ── tab ha ──────────────────────────────────────────────────

    public function test_the_page_renders_every_tab_and_keeps_the_requested_one(): void
    {
        $this->actingAs($this->admin())->get(route('panel.finance.index', ['tab' => 'invoices']))
            ->assertOk()
            ->assertSee('گزارش ماهانه')->assertSee('تراکنش‌ها')->assertSee('دفتر کل')
            ->assertSee('تسویه درگاه')->assertSee('فاکتورها')->assertSee('شارژ کیف پول')
            ->assertViewHas('tab', 'invoices')
            ->assertSee('data-monthly-report', false);
    }

    public function test_an_unknown_tab_is_rejected(): void
    {
        $this->actingAs($this->admin())->get(route('panel.finance.index', ['tab' => 'nist']))->assertSessionHasErrors('tab');
    }

    // ── gozaresh e mahane ───────────────────────────────────────

    /**
     * 🔴 Satl ha mah e JALALI-and: 1405/07/01 = 2026-09-23. Yek sanad e
     * 2026-09-22 (31 Shahrivar) nabayad tu Mehr biofte.
     */
    public function test_the_monthly_report_buckets_by_jalali_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:00', 'UTC'));
        $customer = User::factory()->create();

        // Mehr 1405: 2026-09-23 .. 2026-10-22
        $this->credit($customer, 1_000_000, 'topup', 'mehr-1', CarbonImmutable::parse('2026-10-01 10:00:00', 'Asia/Tehran'));
        $this->debit($customer, 300_000, 'api_call', 'mehr-2', CarbonImmutable::parse('2026-10-02 10:00:00', 'Asia/Tehran'));
        // 31 Shahrivar 1405 = 2026-09-22 — mah e ghabl.
        $this->credit($customer, 500_000, 'topup', 'shahrivar-1', CarbonImmutable::parse('2026-09-22 23:30:00', 'Asia/Tehran'));
        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:00', 'UTC'));

        $report = app(FinanceMonthlyReportQuery::class)->report(['period' => '3m']);

        self::assertSame('3m', $report['period']);
        self::assertFalse($report['custom']);
        self::assertCount(3, $report['rows']);
        self::assertSame(['1405-05', '1405-06', '1405-07'], array_column($report['rows'], 'key'));
        self::assertSame('مهر 1405', $report['rows'][2]['label']);

        $mehr = $report['rows'][2];
        self::assertSame(1_000_000, $mehr['topup']);
        self::assertSame(300_000, $mehr['apiCall']);
        self::assertSame(700_000, $mehr['net']);
        self::assertSame(2, $mehr['count']);
        self::assertSame('1,000,000', $mehr['topupRial']);

        $shahrivar = $report['rows'][1];
        self::assertSame(500_000, $shahrivar['topup']);
        self::assertSame(1, $shahrivar['count']);

        self::assertSame(1_500_000, $report['totals']['topup']);
        self::assertSame(3, $report['totals']['count']);
        self::assertSame('2026-07-23', $report['from']);
        self::assertSame('2026-10-22', $report['to']);
    }

    public function test_a_custom_range_covers_every_jalali_month_between_the_dates(): void
    {
        $report = app(FinanceMonthlyReportQuery::class)->report(['from' => '2026-06-25', 'to' => '2026-09-25']);

        self::assertTrue($report['custom']);
        // 4 Tir .. 3 Mehr -> Tir، Mordad، Shahrivar، Mehr
        self::assertSame(['1405-04', '1405-05', '1405-06', '1405-07'], array_column($report['rows'], 'key'));
        self::assertSame(Jalali::toIso('1405/04/01'), $report['from']);
    }

    public function test_the_monthly_csv_export_streams_rows_and_totals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:00', 'UTC'));
        $customer = User::factory()->create();
        $this->credit($customer, 1_000_000, 'topup', 'x', CarbonImmutable::parse('2026-10-01 10:00:00', 'Asia/Tehran'));
        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:00', 'UTC'));

        $response = $this->actingAs($this->admin())->get(route('panel.finance.monthly-export', ['period' => 'current']));
        $response->assertOk();
        $csv = $response->streamedContent();

        self::assertStringContainsString('مهر 1405', $csv);
        self::assertStringContainsString('جمع', $csv);
        self::assertStringContainsString('1000000', $csv);
    }

    // ── factor e rasmi ──────────────────────────────────────────

    public function test_an_admin_can_issue_an_invoice_for_a_verified_topup_of_an_invoice_verified_user(): void
    {
        $customer = User::factory()->create(['level' => UserLevel::InvoiceVerified, 'invoice_profile' => ['registered_name' => 'شرکت نمونه']]);
        $payment = WalletTopupPayment::query()->create(['user_id' => $customer->id, 'amount_minor' => 2_000_000, 'request_key' => 'k1', 'status' => 'verified', 'track_id' => 't1']);

        $this->actingAs($this->admin())->get(route('panel.finance.index', ['tab' => 'invoices']))
            ->assertOk()->assertSee('صدور فاکتور');

        $this->actingAs($this->admin())
            ->post(route('panel.finance.invoices.issue'), ['kind' => 'wallet_topup', 'source' => $payment->id])
            ->assertRedirect(route('panel.finance.index', ['tab' => 'invoices']))
            ->assertSessionHas('status');

        $invoice = BillingInvoice::query()->where('wallet_topup_payment_id', $payment->id)->firstOrFail();
        self::assertSame(2_000_000, $invoice->amount_minor);

        // Dobare sodur = hamun factor، na yek shomare ye jadid.
        $this->actingAs($this->admin())->post(route('panel.finance.invoices.issue'), ['kind' => 'wallet_topup', 'source' => $payment->id]);
        $this->assertDatabaseCount('billing_invoices', 1);

        // Namayesh/chap e admin.
        config(['billing.seller.name' => 'پرایس‌بورد', 'billing.seller.vat_percent' => 10]);
        $this->actingAs($this->admin())->get(route('panel.finance.invoice', $invoice->id))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('پرایس‌بورد')
            ->assertSee('شرکت نمونه')
            // 2,000,000 shamel e 10% VAT -> VAT = 181,818
            ->assertSee('181,818')
            ->assertSee('2,000,000');
    }

    public function test_issuing_for_a_basic_user_is_refused_with_a_message(): void
    {
        $customer = User::factory()->create(['level' => UserLevel::Basic]);
        $payment = WalletTopupPayment::query()->create(['user_id' => $customer->id, 'amount_minor' => 2_000_000, 'request_key' => 'k2', 'status' => 'verified', 'track_id' => 't2']);

        $this->actingAs($this->admin())
            ->post(route('panel.finance.invoices.issue'), ['kind' => 'wallet_topup', 'source' => $payment->id])
            ->assertSessionHasErrors('invoice');

        $this->assertDatabaseCount('billing_invoices', 0);
    }

    public function test_an_unverified_payment_cannot_be_invoiced(): void
    {
        $customer = User::factory()->create(['level' => UserLevel::InvoiceVerified, 'invoice_profile' => ['registered_name' => 'x']]);
        $payment = WalletTopupPayment::query()->create(['user_id' => $customer->id, 'amount_minor' => 10, 'request_key' => 'k3', 'status' => 'initiated']);

        $this->actingAs($this->admin())
            ->post(route('panel.finance.invoices.issue'), ['kind' => 'wallet_topup', 'source' => $payment->id])
            ->assertSessionHasErrors('invoice');
    }

    // ── sharj e kif pool ────────────────────────────────────────

    public function test_an_admin_can_charge_a_wallet_by_phone_from_the_finance_page(): void
    {
        $customer = User::factory()->create(['phone' => '+989121234567']);

        $this->actingAs($this->admin())
            ->post(route('panel.finance.wallet-adjust'), [
                'user' => '09121234567', 'direction' => 'credit', 'amount' => '1,250,000',
                'note' => 'شارژ تستی', 'idempotency_key' => 'd88b064b-0ebd-4f7d-b092-f2646074e11a',
            ])
            ->assertRedirect(route('panel.finance.index', ['tab' => 'wallet']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('wallet_transactions', ['amount_minor' => 1_250_000, 'direction' => 'credit', 'reason' => 'admin_adjust']);
        self::assertSame(1_250_000, app(WalletAction::class)->forOwner($customer->id)->refresh()->balance_minor);
    }

    public function test_charging_an_unknown_user_or_bad_amount_is_an_error(): void
    {
        $this->actingAs($this->admin())
            ->post(route('panel.finance.wallet-adjust'), [
                'user' => '09120000000', 'direction' => 'credit', 'amount' => '1000',
                'note' => 'x', 'idempotency_key' => 'd88b064b-0ebd-4f7d-b092-f2646074e11b',
            ])
            ->assertSessionHasErrors('user');

        $customer = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('panel.finance.wallet-adjust'), [
                'user' => (string) $customer->id, 'direction' => 'credit', 'amount' => 'abc',
                'note' => 'x', 'idempotency_key' => 'd88b064b-0ebd-4f7d-b092-f2646074e11c',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
