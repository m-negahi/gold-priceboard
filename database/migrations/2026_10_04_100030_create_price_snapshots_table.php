<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AKHARIN gheymat — yek radif be ezaye har (provider, symbol, side).
 *
 * ⚠️ Inja TARIKHCHE NIST. Tarikhche tu Influx ast. Naghsh e in jadval
 * **backfill e Redis** ast: agar Redis khali/down bashe, tablo az inja khunde
 * mishe va site zende mimune.
 *
 * Tedad e radif: provider x symbol x side ≈ 50 alan, ~300 ba shesh provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('symbol', 32);

            // `side` tu unique hast chun `xau-melted-g-irr` vaghe'an DO radif e
            // upstream dare (`tgju_gold_irg18` + `_buy`), na yeki.
            $table->string('side', 4)->default('last');

            $table->char('quote_currency', 3);

            // `scale` ru HAR radif takrar mishe (denormalize). Amdan: hich
            // reader-i (Redis, JS, API e moshtari) nabayad baraye tafsir e
            // `*_minor` majbur bashe enum e PHP e ma ro bebine.
            $table->unsignedTinyInteger('scale')->default(0);

            // 🔴 KHAM — daghighan chizi ke provider goft, faghat ba arz
            // yeksan-shode. HICH spread-i inja e'mal nashode.
            // Dalil e kamel tu `SpreadPricer` va `docs/architecture.md` §4.
            $table->bigInteger('raw_minor');

            // Akharin meghdar e MOTEFAVET — paye ye `direction`.
            $table->bigInteger('prev_minor')->nullable();

            $table->bigInteger('day_open_minor')->nullable();
            $table->bigInteger('day_high_minor')->nullable();
            $table->bigInteger('day_low_minor')->nullable();

            $table->decimal('change_pct', 9, 4)->default(0);
            $table->string('direction', 8)->default('flat');

            // Outlier e moallagh: meghdar-i ke `PriceSanity` rad karde vali
            // negah dashte. Agar tick e ba'di ta'eed esh kone, paziresh mishe
            // («do outlier e mote'ali vaghei-and, yeki parse error ast»).
            //
            // Tu DB ast va na Redis: nabayad ba restart e Redis gom beshe,
            // vagarna yek harekat e vaghei ye bazaar baraye hamishe rad mishe.
            $table->bigInteger('pending_outlier_minor')->nullable();

            // `true` faghat vaghti upstream VAGHE'AN jofte bid/ask bede.
            // Ru UI va tu payload e API be onvan e `is_derived` neshun dade
            // mishe — ma nabayad arithmetic e khodemun ro «nerkh e maghaze»
            // sedā konim.
            $table->boolean('has_native_sides')->default(false);

            $table->timestampTz('upstream_at')->nullable();
            $table->unsignedInteger('upstream_lag_seconds')->nullable();
            $table->timestampTz('quoted_at');

            $table->timestamps();

            // Kelid e upsert.
            $table->unique(['provider_id', 'symbol', 'side']);
            // Jadval e moghayese: «hame ye provider ha, yek symbol».
            $table->index(['symbol', 'side']);
            $table->index('quoted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
