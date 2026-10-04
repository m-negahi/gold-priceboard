<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftarche ye append-only ye har gheymat-i ke VAGHE'AN avaz shode.
 *
 * ── Chera vojud dare ─────────────────────────────────────────────────
 * Influx 2 OSS replication nadare, tick haye kham ba'd az 30 rooz pak mishan,
 * va candle haghighat e tick-level ro az beyn mibare. Pas bedun e in jadval,
 * tanha sanad e «ma che gheymat-i neshun dadim» yek DB e bedun e replica ast.
 *
 * In jadval:
 *   - `pg_dump`-pazir ast (hamrah e baghi ye backup)
 *   - manba' e DOVOM baraye rebuild e Influx ba'd az kharabi
 *   - javab e «gheymat tu lahze T chand bud» baraye API, bedun e Influx
 *
 * ⚠️ FAGHAT vaghti `raw_minor` taghir karde insert mishe — na har tick.
 * Tala shayad 200-2000 bar tu rooz harekat kone, na 5,760 bar. ~5MB/mah.
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
