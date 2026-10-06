<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ api_plans + website/plan ru api_clients — model e karbari (P4-09)  ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * Khaste ye sahebe proje (2026-10-06): karbar baraye HAR website yek
 * client misaze، plan mikhare (masalan 1200 call/rooz)؛ kharid e plan
 * wallet ro be andaze ye gheymat e plan SHARJ mikone va masraf
 * pay-as-you-go kasr mishe. Saghf e ruzane ye plan ru charge e'mal
 * mishe (429).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 120);
            // Gheymat e kharid e plan — meghdari ke be wallet CREDIT mishe.
            $table->bigInteger('price_minor');
            $table->integer('calls_per_day');
            // Hazine ye har call e in plan (pay-as-you-go).
            $table->bigInteger('cost_per_call_minor');
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('api_clients', function (Blueprint $table): void {
            // Website-i ke in client barash sakhte shode (masalan tala.ir).
            $table->string('website_url', 190)->nullable()->after('name');
            $table->foreignId('plan_id')->nullable()->after('price_group_id')
                ->constrained('api_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('website_url');
        });
        Schema::dropIfExists('api_plans');
    }
};
