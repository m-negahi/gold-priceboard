<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spread e har (provider, instrument) — «chi ru tablo neshun midim».
 *
 * 🔴 Esm amdan `provider_spreads` ast, na `price_groups`.
 * Phase 4 (API e foroshi) be yek mehvar e KAMELAN JODA niaz dare:
 *   provider_spreads     -> chi ru tablo neshun midim (har maghaze)
 *   api_price_groups     -> chi be har MOSHTARI ye API mohasebe mikonim
 * Ghati kardan e in do ba'd az avvalin surat-hesab ghabl e bargasht nist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_spreads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();

            // `*` = default e wildcard baraye hame ye instrument haye in provider.
            // Tartib e resolve: (provider, symbol) -> (provider, '*') -> sefr.
            $table->string('symbol', 32);

            // Meghdar ELAMATDAR ast: manfi kam mikone. Soton e jodagane baraye
            // jahat nadarim chun `+`/`-` tu form khanatar az yek select ast.
            //
            // 4 raghame ashar: ham darsad haye riz (0.0125%) ro pushesh mide
            // ham meghdar haye riz e dollari (0.0001).
            $table->string('buy_mode', 8)->default('percent');
            $table->decimal('buy_value', 12, 4)->default(0);
            $table->string('sell_mode', 8)->default('percent');
            $table->decimal('sell_value', 12, 4)->default(0);

            // Override haye per-(provider, symbol) — yek jadval jaye se ta.
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('freshness_seconds')->nullable();
            $table->unsignedSmallInteger('sort')->nullable();

            $table->timestamps();

            $table->unique(['provider_id', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_spreads');
    }
};
