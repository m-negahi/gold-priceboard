<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ providers — «az KOJA gheymat migirim»                              ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   har manba' e gheymat yek radif. Alan 3 ta: tgju، estjt، mock.
 *             Ba'dan wallgold، taline، … har kodum faghat yek radif e jadid.
 *
 * MESAL:      | key   | driver | is_active | timeout | poll_interval |
 *             | tgju  | tgju   | true      | 15s     | 15s           |
 *             | estjt | estjt  | true      | 20s     | 15s           |
 *
 * KEY NEVESHTE MISHE:
 *   - admin az panel (roshan/khamoosh، timeout، …) — bedun e deploy
 *   - worker har tick soton haye salamat ro update mikone (last_error،
 *     consecutive_failures، …) — vali DAMPED، faghat vaghti chizi avaz shode
 *
 * KEY KHUNDE MISHE:
 *   - avval e har tick: «kodum provider ha ro alan poll konam?»
 *     (index e `is_active, next_poll_at` baraye hamin ast)
 *   - panel e admin (lista + salamat)
 *
 * CHERA `driver` JODA AZ `key` AST:
 *   5 maghaze ba hamun format e site mitunan 5 PROVIDER bashan ru YEK
 *   driver — provider e jadid ru driver e mojud = faghat yek radif e DB،
 *   bedun e hich code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();

            // 🔴 `key` va `driver` AMDAN jodā-an.
            // `key` hoviyat e in provider ast (`wallgold`), `driver` kelas e
            // code-i ke azash mikhune (`wordpress_tablotala`). Ba'dan panj
            // maghaze ba hamun shape e `admin-ajax.php` mishan PANJ provider
            // ru YEK driver. Agar inha ro yeki konim, un ruz bayad kol e
            // jadval ro refactor konim.
            $table->string('key', 32)->unique();
            $table->string('driver', 32);

            // `reference` = site e nerkh (tgju) — kharid/foroosh e ma MOSHTAGH
            // ast va `is_derived` mikhore. `platform` = maghaze ye vaghei ba
            // nerkh e khodesh. (Jozyat tu enum e `ProviderKind`.)
            $table->string('kind', 16)->default('reference');

            $table->string('name', 64);
            $table->string('name_fa', 64);
            $table->string('website', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->foreignId('logo_media_id')->nullable();

            // Toggle haye admin — jodā, chun yek provider mitune manba' e
            // chart bashe vali ru tablo ye TV naiad (ya bar'aks).
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trust_anchor')->default(false);
            $table->boolean('in_comparison')->default(true);
            $table->boolean('on_board')->default(true);
            $table->boolean('in_chart')->default(true);

            $table->unsignedSmallInteger('timeout')->default(10);

            // Poll e tatbigh-paziri: provider e geroon tar mitune 60s bashe va
            // arzun tar 15s. `next_poll_at` ya'ni back-off yek UPDATE ast, na
            // yek deploy.
            $table->unsignedSmallInteger('poll_interval_seconds')->default(15);
            $table->timestampTz('next_poll_at')->nullable();

            // Saghf e default e bayat budan; har instrument mitune override kone.
            $table->unsignedInteger('freshness_seconds')->default(900);
            // Ba'd az in moddat, radif az tablo ye TV mifte birun.
            $table->unsignedInteger('hide_after_seconds')->default(600);

            // ⚠️ FAGHAT default. Vahed e vaghei tu map e har instrument e
            // Source ast — estjt «انس طلا» ro USD mide vali baghi ro Toman,
            // va yek meghdar e provider-level ons ro x10 mikone.
            $table->string('upstream_unit', 8)->default('rial');

            $table->unsignedSmallInteger('sort')->default(0);

            // UA, base url, body, har chiz e makhsus e in Source.
            $table->jsonb('options')->default('{}');

            // ── Salamati (noskhe ye payedar; Redis mirror e zende ast) ──
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->unsignedSmallInteger('last_symbol_count')->nullable();
            $table->unsignedSmallInteger('last_rejected_count')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->string('health_state', 12)->default('unknown');

            // Baraye Source hayi ke timestamp NEMIDAN (estjt): agar hash
            // chand daghighe sabet bemune va bazaar baz bashe, feed yakh zade.
            $table->char('last_payload_hash', 32)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort']);
            // Har tick in ro mizane: «kodum provider ha alan bayad poll shan».
            $table->index(['is_active', 'next_poll_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
