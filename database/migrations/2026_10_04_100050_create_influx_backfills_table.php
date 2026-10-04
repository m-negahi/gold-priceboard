<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sabt e manbae' (provenance) ye har data-i ke BACKFILL shode tu Influx.
 *
 * Chera: tarikhche ye ma az tick haye khodemun miad. Agar ruzi az yek manba' e
 * biruni backfill konim, bayad betunim begim kodum bazeh az koja umade —
 * vagarna ba'dan hich kas nemifahme chera chart e 2025 shekl esh ba 2026 fargh
 * dare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influx_backfills', function (Blueprint $table): void {
            $table->id();
            $table->string('bucket', 32);
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('symbol', 32);
            // Masalan `tgju-summary-table`.
            $table->string('source', 64);
            $table->timestampTz('range_start');
            $table->timestampTz('range_stop');
            $table->unsignedInteger('points');
            $table->timestamps();

            $table->index(['bucket', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influx_backfills');
    }
};
