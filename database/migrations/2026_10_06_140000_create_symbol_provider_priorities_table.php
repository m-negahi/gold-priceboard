<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ symbol_provider_priorities — olaviat e provider PER-SYMBOL (P5-07)  ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Khaste ye sahebe proje (2026-10-06): «masalan xau-18k-g-irr aval az tgju،
 * age khata dad estjt، age khata dad x». Hamin zanjire ham feed e mo'asser
 * (API) ro hedayat mikone ham «پر کردن بر اساس اولویت» e gheymat e dasti.
 *
 * Symbol-i ke HICH radif nadare = tartib e global (`providers.sort`) —
 * pas in jadval khali = raftar e ghabli، bedun e taghir.
 *
 * `manual` inja hichvaght nemiyad: hamishe akhar-e (Action rad mikone).
 * Mantegh e tartib: `Domain\Services\PriorityChain`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symbol_provider_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 32);
            // Hazf e SAKHT e provider radif ha ro ham mibare؛ soft-delete ro
            // Query ba join nadide migire.
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['symbol', 'provider_id']);
            $table->index(['symbol', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symbol_provider_priorities');
    }
};
