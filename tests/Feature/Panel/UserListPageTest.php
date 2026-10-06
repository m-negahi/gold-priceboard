<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Domain\Enums\UserStatus;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Panel\Support\Navigation;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-02 — lista ye karbaran tu panel: search ba mobile/esm، filter e
 * naghsh/vaziat/level، safhe-bandi e server-side.
 */
final class UserListPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->named('ادمین اصلی')->create(['phone' => '+989120000000']);
    }

    private function makeCustomer(string $phone, ?string $name = null): User
    {
        return User::factory()->create(['phone' => $phone, 'name' => $name]);
    }

    public function test_guests_go_to_login_and_customers_get_404(): void
    {
        $this->get(route('panel.users.index'))->assertRedirect(route('login'));

        $this->actingAs($this->makeCustomer('+989121111111'))
            ->get(route('panel.users.index'))
            ->assertNotFound();
    }

    public function test_the_list_shows_users_with_local_phone_role_and_status(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCustomer('+989121111111', 'سارا مشتری');

        $response = $this->actingAs($admin)->get(route('panel.users.index'));

        $response->assertOk()
            ->assertSee('09121111111')
            ->assertSee('سارا مشتری')
            ->assertSee('ادمین اصلی')
            ->assertSee('data-user-row', false);
    }

    public function test_search_by_mobile_accepts_local_and_persian_digit_forms(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCustomer('+989121111111', 'اول');
        $this->makeCustomer('+989352222222', 'دوم');

        // `0935…` (ba sefr e mahalli) bayad `+98935…` ro peyda kone.
        $this->actingAs($admin)->get(route('panel.users.index', ['q' => '0935222']))
            ->assertOk()->assertSee('09352222222')->assertDontSee('09121111111');

        // Raghame Farsi (ghabl az middleware ham normalize mishe).
        $this->actingAs($admin)->get(route('panel.users.index', ['q' => '۹۱۲۱۱']))
            ->assertOk()->assertSee('09121111111')->assertDontSee('09352222222');
    }

    public function test_search_by_name(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCustomer('+989121111111', 'سارا رضایی');
        $this->makeCustomer('+989352222222', 'علی محمدی');

        $this->actingAs($admin)->get(route('panel.users.index', ['q' => 'رضایی']))
            ->assertOk()->assertSee('09121111111')->assertDontSee('09352222222');
    }

    public function test_filter_by_role(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCustomer('+989121111111');

        $this->actingAs($admin)->get(route('panel.users.index', ['role' => 'admin']))
            ->assertOk()->assertSee('09120000000')->assertDontSee('09121111111');

        $this->actingAs($admin)->get(route('panel.users.index', ['role' => 'customer']))
            ->assertOk()->assertSee('09121111111')->assertDontSee('09120000000');
    }

    public function test_filter_by_status_and_level(): void
    {
        $admin = $this->makeAdmin();
        $off = $this->makeCustomer('+989121111111');
        $off->forceFill(['status' => UserStatus::Disabled])->save();
        $vip = $this->makeCustomer('+989352222222');
        $vip->forceFill(['level' => UserLevel::InvoiceVerified])->save();

        $this->actingAs($admin)->get(route('panel.users.index', ['status' => 'disabled']))
            ->assertOk()->assertSee('09121111111')->assertDontSee('09352222222');

        $this->actingAs($admin)->get(route('panel.users.index', ['level' => 'invoice_verified']))
            ->assertOk()->assertSee('09352222222')->assertDontSee('09121111111');
    }

    public function test_an_unknown_filter_value_is_rejected_not_silently_ignored(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('panel.users.index', ['status' => 'banana']))
            ->assertSessionHasErrors('status');
    }

    public function test_the_list_shows_wallet_balance_in_toman_and_site_count(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('+989121111111');

        $wallets = app(WalletAction::class);
        // 1,234,560 rial = 123,456 toman
        $wallets->credit($wallets->forOwner($customer->id), 1_234_560, 'topup', 'seed-1');
        app(ApiClientAction::class)->create($customer->id, 'site-a');
        app(ApiClientAction::class)->create($customer->id, 'site-b');

        $this->actingAs($admin)->get(route('panel.users.index', ['q' => '09121111111']))
            ->assertOk()
            ->assertSee('123,456')
            ->assertSee('data-sites="2"', false);
    }

    public function test_the_list_is_paginated_server_side(): void
    {
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 30; $i++) {
            $this->makeCustomer(sprintf('+98912%07d', 3_000_000 + $i));
        }

        $first = $this->actingAs($admin)->get(route('panel.users.index'))->assertOk();
        // 31 karbar، 25 dar safhe -> safhe ye dovvom vojud dare.
        $this->assertSame(25, substr_count($first->getContent(), 'data-user-row'));

        $second = $this->actingAs($admin)->get(route('panel.users.index', ['page' => 2]))->assertOk();
        $this->assertSame(6, substr_count($second->getContent(), 'data-user-row'));
    }

    public function test_the_customers_sidebar_item_is_enabled(): void
    {
        $item = collect(Navigation::groups())->pluck('items')->flatten(1)->firstWhere('key', 'customers');

        $this->assertSame('panel.users.index', $item['route']);
    }
}
