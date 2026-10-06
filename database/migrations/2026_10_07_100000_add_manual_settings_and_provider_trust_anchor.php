<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symbol_price_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 32)->unique();
            $table->boolean('manual_only')->default(false);
            $table->boolean('manual_fallback_enabled')->default(true);
            $table->string('manual_auto_source', 32)->default('none');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symbol_price_settings');
    }
};
