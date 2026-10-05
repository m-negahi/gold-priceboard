<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_removal` ru `provider_spread_revisions`.
 *
 * ── Chera lazem ast ─────────────────────────────────────────────────
 * Hazf kardan e yek radif e `provider_spreads` yek TAGHIR e vaghei-e: un
 * (provider, symbol) az un lahze be `*` (ya be sefr) fallback mikone.
 * Bedun e in soton، yek hazf hich asari tu daftarche nemizasht — ya «ba
 * hamin meghdar ha set shod» va «hazf shod» az ham ghabl e tashkhis nabudan.
 *
 * Kol e dalil e vojud e in jadval javab dadan be «ki، key، chi ro avaz
 * karde» ast؛ yek taghir e bi-asar un ta'ahod ro mishkone.
 *
 * Meghdar haye `buy_*`/`sell_*` tu radif e hazf، HAMUN chizi-e ke GHABL az
 * hazf dar jarian bud — ya'ni «in spread bud va bardashte shod».
 *
 * Migration e ezafe-shi — radif haye mojud `false` migiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_spread_revisions', function (Blueprint $table): void {
            $table->boolean('is_removal')->default(false)->after('sell_value');
        });
    }

    public function down(): void
    {
        Schema::table('provider_spread_revisions', function (Blueprint $table): void {
            $table->dropColumn('is_removal');
        });
    }
};
