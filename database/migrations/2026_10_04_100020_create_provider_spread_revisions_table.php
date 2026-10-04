<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarikhche ye append-only ye taghirat e spread.
 *
 * Chera lazem ast: ma gheymat e kharid/foroosh ro ba label e «نرخ فلان مغازه»
 * neshun midim dar hali ke arithmetic e KHODEMUN ru yek mid price ast. Avvalin
 * bar ke yek maghaze e'teraz kone «shoma nerkh e man ro ghalat zadid», in
 * jadval javab ast: ki, key, chi ro avaz karde.
 *
 * Tavasot e `ProviderSpreadAction` tu HAMUN transaction e upsert e spread
 * neveshte mishe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_spread_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('symbol', 32);

            $table->string('buy_mode', 8);
            $table->decimal('buy_value', 12, 4);
            $table->string('sell_mode', 8);
            $table->decimal('sell_value', 12, 4);

            // Hoviat e admin (session). `null` = taghir e systemi (seeder).
            $table->string('changed_by', 64)->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('created_at');

            $table->index(['provider_id', 'symbol', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_spread_revisions');
    }
};
