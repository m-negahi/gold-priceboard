<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Persistence\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ledger e kif e pul (P4-02/P4-08): append-only، idempotent، cache =
 * ledger.
 */
final class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private WalletAction $action;

    private WalletQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new WalletAction;
        $this->query = new WalletQuery;
    }

    private function wallet(): Wallet
    {
        return $this->action->forOwner(User::factory()->create()->id);
    }

    public function test_credit_then_debit_keeps_the_chain_consistent(): void
    {
        $wallet = $this->wallet();

        $topup = $this->action->credit($wallet, 1_000_000, 'topup', 'pay-1');
        self::assertSame(1_000_000, $topup->balance_after_minor);

        $call = $this->action->debit($wallet, 30_000, 'api_call', 'req-1');
        self::assertSame(970_000, $call->balance_after_minor);

        self::assertSame(970_000, (int) $wallet->refresh()->balance_minor);
        self::assertTrue($this->query->verifyBalance($wallet));
    }

    public function test_a_debit_beyond_the_balance_is_rejected_and_writes_nothing(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 10_000, 'topup', 'pay-1');

        try {
            $this->action->debit($wallet, 10_001, 'api_call', 'req-1');
            self::fail('bayad WalletException miumad.');
        } catch (WalletException) {
        }

        self::assertSame(10_000, (int) $wallet->refresh()->balance_minor);
        self::assertSame(1, WalletTransaction::query()->count(), 'debit e rad-shode nabayad radif benevise.');
    }

    public function test_a_blocked_wallet_accepts_nothing(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 10_000, 'topup', 'pay-1');
        $this->action->block($wallet);

        $this->expectException(WalletException::class);
        $this->action->debit($wallet, 1_000, 'api_call', 'req-1');
    }

    /** 🔴 Kasr e DOBARE ye yek request mohal ast — asli tarin test e pul. */
    public function test_the_same_reference_is_charged_exactly_once(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 100_000, 'topup', 'pay-1');

        $first = $this->action->debit($wallet, 5_000, 'api_call', 'req-42');
        // Retry e client (timeout khord va dobare ferestad):
        $second = $this->action->debit($wallet, 5_000, 'api_call', 'req-42');

        self::assertSame($first->id, $second->id, 'retry bayad HAMUN tarakonesh ro bar gardune.');
        self::assertSame(95_000, (int) $wallet->refresh()->balance_minor, 'faghat YEK bar kasr.');
        self::assertSame(2, WalletTransaction::query()->count()); // topup + yek debit
    }

    public function test_retry_after_spending_the_entire_balance_returns_the_original_debit(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 100, 'topup', 'seed');
        $first = $this->action->debit($wallet, 100, 'api_call', 'last-call');

        $retry = $this->action->debit($wallet, 100, 'api_call', 'last-call');

        self::assertSame($first->id, $retry->id);
        self::assertFalse($retry->wasRecentlyCreated);
        self::assertSame(0, $wallet->refresh()->balance_minor);
        self::assertSame(2, WalletTransaction::query()->count());
    }

    public function test_credit_is_idempotent_too(): void
    {
        $wallet = $this->wallet();

        $this->action->credit($wallet, 50_000, 'topup', 'pay-9');
        $this->action->credit($wallet, 50_000, 'topup', 'pay-9');

        self::assertSame(50_000, (int) $wallet->refresh()->balance_minor, 'sharj e dobare ye yek pardakht mamnu'.'\'e.');
    }

    public function test_refund_restores_the_balance_and_is_idempotent(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 100_000, 'topup', 'pay-1');
        $this->action->debit($wallet, 5_000, 'api_call', 'req-7');

        $this->action->refund($wallet, 5_000, 'req-7');
        $this->action->refund($wallet, 5_000, 'req-7');

        self::assertSame(100_000, (int) $wallet->refresh()->balance_minor);
        self::assertTrue($this->query->verifyBalance($wallet));
    }

    public function test_garbage_inputs_are_rejected(): void
    {
        $wallet = $this->wallet();

        try {
            $this->action->credit($wallet, 0, 'topup', 'x');
            self::fail('mablagh e sefr bayad rad mishod.');
        } catch (WalletException) {
        }

        try {
            $this->action->credit($wallet, 100, 'topup', '  ');
            self::fail('reference e khali bayad rad mishod.');
        } catch (WalletException) {
        }

        self::assertSame(0, WalletTransaction::query()->count());
    }

    public function test_verify_balance_catches_a_drifted_cache(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 10_000, 'topup', 'pay-1');

        // Shabih-sazi ye bug: cache dasti kharab mishe.
        $wallet->forceFill(['balance_minor' => 99_999])->save();

        self::assertFalse($this->query->verifyBalance($wallet), 'ekhtelaf e cache/ledger bayad DIDE she.');
        self::assertSame(10_000, $this->query->balanceFromLedger($wallet), 'ledger manba'.'\' e haghighat ast.');
    }

    public function test_the_ledger_lists_newest_first(): void
    {
        $wallet = $this->wallet();
        $this->action->credit($wallet, 10_000, 'topup', 'pay-1');
        $this->action->debit($wallet, 1_000, 'api_call', 'req-1');

        $ledger = $this->query->ledger($wallet);

        self::assertCount(2, $ledger);
        self::assertSame('debit', $ledger[0]['direction']);
        self::assertSame('req-1', $ledger[0]['reference']);
    }
}
