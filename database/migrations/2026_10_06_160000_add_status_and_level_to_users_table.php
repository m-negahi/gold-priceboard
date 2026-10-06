<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ users: status / level / customer_type — Phase 6 (P6-01)           ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * EZAFE-SHI: hich soton e mojud avaz nemishe.
 *
 * 🔴 BACKFILL: hame ye karbar haye mojud `active` + `basic` + `individual`.
 *   `default` e soton ru PG radif haye mojud ro ham por mikone؛ `update` e
 *   sarih e paeen faghat kamarband e dovvom ast (ta age ruzi default
 *   avaz shod، backfill e in migration avaz nashe).
 *
 * `national_code` 11 char: code melli 10 ragham، shenase melli e sherkat
 *   11 ragham. Nullable — ta KYC (Phase 7) por nemishe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 16)->default('active')->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->string('disabled_reason', 255)->nullable();
            $table->string('level', 24)->default('basic')->index();
            $table->string('customer_type', 16)->default('individual');
            $table->string('national_code', 11)->nullable();
        });

        DB::table('users')->whereNull('status')->update(['status' => 'active']);
        DB::table('users')->whereNull('level')->update(['level' => 'basic']);
        DB::table('users')->whereNull('customer_type')->update(['customer_type' => 'individual']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['level']);
            $table->dropColumn(['status', 'disabled_at', 'disabled_reason', 'level', 'customer_type', 'national_code']);
        });
    }
};
