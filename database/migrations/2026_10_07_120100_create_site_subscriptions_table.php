<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained('api_clients')->restrictOnDelete();
            $table->foreignId('api_plan_id')->constrained('api_plans')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedBigInteger('calls_included');
            $table->unsignedInteger('calls_per_day');
            $table->bigInteger('price_minor');
            $table->bigInteger('cost_per_call_minor');
            $table->unsignedBigInteger('calls_used')->default(0);
            $table->string('purchase_reference', 64)->unique();
            $table->timestamps();
            $table->index(['api_client_id', 'starts_at', 'ends_at']);
        });
        Schema::create('site_subscription_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_subscription_id')->constrained('site_subscriptions')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('calls_used')->default(0);
            $table->unique(['site_subscription_id', 'day']);
        });
        Schema::create('site_subscription_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_subscription_id')->constrained('site_subscriptions')->cascadeOnDelete();
            $table->string('reference', 64)->unique();
            $table->date('day');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_subscription_calls');
        Schema::dropIfExists('site_subscription_days');
        Schema::dropIfExists('site_subscriptions');
    }
};
