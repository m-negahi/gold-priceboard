<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name', 120);
            $table->string('normal_side', 6);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('finance_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 100)->unique();
            $table->string('memo', 255);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_journal_id')->nullable()->unique()->constrained('finance_journals')->restrictOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');
        });
        Schema::create('finance_journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_journal_id')->constrained('finance_journals')->restrictOnDelete();
            $table->foreignId('finance_account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->index(['finance_account_id', 'id']);
            $table->index(['wallet_id', 'id']);
        });

        DB::table('finance_accounts')->insert([
            ['code' => '1000', 'name' => 'بانک', 'normal_side' => 'debit'],
            ['code' => '1100', 'name' => 'وجوه در راه درگاه', 'normal_side' => 'debit'],
            ['code' => '2100', 'name' => 'بدهی کیف پول مشتریان', 'normal_side' => 'credit'],
            ['code' => '3000', 'name' => 'تراز افتتاحیه', 'normal_side' => 'credit'],
            ['code' => '3900', 'name' => 'حساب واسط اصلاحات مدیر', 'normal_side' => 'credit'],
            ['code' => '4100', 'name' => 'درآمد اشتراک', 'normal_side' => 'credit'],
            ['code' => '4200', 'name' => 'درآمد فراخوانی API', 'normal_side' => 'credit'],
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('ALTER TABLE finance_journals ADD COLUMN posting_txid bigint NOT NULL DEFAULT txid_current()');
            DB::unprepared('ALTER TABLE finance_journal_lines ADD CONSTRAINT finance_line_positive_one_side CHECK (debit_minor >= 0 AND credit_minor >= 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (credit_minor > 0 AND debit_minor = 0)))');
            DB::unprepared("CREATE FUNCTION finance_no_change() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'finance ledger is append-only'; END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER finance_journals_immutable BEFORE UPDATE OR DELETE ON finance_journals FOR EACH ROW EXECUTE FUNCTION finance_no_change()');
            DB::unprepared('CREATE TRIGGER finance_lines_immutable BEFORE UPDATE OR DELETE ON finance_journal_lines FOR EACH ROW EXECUTE FUNCTION finance_no_change()');
            DB::unprepared("CREATE FUNCTION finance_line_guard() RETURNS trigger AS \$\$ BEGIN IF (SELECT posting_txid FROM finance_journals WHERE id = NEW.finance_journal_id) <> txid_current() THEN RAISE EXCEPTION 'posted journal cannot accept new lines'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER finance_lines_insert_guard BEFORE INSERT ON finance_journal_lines FOR EACH ROW EXECUTE FUNCTION finance_line_guard()');
            DB::unprepared("CREATE FUNCTION finance_balanced() RETURNS trigger AS \$\$ BEGIN IF (SELECT COUNT(*) FROM finance_journal_lines WHERE finance_journal_id = NEW.id) < 2 OR (SELECT COALESCE(SUM(debit_minor-credit_minor), 0) FROM finance_journal_lines WHERE finance_journal_id = NEW.id) <> 0 THEN RAISE EXCEPTION 'unbalanced journal'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE CONSTRAINT TRIGGER finance_journal_balanced AFTER INSERT ON finance_journals DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION finance_balanced()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journal_lines');
        Schema::dropIfExists('finance_journals');
        Schema::dropIfExists('finance_accounts');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS finance_balanced()');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_line_guard()');
            DB::unprepared('DROP FUNCTION IF EXISTS finance_no_change()');
        }
    }
};
