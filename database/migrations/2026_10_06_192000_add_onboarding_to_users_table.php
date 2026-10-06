<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ users: onboarding — Phase 7 (P7-03)                                 ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * EZAFE-SHI. `onboarded_at` = karbar be soal e «فاکتور رسمی» javab dade؛
 * `invoice_requested` = javab esh (null = hanuz naporsidim).
 *
 * 🔴 BACKFILL: faghat ADMIN ha `onboarded_at = now` migiran (onboarding
 * baraye admin nist). Moshtari haye MOJUD null mimunan va yek bar (ba'd az
 * login e ba'di) soal ro mibinan — ghablan hich vaght porsideh nashode bud
 * va javab e «خیر» level/dastresi ro avaz nemikone، pas chizi nemishkane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('onboarded_at')->nullable();
            $table->boolean('invoice_requested')->nullable();
        });

        DB::table('users')->where('is_admin', true)->whereNull('onboarded_at')->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['onboarded_at', 'invoice_requested']);
        });
    }
};
