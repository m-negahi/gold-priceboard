<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ provider_spread_revisions — daftarche: «KI، KEY، spread ro avaz kard»║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   har bar admin yek spread ro avaz (ya hazf) kone، yek radif e
 *             GHEYR e ghabel e taghir inja mishine. In jadval hich naghshi tu
 *             mohasebe ye gheymat NADARE — faghat shahed ast.
 *
 * MESAL:      | provider | symbol | sell  | changed_by | effective_from   |
 *             | tgju     | *      | +0.5% | admin:7    | 2026-10-05 14:00 |
 *             | tgju     | *      | +0.8% | admin:7    | 2026-10-05 16:30 |
 *             -> «sa'at e 15:00 chand neshun midadim?» = radif e aval.
 *
 * CHERA LAZEM AST:
 *   Ma nerkh e «kharid/foroosh e maghaze X» ro neshun midim dar hali ke
 *   arithmetic e KHODEMUN ru yek gheymat e kham ast. Avvalin bar ke yek
 *   maghaze zang bezane «shoma nerkh e man ro ghalat zadid»، ya yek moshtari
 *   ye API e'teraz kone، IN jadval tanha javab ast. Bedun esh: harf e ma
 *   dar barabar e harf e un.
 *
 * GHANUN:     APPEND-ONLY — hich `update`، hich `delete`، hargez.
 *             Tu HAMUN transaction e taghir e spread neveshte mishe
 *             (`ProviderSpreadAction`)، pas nemitune ja bemune.
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

            // `true` ya'ni in radif yek HAZF ro sabt mikone (meghdar ha =
            // chizi ke ghabl az hazf dar jarian bud). Bedun e in، «hazf shod»
            // va «ba hamin meghdar ha set shod» ghabel e tafkik nabudan.
            $table->boolean('is_removal')->default(false);

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
