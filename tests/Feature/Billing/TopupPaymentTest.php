<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Application\Actions\TopupPaymentAction;
use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Billing\Infrastructure\Payments\ZibalGateway;
use App\Modules\Finance\Application\Actions\SettleGatewayPaymentAction;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\TestCase;

final class TopupPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_port_is_bound_to_the_zibal_adapter(): void
    {
        self::assertInstanceOf(ZibalGateway::class, app(PaymentGatewayPort::class));
    }

    public function test_verified_payment_credits_once_and_issues_an_immutable_invoice_snapshot(): void
    {
        $user = User::factory()->named('Buyer')->create();
        $user->forceFill([
            'level' => 'invoice_verified',
            'invoice_profile' => ['registered_name' => 'Registered Buyer', 'economic_code' => '1234'],
        ])->save();
        $gateway = new FakePaymentGateway(10_000);
        $this->app->instance(PaymentGatewayPort::class, $gateway);
        $payments = app(TopupPaymentAction::class);
        $firstStart = $payments->start($user->id, 10_000, 'request-1', 'https://example.test/callback');
        $secondStart = $payments->start($user->id, 10_000, 'request-1', 'https://example.test/callback');

        $first = $payments->verify($firstStart['payment']->track_id);
        $second = $payments->verify($firstStart['payment']->track_id);

        self::assertSame($first->id, $second->id);
        self::assertSame($firstStart['url'], $secondStart['url']);
        self::assertSame(1, $gateway->requestCount);
        self::assertSame(1, $gateway->verifyCount);
        self::assertSame(10_000, app(WalletQuery::class)->findForOwner($user->id)->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('billing_invoices', ['user_id' => $user->id, 'wallet_topup_payment_id' => $first->id, 'kind' => 'wallet_topup', 'amount_minor' => 10_000]);
        $snapshot = DB::table('billing_invoices')->value('buyer_snapshot');
        self::assertStringContainsString('Registered Buyer', (string) $snapshot);
    }

    public function test_wrong_verified_amount_never_credits_or_issues_invoice(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'level' => 'invoice_verified',
            'invoice_profile' => ['registered_name' => 'Buyer'],
        ])->save();
        $this->app->instance(PaymentGatewayPort::class, new FakePaymentGateway(9_000));
        $payments = app(TopupPaymentAction::class);
        $started = $payments->start($user->id, 10_000, 'request-1', 'https://example.test/callback');

        $payment = $payments->verify($started['payment']->track_id);

        self::assertSame('mismatch', $payment->status);
        self::assertNull(app(WalletQuery::class)->findForOwner($user->id));
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('billing_invoices', 0);
    }

    public function test_verified_user_without_invoice_profile_receives_credit_without_invoice(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['level' => 'invoice_verified', 'invoice_profile' => null])->save();
        $this->app->instance(PaymentGatewayPort::class, new FakePaymentGateway(10_000));
        $payments = app(TopupPaymentAction::class);
        $started = $payments->start($user->id, 10_000, 'request-1', 'https://example.test/callback');

        $payments->verify($started['payment']->track_id);

        self::assertSame(10_000, app(WalletQuery::class)->findForOwner($user->id)->balance_minor);
        $this->assertDatabaseCount('billing_invoices', 0);
    }

    public function test_bank_settlement_moves_verified_amount_out_of_gateway_clearing_once(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->app->instance(PaymentGatewayPort::class, new FakePaymentGateway(10_000));
        $payments = app(TopupPaymentAction::class);
        $started = $payments->start($user->id, 10_000, 'request-1', 'https://example.test/callback');
        $payment = $payments->verify($started['payment']->track_id);

        $first = app(SettleGatewayPaymentAction::class)->execute($payment->id, $admin->id, 'bank-1');
        $second = app(SettleGatewayPaymentAction::class)->execute($payment->id, $admin->id, 'bank-1');

        self::assertSame($first, $second);
        $this->assertDatabaseHas('finance_journals', ['id' => $first, 'source_key' => 'settlement:payment:'.$payment->id]);
        $bankDebit = DB::table('finance_journal_lines as lines')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.finance_account_id')
            ->where('lines.finance_journal_id', $first)->where('accounts.code', '1000')->value('lines.debit_minor');
        self::assertSame(10_000, (int) $bankDebit);
        $this->expectException(InvalidArgumentException::class);
        app(SettleGatewayPaymentAction::class)->execute($payment->id, $admin->id, 'bank-2');
    }

    public function test_customer_billing_pages_and_invoice_are_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['level' => 'invoice_verified', 'invoice_profile' => ['registered_name' => 'Buyer']])->save();
        $other = User::factory()->create();
        $this->app->instance(PaymentGatewayPort::class, new FakePaymentGateway(10_000));
        $payments = app(TopupPaymentAction::class);
        $started = $payments->start($user->id, 10_000, 'request-owner', 'https://example.test/callback');
        $payments->verify($started['payment']->track_id);
        $invoiceId = (int) DB::table('billing_invoices')->value('id');

        foreach (['dashboard', 'sites', 'subscriptions', 'wallet', 'usage'] as $section) {
            $this->actingAs($user)->get(route('account.billing.'.$section))->assertOk();
        }
        $this->actingAs($user)->get(route('account.billing.invoice', $invoiceId))
            ->assertOk()->assertSee('رسید افزایش اعتبار کیف پول')->assertSee('10,000');
        $this->actingAs($other)->get(route('account.billing.invoice', $invoiceId))->assertNotFound();
    }

    public function test_gateway_already_verified_reconciles_after_local_wallet_failure(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($user->id);
        $wallets->block($wallet);
        config()->set('billing.zibal.merchant', 'test-merchant');
        Http::preventStrayRequests();
        $verifyCalls = 0;
        $paymentId = null;
        Http::fake(static function ($request) use (&$verifyCalls, &$paymentId) {
            if (str_ends_with($request->url(), '/v1/request')) {
                return Http::response(['result' => 100, 'trackId' => 123456]);
            }
            if (str_ends_with($request->url(), '/v1/verify')) {
                $verifyCalls++;

                return Http::response($verifyCalls === 1
                    ? ['result' => 100, 'status' => 1, 'amount' => 10_000, 'orderId' => (string) $paymentId]
                    : ['result' => 201]);
            }

            return Http::response([
                'result' => 100, 'status' => 1, 'amount' => 10_000,
                'trackId' => '123456', 'orderId' => (string) $paymentId,
            ]);
        });
        $payments = app(TopupPaymentAction::class);
        $started = $payments->start($user->id, 10_000, 'request-recovery', 'https://example.test/callback');
        $paymentId = $started['payment']->id;

        try {
            $payments->verify('123456');
            self::fail('blocked wallet bayad local commit ra rad mikard');
        } catch (WalletException) {
            $this->assertDatabaseHas('wallet_topup_payments', ['id' => $paymentId, 'status' => 'pending']);
        }
        $wallets->unblock($wallet);
        $verified = $payments->verify('123456');
        self::assertSame('verified', $verified->status);
        self::assertSame(2, $verifyCalls);
        self::assertSame(10_000, $wallet->refresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
