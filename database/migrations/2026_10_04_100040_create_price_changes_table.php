<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ price_changes — daftarche: «har gheymat-i ke VAGHE'AN avaz shod»   ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   append-only. Har bar `raw_minor` e yek (provider، symbol،
 *             side) taghir kard — NA har tick — yek radif. Tala shayad
 *             200-2000 bar dar rooz harekat kone، na 5,760 bar. ≈ 5MB/mah.
 *
 * FARGH ESH BA `price_snapshots`:   snapshot faghat AKHARIN ro dare
 *   (upsert، ~25 radif)؛ in hame ye taghir ha ro dare (insert، rushd
 *   mikone). Snapshot «alan chand e?» ro javab mide؛ in «sa'at e 14:03
 *   chand BUD?» ro.
 *
 * FARGH ESH BA INFLUX:   Influx HAR tick ro dare vali (a) replication
 *   nadare، (b) tick e kham 30 rooz ba'd pak mishe va faghat candle mimune.
 *   In jadval `pg_dump`-pazir ast (tu hamun backup e asli)، manba' e DOVOM
 *   baraye rebuild e Influx ba'd az kharabi، va javab e API bedun e
 *   vabastegi be Influx.
 *
 * KEY NEVESHTE MISHE:  tu tick، faghat baraye radif haye taghir-karde.
 * KEY KHUNDE MISHE:    (hanuz hich ja — Phase 4: endpoint e «gheymat dar
 *                      lahze T» + e'teraz e moshtari. Bime-name ast.)
 *
 * GHANUN:  APPEND-ONLY — hich update/delete. Agar hajm mas'ale shod،
 *          partition e mahiyane، na hazf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_changes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('side', 4)->default('last');

            $table->char('quote_currency', 3);
            $table->unsignedTinyInteger('scale')->default(0);
            $table->bigInteger('raw_minor');

            $table->timestampTz('upstream_at')->nullable();
            // Zaman e tick e floor-shode, na `created_at`.
            $table->timestampTz('observed_at');

            $table->index(['provider_id', 'symbol', 'side', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
    }
};
