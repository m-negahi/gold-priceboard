<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ api_usage_daily — usage e PAYDAR e API (P6-08)                     ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Ta inja tanha shomarande ye ruzane yek kelid e Redis e 2-rooze (quota)
 * bud — ba evict/restart gom mishod va tarikhche nadasht.
 *
 * Yek radif be ezaye (client، rooz e TEHRAN). `api:usage:rollup` (har 15
 * daghighe) por esh mikone:
 *   - `calls`: az shomarande ye Redis — har call e data ke serve shod.
 *   - `cost_minor`: az LEDGER e PG (kasr − refund) — manba' e haghighat
 *     e pul. Bigint e minor unit (rial)، hargez float.
 *
 * `unique(api_client_id, day)` = upsert e idempotent (ejra ye dobare ye
 * rollup radif e dovom nemisaze). FK `cascadeOnDelete` faghat baraye
 * hazf e SAKHT e client؛ client e soft-delete tarikhche ash mimune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('calls')->default(0);
            $table->bigInteger('cost_minor')->default(0);
            $table->timestamps();

            $table->unique(['api_client_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
    }
};
