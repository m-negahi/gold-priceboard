<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backup e dasti bayad KHODKAR por bemune (khaste ye sahebe proje،
 * 2026-10-06): default e `manual_auto_source` az `none` be `priority`.
 *
 * Radif haye mojud ke `none` daran ham `priority` mishan — `none` ta in
 * lahze faghat default e tanzim-nashode bud، na entekhab e admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symbol_price_settings', function (Blueprint $table): void {
            $table->string('manual_auto_source', 32)->default('priority')->change();
        });

        DB::table('symbol_price_settings')->where('manual_auto_source', 'none')->update(['manual_auto_source' => 'priority']);
    }

    public function down(): void
    {
        Schema::table('symbol_price_settings', function (Blueprint $table): void {
            $table->string('manual_auto_source', 32)->default('none')->change();
        });
    }
};
