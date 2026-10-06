<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Actions\CreateSiteAction;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\TestCase;

/**
 * Masir haye HTTP e panel e moshtari (`routes/billing.php` → AccountBillingController):
 * sabt e site، kharid e eshterak، shoru' e topup va callback e dargah.
 *
 * Hich network-i be dargah e vaghei zade nemishe — `FakePaymentGateway`.
 */
final class AccountBillingHttpTest extends TestCase
{
    use RefreshDatabase;

    private const PURCHASE_KEY = '4a6ad1bc-9a7f-4d66-a7e5-0c0b3b0d9c11';

    private const TOPUP_KEY = '4a6ad1bc-9a7f-4d66-a7e5-0c0b3b0d9c12';

    // ── POST /account/sites ─────────────────────────────────────

    public function test_storing_a_site_creates_a_pending_client_and_flashes_credentials_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.billing.sites.store'), [
            'name' => 'سایت من', 'website_url' => 'https://example.com',
        ]);

        $response->assertRedirect(route('account.billing.sites'))->assertSessionHas('site_credentials');
        $credentials = session('site_credentials');
        self::assertIsString($credentials['client_id']);
        self::assertIsString($credentials['secret']);
        self::assertNotSame('', $credentials['secret']);
        $this->assertDatabaseHas('api_clients', [
            'user_id' => $user->id, 'client_id' => $credentials['client_id'],
            'status' => 'pending', 'website_url' => 'https://example.com',
        ]);
        $this->assertDatabaseHas('tickets', ['user_id' => $user->id, 'type' => 'site_approval', 'status' => 'open']);

        // Flash faghat YEK bar: safhe ye ba'd az redirect ramz ro neshun mide، refresh e ba'di na.
        $this->get(route('account.billing.sites'))->assertOk()
            ->assertSee('data-site-credentials', false)->assertSee($credentials['secret']);
        $this->get(route('account.billing.sites'))->assertOk()
            ->assertDontSee('data-site-credentials', false)->assertDontSee($credentials['secret']);
    }

    public function test_storing_a_site_with_an_invalid_url_fails_validation_without_a_client(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('account.billing.sites'))
            ->post(route('account.billing.sites.store'), ['name' => 'بد', 'website_url' => 'ftp://example.com'])
            ->assertRedirect(route('account.billing.sites'))->assertSessionHasErrors('website_url');

        $this->assertDatabaseCount('api_clients', 0);
        $this->assertDatabaseCount('tickets', 0);
    }

    // ── POST /account/subscriptions ─────────────────────────────

    public function test_purchasing_a_plan_debits_the_wallet_and_flashes_a_success_toast(): void
    {
        $user = User::factory()->create();
        $site = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = $this->plan();
        $wallet = app(WalletAction::class)->forOwner($user->id);
        app(WalletAction::class)->credit($wallet, 5_000, 'topup', 'seed');

        $this->actingAs($user)->post(route('account.billing.subscriptions.purchase'), [
            'site_id' => $site->id, 'plan_id' => $plan->id, 'purchase_key' => self::PURCHASE_KEY,
        ])->assertRedirect(route('account.billing.subscriptions'))->assertSessionHas('toast.kind', 'success');

        $this->assertDatabaseHas('site_subscriptions', [
            'api_client_id' => $site->id, 'api_plan_id' => $plan->id, 'purchase_reference' => self::PURCHASE_KEY,
        ]);
        self::assertSame(3_000, $wallet->refresh()->balance_minor);
    }

    public function test_purchasing_for_a_pending_site_is_rejected_without_a_debit(): void
    {
        $user = User::factory()->create();
        $created = app(CreateSiteAction::class)->execute($user->id, 'Pending', 'https://pending.example');
        $plan = $this->plan();
        $wallet = app(WalletAction::class)->forOwner($user->id);
        app(WalletAction::class)->credit($wallet, 5_000, 'topup', 'seed');

        $this->actingAs($user)->from(route('account.billing.subscriptions'))
            ->post(route('account.billing.subscriptions.purchase'), [
                'site_id' => $created['client']->id, 'plan_id' => $plan->id, 'purchase_key' => self::PURCHASE_KEY,
            ])->assertRedirect(route('account.billing.subscriptions'))->assertSessionHasErrors('plan_id');

        $this->assertDatabaseCount('site_subscriptions', 0);
        self::assertSame(5_000, $wallet->refresh()->balance_minor);
    }

    public function test_insufficient_wallet_balance_rejects_the_purchase(): void
    {
        $user = User::factory()->create();
        $site = (new ApiClientAction)->create($user->id, 'site')['client'];
        $plan = $this->plan();

        $this->actingAs($user)->from(route('account.billing.subscriptions'))
            ->post(route('account.billing.subscriptions.purchase'), [
                'site_id' => $site->id, 'plan_id' => $plan->id, 'purchase_key' => self::PURCHASE_KEY,
            ])->assertRedirect(route('account.billing.subscriptions'))->assertSessionHasErrors('plan_id');

        $this->assertDatabaseCount('site_subscriptions', 0);
    }

    // ── POST /account/wallet/topup + GET /payments/zibal/callback ───

    public function test_topup_redirects_to_the_gateway_and_the_callback_credits_once(): void
    {
        $user = User::factory()->create();
        $gateway = new FakePaymentGateway(10_000);
        $this->app->instance(PaymentGatewayPort::class, $gateway);

        $this->actingAs($user)->post(route('account.billing.topup.start'), [
            'amount_rial' => '10,000', 'request_key' => self::TOPUP_KEY,
        ])->assertRedirect('https://gateway.zibal.ir/start/123456');

        $this->assertDatabaseHas('wallet_topup_payments', [
            'user_id' => $user->id, 'amount_minor' => 10_000, 'track_id' => '123456', 'status' => 'pending',
        ]);
        self::assertSame(1, $gateway->requestCount);

        // Callback e dargah session e karbar ro lazem nadare (browser az dargah bar migarde).
        $this->get(route('account.billing.topup.callback', ['trackId' => '123456']))
            ->assertRedirect(route('account.billing.wallet'))->assertSessionHas('toast.kind', 'success');
        // Bar e dovom (refresh e callback): hamun natije، bedun e credit e dobare.
        $this->get(route('account.billing.topup.callback', ['trackId' => '123456']))
            ->assertRedirect(route('account.billing.wallet'))->assertSessionHas('toast.kind', 'success');

        self::assertSame(1, $gateway->verifyCount);
        self::assertSame(10_000, app(WalletQuery::class)->findForOwner($user->id)->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_topup_payments', ['track_id' => '123456', 'status' => 'verified']);
    }

    public function test_topup_below_the_minimum_amount_is_rejected_before_the_gateway(): void
    {
        $user = User::factory()->create();
        $gateway = new FakePaymentGateway(1_000);
        $this->app->instance(PaymentGatewayPort::class, $gateway);

        $this->actingAs($user)->from(route('account.billing.wallet'))
            ->post(route('account.billing.topup.start'), ['amount_rial' => '1,000', 'request_key' => self::TOPUP_KEY])
            ->assertRedirect(route('account.billing.wallet'))->assertSessionHasErrors('amount_rial');

        self::assertSame(0, $gateway->requestCount);
        $this->assertDatabaseCount('wallet_topup_payments', 0);
    }

    public function test_callback_with_a_mismatched_amount_reports_an_error_and_never_credits(): void
    {
        $user = User::factory()->create();
        $this->app->instance(PaymentGatewayPort::class, new FakePaymentGateway(9_000));
        $this->actingAs($user)->post(route('account.billing.topup.start'), [
            'amount_rial' => '10,000', 'request_key' => self::TOPUP_KEY,
        ]);

        $this->get(route('account.billing.topup.callback', ['trackId' => '123456']))
            ->assertRedirect(route('account.billing.wallet'))->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('wallet_topup_payments', ['track_id' => '123456', 'status' => 'mismatch']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_callback_for_an_unknown_track_id_is_rejected(): void
    {
        $this->get(route('account.billing.topup.callback', ['trackId' => '999']))
            ->assertRedirect(route('account.billing.wallet'))->assertSessionHasErrors('payment');
    }

    private function plan(): ApiPlan
    {
        return ApiPlan::query()->create([
            'key' => 'basic', 'name' => 'پایه', 'price_minor' => 2_000,
            'duration_days' => 30, 'calls_included' => 10, 'calls_per_day' => 10,
            'cost_per_call_minor' => 500, 'is_active' => true,
        ]);
    }
}
