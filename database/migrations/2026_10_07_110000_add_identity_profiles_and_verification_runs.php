<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profile_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before');
            $table->json('after');
            $table->timestampTz('created_at');
        });
        Schema::create('identity_verification_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_key', 100)->unique();
            $table->string('status', 20)->default('queued');
            $table->json('input');
            $table->json('results')->nullable();
            $table->timestampsTz();
        });
        Schema::create('ticket_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('ticket_events')->cascadeOnDelete();
            $table->timestampTz('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_notifications');
        Schema::dropIfExists('identity_verification_runs');
        Schema::dropIfExists('user_profile_audits');
    }
};
