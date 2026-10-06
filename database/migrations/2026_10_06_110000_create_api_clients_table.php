<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ api_price_groups + api_plans + api_clients — foroosh e API        ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * `api_price_groups` mehvar e JODAGANE az `provider_spreads` ast (PLAN
 * §1.5): spread e provider = «chi neshun midim»، group e API = «be in
 * dasteh az customer ha chand mifrushim». Fe'lan faghat hazine ye har
 * call؛ soton e spread e customer rooz-i ke business esh ma'lum shod
 * ezafe-shi miad.
 *
 * 🔴 `secret_encrypted`: secret e HMAC bayad GHABL E BAZYABI bashe
 * (server baraye verify be khod e secret niaz dare — MAC ast، na
 * password)، pas HASH nemishe؛ ENCRYPT mishe (Crypt/APP_KEY) ta dump e
 * DB be tanha-i secret ha ro lo nade. (Hamun model e tahdid e OtpCode:
 * lo raftan e DB bedun e lo raftan e APP_KEY.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_price_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 120);
            // Hazine ye har call e movafagh — minor unit (rial). 304 kasr
            // NEMISHE (ETag — PLAN §Phase 4).
            $table->bigInteger('cost_per_call_minor')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('api_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 120);
            $table->bigInteger('price_minor');
            $table->integer('calls_per_day');
            $table->bigInteger('cost_per_call_minor');
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedBigInteger('calls_included')->default(0);
            $table->timestamps();
        });

        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('price_group_id')->nullable()->constrained('api_price_groups')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('api_plans')->nullOnDelete();
            $table->string('name', 120);
            $table->string('website_url', 190)->nullable();
            // Shenase ye omumi tu header — unique، gheyr e hads-pazir.
            $table->string('client_id', 40)->unique();
            $table->text('secret_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_used_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['status', 'user_id']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('api_plans');
        Schema::dropIfExists('api_price_groups');
    }
};
