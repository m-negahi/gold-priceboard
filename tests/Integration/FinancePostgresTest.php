<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Application\Actions\PurchasePlanAction;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Application\Actions\TopupPaymentAction;
use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Tests\Support\Fakes\FakePaymentGateway;
use Tests\TestCase;

/**
 * Faghat ru database e disposable e PostgreSQL; har test migrate:fresh mizane.
 * PB_TEST_PG_DATABASE bayad name e mojaza ba prefix priceboard_verify_ bashe.
 */
final class FinancePostgresTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        $database = getenv('PB_TEST_PG_DATABASE');
        if ($database === false || $database === '') {
            self::markTestSkipped('PB_TEST_PG_DATABASE baraye test e izole PostgreSQL lazeme');
        }
        if (! preg_match('/^priceboard_verify_[a-z0-9_]+$/', $database)) {
            throw new RuntimeException('PB_TEST_PG_DATABASE bayad database e disposable e priceboard_verify_* bashe');
        }

        parent::setUp();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $database = (string) getenv('PB_TEST_PG_DATABASE');
        $actualDatabase = (string) $app['config']->get('database.connections.pgsql.database');
        if ($database === $actualDatabase || ! str_starts_with($database, 'priceboard_verify_')) {
            throw new RuntimeException('PostgreSQL test database e amn nist');
        }
        $app['config']->set('database.connections.pgsql.database', $database);
        $app['config']->set('database.default', 'pgsql');
        $app['db']->purge('pgsql');

        return $app;
    }

    public function test_posted_journal_rejects_late_lines_mutation_and_deletion(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletAction::class);
        $transaction = $wallets->credit($wallets->forOwner($user->id), 2_500, 'topup', 'pg-guard');
        $journalId = (int) DB::table('finance_journals')->where('source_key', 'wallet:'.$transaction->id)->value('id');
        $accountId = (int) DB::table('finance_accounts')->where('code', '1000')->value('id');
        $original = DB::table('finance_journal_lines')->where('finance_journal_id', $journalId)->orderBy('id')->first();

        $this->assertRejected(static fn (): int => DB::table('finance_journal_lines')->insertGetId([
            'finance_journal_id' => $journalId, 'finance_account_id' => $accountId,
            'debit_minor' => 1, 'credit_minor' => 0,
        ]));
        $this->assertRejected(static fn (): int => DB::table('finance_journal_lines')->where('id', $original->id)->update(['debit_minor' => 999]));
        $this->assertRejected(static fn (): int => DB::table('finance_journal_lines')->where('id', $original->id)->delete());
        $this->assertRejected(static fn (): int => DB::table('finance_journals')->where('id', $journalId)->update(['memo' => 'tamper']));
        $this->assertRejected(static fn (): int => DB::table('finance_journals')->where('id', $journalId)->delete());
        self::assertSame(2, DB::table('finance_journal_lines')->where('finance_journal_id', $journalId)->count());
    }

    public function test_unbalanced_and_negative_journals_cannot_commit(): void
    {
        $accountId = (int) DB::table('finance_accounts')->where('code', '1000')->value('id');
        $oppositeId = (int) DB::table('finance_accounts')->where('code', '2100')->value('id');

        $this->assertRejected(static function () use ($accountId): void {
            DB::transaction(static function () use ($accountId): void {
                $journalId = DB::table('finance_journals')->insertGetId([
                    'source_key' => 'pg-unbalanced', 'memo' => 'bad',
                    'occurred_at' => now(), 'created_at' => now(),
                ]);
                DB::table('finance_journal_lines')->insert([
                    'finance_journal_id' => $journalId, 'finance_account_id' => $accountId,
                    'debit_minor' => 100, 'credit_minor' => 0,
                ]);
            });
        });

        $this->assertRejected(static function () use ($accountId, $oppositeId): void {
            DB::transaction(static function () use ($accountId, $oppositeId): void {
                $journalId = DB::table('finance_journals')->insertGetId([
                    'source_key' => 'pg-negative', 'memo' => 'bad',
                    'occurred_at' => now(), 'created_at' => now(),
                ]);
                DB::table('finance_journal_lines')->insert([
                    ['finance_journal_id' => $journalId, 'finance_account_id' => $accountId, 'debit_minor' => -100, 'credit_minor' => 0],
                    ['finance_journal_id' => $journalId, 'finance_account_id' => $oppositeId, 'debit_minor' => 0, 'credit_minor' => 100],
                ]);
            });
        });
        self::assertSame(0, DB::table('finance_journals')->count());
    }

    public function test_verified_topup_posts_balanced_wallet_liability_and_gateway_clearing(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['level' => 'invoice_verified', 'invoice_profile' => ['registered_name' => 'PG Buyer']])->save();
        $fake = new FakePaymentGateway(15_000);
        $this->app->instance(PaymentGatewayPort::class, $fake);
        $topups = app(TopupPaymentAction::class);
        $started = $topups->start($user->id, 15_000, '75bdc1fb-63dc-4254-aee1-4d061728712c', 'https://example.test/callback');
        $verified = $topups->verify((string) $started['payment']->track_id);
        self::assertSame('verified', $verified->status);
        self::assertSame($verified->id, $topups->verify((string) $started['payment']->track_id)->id);
        self::assertSame(1, $fake->verifyCount);
        self::assertSame(15_000, (int) DB::table('wallets')->where('owner_id', $user->id)->value('balance_minor'));
        self::assertSame(1, DB::table('finance_journals')->count());
        $balance = DB::table('finance_journal_lines')->selectRaw('SUM(debit_minor) - SUM(credit_minor) as balance')->value('balance');
        self::assertSame(0, (int) $balance);
        $credits = DB::table('finance_journal_lines as lines')->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.finance_account_id')
            ->where('accounts.code', '2100')->value('lines.credit_minor');
        self::assertSame(15_000, (int) $credits);
        $invoice = DB::table('billing_invoices')->first();
        self::assertNotNull($invoice);
        self::assertStringContainsString('PG Buyer', (string) $invoice->buyer_snapshot);
        $this->assertRejected(static fn (): int => DB::table('billing_invoices')->where('id', $invoice->id)->update(['amount_minor' => 1]));
        $this->assertRejected(static fn (): int => DB::table('billing_invoices')->where('id', $invoice->id)->delete());
    }

    public function test_nested_plan_purchase_posts_and_commits_balanced_journal(): void
    {
        $user = User::factory()->create();
        $client = (new ApiClientAction)->create($user->id, 'pg-site')['client'];
        $plan = ApiPlan::query()->create([
            'key' => 'pg-plan', 'name' => 'PG Plan', 'price_minor' => 5_000,
            'duration_days' => 30, 'calls_included' => 10, 'calls_per_day' => 2,
            'cost_per_call_minor' => 100, 'is_active' => true,
        ]);
        $wallets = app(WalletAction::class);
        $wallet = $wallets->forOwner($user->id);
        $wallets->credit($wallet, 10_000, 'topup', 'pg-seed');

        $subscription = app(PurchasePlanAction::class)->purchase($client, $plan, 'pg-purchase');
        self::assertSame(5_000, $wallet->refresh()->balance_minor);
        self::assertSame(5_000, $subscription->price_minor);
        $journals = DB::table('finance_journal_lines')->groupBy('finance_journal_id')
            ->selectRaw('finance_journal_id, SUM(debit_minor) as debits, SUM(credit_minor) as credits')->get();
        self::assertCount(2, $journals);
        foreach ($journals as $journal) {
            self::assertSame((int) $journal->debits, (int) $journal->credits);
        }
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('PostgreSQL bayad amal e na-motabar ra rad mikard');
        } catch (PDOException|QueryException) {
            self::assertTrue(true);
        }
    }
}
