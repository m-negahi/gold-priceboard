<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P6-07 — negahban e APPEND-ONLY e `wallet_transactions` dar sath e DB.
 *
 * Ghanun e neveshtari (`UPDATED_AT = null`) jolo ye yek `DB::table()->
 * update()` ya yek `->delete()` e bi-deghat ro nemigire؛ trigger e DB
 * migire — har masir، har code e ayande، hatta psql e dasti.
 *
 * Test ha ru SQLite e test ejra mishan (trigger e `RAISE(ABORT)`)؛ noskhe
 * ye Postgres hamun gharardad ro dare (migration) va ru dev ba SQL e
 * no-op tu transaction e rollback-shode check shod.
 */
final class WalletAppendOnlyGuardTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $wallets = app(WalletAction::class);
        $this->wallet = $wallets->forOwner(User::factory()->create()->id);
        $wallets->credit($this->wallet, 10_000, 'topup', 'pay-1');
    }

    public function test_inserting_through_the_action_still_works(): void
    {
        app(WalletAction::class)->debit($this->wallet, 1_000, 'api_call', 'req-1');

        $this->assertSame(2, WalletTransaction::query()->count());
        $this->assertTrue(app(WalletQuery::class)->verifyBalance($this->wallet));
    }

    public function test_a_raw_update_is_refused(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('wallet_transactions')->update(['amount_minor' => 1]);
    }

    public function test_an_eloquent_update_is_refused_and_nothing_changes(): void
    {
        /** @var WalletTransaction $tx */
        $tx = WalletTransaction::query()->sole();

        try {
            $tx->forceFill(['amount_minor' => 999_999])->save();
            $this->fail('update bayad rad mishod.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame(10_000, (int) DB::table('wallet_transactions')->value('amount_minor'));
    }

    public function test_a_delete_is_refused(): void
    {
        try {
            WalletTransaction::query()->delete();
            $this->fail('delete bayad rad mishod.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /** Pak kardan e karbar/kif ba ledger ham cascade ro mibande — tarikhche ye pul pak nemishe. */
    public function test_deleting_the_owner_cannot_cascade_the_ledger_away(): void
    {
        try {
            User::query()->whereKey($this->wallet->owner_id)->delete();
            $this->fail('cascade e ledger bayad rad mishod.');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/append-only|FOREIGN KEY constraint failed/', $e->getMessage());
        }

        $this->assertSame(1, WalletTransaction::query()->count());
    }
}
