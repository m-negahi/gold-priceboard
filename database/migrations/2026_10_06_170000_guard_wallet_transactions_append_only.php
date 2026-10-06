<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ wallet_transactions: APPEND-ONLY dar sath e DB — P6-07             ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Ta inja append-only faghat yek ghanun e NEVESHTARI bud (`UPDATED_AT =
 * null` + docblock) — yek `DB::table('wallet_transactions')->update()`،
 * yek `->delete()`، ya yek psql e dasti hich manE-i nadasht. In trigger
 * UPDATE / DELETE (va TRUNCATE ru PG) ro dar KHOD e DB rad mikone.
 *
 * ── Natije ye amdi: cascade ham baste-st ────────────────────────────
 * FK e `wallets.owner_id -> users` va `wallet_transactions.wallet_id ->
 * wallets` `cascadeOnDelete` daran. Ba in trigger، pak kardan e karbar/kif
 * ke ledger DARE khata mide — tarikhche ye pul ba pak kardan e karbar
 * gom nemishe (karbar disable mishe، na delete — P6-05). Karbar/kif e
 * BEDUN e tarakonesh hamchenan pak mishe (trigger ROW-level ast).
 *
 * ── Har driver noskhe ye khodesh ────────────────────────────────────
 * - pgsql: function + trigger e ROW-level baraye UPDATE/DELETE (faghat ru
 *   radif e vaghei fire mishe — hamun raftar e SQLite) + STATEMENT-level
 *   baraye TRUNCATE (PG faghat statement-level ejaze mide).
 * - sqlite (test ha): `RAISE(ABORT)` — ta test ha HAMIN gharardad ro
 *   vaghe-an check konan، na inke ru test bi-asar bashe.
 * - baghi driver ha: kari nemikonim (proje faghat in do ro dare).
 *
 * Eslah e eshtebah = radif e JADID (`refund`/`admin_adjust`)، na UPDATE.
 */
return new class extends Migration
{
    private const MESSAGE = 'wallet_transactions append-only ast: UPDATE/DELETE mamnu';

    public function up(): void
    {
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
