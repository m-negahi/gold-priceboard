<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `kind` ru `providers`.
 *
 * ── Chera lazem ast ─────────────────────────────────────────────────
 *   `reference` -> site e nerkh (tgju, estjt). Yek gheymat mide؛ kharid/
 *                  foroosh e ma MOSHTAGH ast va bayad ba `is_derived`
 *                  neshun dade beshe.
 *   `platform`  -> platform e vaghei (wallgold, taline, …) ke khodesh
 *                  kharid va foroosh e VAGHEI dare.
 *
 * In farq faghat barchasb nist: ru site-i ke kol e arzesh esh e'temad ast،
 * neshun dadan e arithmetic e khodemun be onvan e «nerkh e folan maghaze»
 * daghighan chizi-e ke shekayat misaze. `platform` ha nabayad marker e
 * «محاسبه‌شده» begiran.
 *
 * Migration e ezafe-shi — jadval e mojud dast nemikhore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->string('kind', 16)->default('reference')->after('driver');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
