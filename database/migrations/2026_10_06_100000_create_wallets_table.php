<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ wallets + wallet_transactions — billing e Phase 4                  ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST: kif e pul e customer. Har masraf e API kasr mishe، sharj
 * credit mishe.
 *
 * 🔴 `wallet_transactions` APPEND-ONLY ast — hich `update`، hich `delete`
 *   (ghanun e proje). `balance_minor` e `wallets` faghat CACHE ast؛
 *   manba' e haghighat LEDGER ast (sum e credit - debit). Ekhtelaf e in
 *   do ya'ni bug — `WalletQuery::verifyBalance()` baraye kashf esh.
 *
 * `unique(wallet_id, reason, reference)`: kasr e DOBARE ye yek request
 *   mohal e (idempotency) — `reference` id e request/pardakht ast.
 *
 * `balance_after_minor` ru har radif: ledger bedun e replay ghabl e
 *   khundan ast («mande ba'd az in tarakonesh chand bud»).
 */
return new class extends Migration
{
    private const MESSAGE = 'wallet_transactions append-only ast: UPDATE/DELETE mamnu';

    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // Minor unit (IRR -> rial). Hamun ghanun e «hich float» e gheymat.
            $table->bigInteger('balance_minor')->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            // Fe'lan yek kif be ezaye har (user, arz).
            $table->unique(['owner_id', 'currency']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('direction', 6); // credit | debit
            // Hamishe MOSBAT — jahat tu `direction` ast.
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('reason', 32);    // topup | api_call | adjustment | refund
            $table->string('reference', 64); // request id — idempotency
            $table->jsonb('meta')->nullable();
            // Append-only: faghat `created_at`، hich `updated_at`.
            $table->timestampTz('created_at');

            $table->unique(['wallet_id', 'reason', 'reference']);
            $table->index(['wallet_id', 'created_at']);
        });

        match (DB::getDriverName()) {
            'pgsql' => $this->upPostgres(),
            'sqlite' => $this->upSqlite(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS wallet_transactions_no_truncate ON wallet_transactions;
                DROP TRIGGER IF EXISTS wallet_transactions_append_only ON wallet_transactions;
                DROP FUNCTION IF EXISTS wallet_transactions_append_only();
                SQL),
            'sqlite' => DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS wallet_transactions_no_update;
                DROP TRIGGER IF EXISTS wallet_transactions_no_delete;
                SQL),
            default => null,
        };

        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }

    private function upPostgres(): void
    {
        $message = self::MESSAGE;

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION wallet_transactions_append_only() RETURNS trigger
            LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION '{$message} (%)', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END;
            \$\$;

            DROP TRIGGER IF EXISTS wallet_transactions_append_only ON wallet_transactions;
            CREATE TRIGGER wallet_transactions_append_only
                BEFORE UPDATE OR DELETE ON wallet_transactions
                FOR EACH ROW EXECUTE FUNCTION wallet_transactions_append_only();

            DROP TRIGGER IF EXISTS wallet_transactions_no_truncate ON wallet_transactions;
            CREATE TRIGGER wallet_transactions_no_truncate
                BEFORE TRUNCATE ON wallet_transactions
                FOR EACH STATEMENT EXECUTE FUNCTION wallet_transactions_append_only();
            SQL);
    }

    private function upSqlite(): void
    {
        $message = self::MESSAGE;

        DB::unprepared(<<<SQL
            CREATE TRIGGER IF NOT EXISTS wallet_transactions_no_update
            BEFORE UPDATE ON wallet_transactions
            BEGIN
                SELECT RAISE(ABORT, '{$message}');
            END;
            SQL);

        DB::unprepared(<<<SQL
            CREATE TRIGGER IF NOT EXISTS wallet_transactions_no_delete
            BEFORE DELETE ON wallet_transactions
            BEGIN
                SELECT RAISE(ABORT, '{$message}');
            END;
            SQL);
    }
};
