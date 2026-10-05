<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ price_snapshots — «AKHARIN gheymat e har chiz، faghat yeki»        ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   yek radif be ezaye har (provider، symbol، side) — UPSERT
 *             mishe، hich vaght rushd nemikone. Alan ~25 radif؛ ba 6
 *             provider ~300. ⚠️ TARIKHCHE NIST — tarikhche tu Influx ast.
 *
 * MESAL:      | provider | symbol        | side | raw_minor   | quoted_at |
 *             | tgju     | xau-18k-g-irr | last | 266,400,000 | 14:03:15  |
 *             | tgju     | xau-melted-…  | bid  | 263,175,000 | 14:03:15  |
 *             | estjt    | xau-18k-g-irr | last | 266,818,000 | 14:03:15  |
 *
 * KEY NEVESHTE MISHE:  har tick (yek `INSERT … ON CONFLICT` e dasteji).
 * KEY KHUNDE MISHE:
 *   1. avval e har tick — gheymat e GHABLI baraye `PriceSanity` (check e
 *      jahesh) va `QuoteEnricher` (direction، band e rooz)
 *   2. 🔴 FALLBACK e tablo: Redis khali/kharab/yakh-zade -> tablo az inja
 *      khunde mishe va site zende mimune. (In naghsh e asli ye jadval ast.)
 *
 * CHERA `side` TU UNIQUE AST:   `xau-melted-g-irr` e tgju VAGHE'AN do
 *   gheymat e mostaghel dare (bid/ask e khod e bazaar) — do radif.
 *
 * CHERA `buy_minor`/`sell_minor` NADARE:   un do MOSHTAGH-and (raw +
 *   spread) va ru READ hesab mishan. Zakhire-shun ya'ni ba'd az har taghir
 *   e spread، meghdar e bayat tu DB ja munde.
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
