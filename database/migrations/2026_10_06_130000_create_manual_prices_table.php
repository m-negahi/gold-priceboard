<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ manual_prices — gheymat haye DASTI (fallback) — Phase 4/5           ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Khaste ye sahebe proje (2026-10-06): yek manba' e MANUAL ke age hame ye
 * provider ha ghat' shan، API/tablo ghat' nashe. Admin gheymat ha ro dasti
 * migzare؛ ya be sorat e KHODKAR az yek source e entekhabi por mishan va
 * vaghti un source bayat shod، admin dasti be-rooz mikone (+ ALARM).
 *
 * Yek radif be ezaye har symbol. `minor` hamun vahed e minor e gheymat ast
 * (hargez float). `source_hint` = provider-i ke akharin bar azash seed
 * shode (baraye namayesh). `updated_at` paye ye ALARM ast — age ghadimi
 * tar az saghf bashe، panel hoshdar mide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->bigInteger('minor');
            $table->unsignedTinyInteger('scale')->default(0);
            $table->string('quote_currency', 3)->default('IRR');
            // Provider-i ke akharin bar azash seed shod (ya `manual` age dasti).
            $table->string('source_hint', 32)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_prices');
    }
};
