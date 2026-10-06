<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-03 — safhe ye yek karbar: profile، wallet (mande + ledger)، site ha
 * ba vaziat va plan.
 */
final class UserDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create(['phone' => '+989120000000']);
    }

    public function test_customers_cannot_open_a_user_page(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('panel.users.show', $customer->id))->assertNotFound();
    }

    public function test_an_unknown_user_is_404(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('panel.users.show', 99_999))->assertNotFound();
    }

    public function test_the_profile_section(): void
    {
        $customer = User::factory()->named('سارا رضایی')->create([
            'phone' => '+989121111111',
            'email' => 'sara@example.com',
            'customer_type' => 'legal',
            'national_code' => '10101010101',
        ]);

        $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))
            ->assertOk()
            ->assertSee('سارا رضایی')
            ->assertSee('09121111111')
            ->assertSee('sara@example.com')
            ->assertSee('حقوقی')
            ->assertSee('10101010101')
            ->assertSee('پایه')
            ->assertSee('فعال');
    }

    public function test_the_wallet_section_shows_balance_and_ledger_newest_first(): void
    {
        $customer = User::factory()->create();
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($customer->id);
        $wallets->credit($wallet, 5_000_000, 'topup', 'pay-1');
        $wallets->debit($wallet, 1_230, 'api_call', 'req-1');

        $response = $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))->assertOk();

        // 4,998,770 rial = 499,877 toman
        $response->assertSee('499,877')
            ->assertSee('شارژ')
            ->assertSee('فراخوانی API');

        $html = (string) $response->getContent();
        $this->assertSame(2, substr_count($html, 'data-ledger-row'));
        // Jadid tarin (kasr) aval.
        $this->assertLessThan(strpos($html, 'pay-1'), strpos($html, 'req-1'));
    }

    public function test_a_user_without_a_wallet_shows_an_empty_state(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))
            ->assertOk()
            ->assertSee('هنوز کیف پولی ساخته نشده');
    }

    public function test_the_sites_section_shows_status_and_plan(): void
    {
        $customer = User::factory()->create();
        $plan = ApiPlan::query()->create([
            'key' => 'p1200', 'name' => 'پلن ۱۲۰۰', 'price_minor' => 1_000_000,
            'calls_per_day' => 1200, 'cost_per_call_minor' => 10,
        ]);

        $clients = app(ApiClientAction::class);
        ['client' => $on, 'secret' => $secret] = $clients->create($customer->id, 'سایت طلا');
        $on->forceFill(['website_url' => 'https://tala.example', 'plan_id' => $plan->id])->save();
        $off = $clients->create($customer->id, 'سایت قدیمی')['client'];
        $clients->deactivate($off);

        $response = $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))->assertOk();

        $response->assertSee('سایت طلا')
            ->assertSee('https://tala.example')
            ->assertSee($on->client_id)
            ->assertSee('1200')
            ->assertSee('سایت قدیمی');

        $html = (string) $response->getContent();
        $this->assertSame(2, substr_count($html, 'data-site-row'));
        $this->assertStringContainsString('data-site-active="0"', $html);
        // 🔴 Secret hich vaght tu safhe nist.
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString($on->secret_encrypted, $html);
    }

    public function test_another_users_sites_and_wallet_do_not_leak(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        app(ApiClientAction::class)->create($other->id, 'سایت دیگری');
        $wallets = app(WalletAction::class);
        $wallets->credit($wallets->forOwner($other->id), 7_770, 'topup', 'other-pay');

        $this->actingAs($this->makeAdmin())->get(route('panel.users.show', $customer->id))
            ->assertOk()
            ->assertDontSee('سایت دیگری')
            ->assertDontSee('other-pay');
    }

    public function test_the_list_links_to_the_detail_page(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($this->makeAdmin())->get(route('panel.users.index'))
            ->assertOk()
            ->assertSee(route('panel.users.show', $customer->id), false);
    }
}
