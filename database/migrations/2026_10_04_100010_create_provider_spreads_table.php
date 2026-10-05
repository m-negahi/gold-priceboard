<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ provider_spreads — «chand darsad balatar/paeen-tar NESHUN bedim»   ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   tgju faghat YEK gheymat mide (masalan 266,400,000 rial).
 *             Vali tablo ye ma «kharid» va «foroosh» neshun mide. In jadval
 *             mige un do adad chetor az gheymat e kham sakhte mishan.
 *
 * MESAL:      | provider | symbol        | buy        | sell       |
 *             | tgju     | *             | -0.5%      | +0.5%      |
 *             | tgju     | xau-18k-g-irr | -0.3%      | +0.8%      |
 *             `*` = default baraye hame ye symbol haye un provider.
 *             Resolve: (provider, symbol) -> (provider, '*') -> sefr.
 *             Meghdar ELAMATDAR ast: manfi kam mikone.
 *
 * KEY NEVESHTE MISHE:  faghat admin az panel (`ProviderSpreadAction`).
 * KEY KHUNDE MISHE:    har bar tablo/chart render mishe — spread ru READ
 *                      e'mal mishe، HICHVAGHT ru storage (dalil e kamel tu
 *                      `SpreadPricer`: spread e bake-shode pele ye ja'li tu
 *                      chart mizare va ghabl e bargasht nist).
 *
 * 🔴 CHERA ESM ESH `price_groups` NIST:
 *   Phase 4 yek mehvar e KAMELAN joda mikhad:
 *     provider_spreads -> chi ru TABLO neshun midim (har maghaze)
 *     api_price_groups -> chi be har MOSHTARI ye API hesab mikonim
 *   Ghati kardaneshun ba'd az avvalin surat-hesab ghabl e bargasht nist.
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
