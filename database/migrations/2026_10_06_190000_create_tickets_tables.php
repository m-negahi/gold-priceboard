<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ tickets / ticket_messages / ticket_events — Phase 7 (P7-01)        ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * «Har darkhast e karbar = ticket» (ertegha ye sath/KYC، ta'eed e site،
 * poshtibani). Jadval haye JADID — hich chiz e mojud dast nemikhore.
 *
 * - `tickets.meta` (json): data ye sakhtar-dar e darkhast (masalan form e
 *   hoghoghi e KYC — P7-03). Matn e azad tu `ticket_messages`.
 * - `ticket_events`: audit e HAR taghir e vaziat (ki، kay، az chi be chi،
 *   chera). Faghat INSERT — `UPDATED_AT` nadare.
 * - `author_id`/`actor_id` `nullOnDelete`: hazf e yek admin tarikhche ro
 *   pak nemikone. `tickets.user_id` `cascadeOnDelete`: ticket bedun e
 *   saheb ma'na nadare (karbar ma'mulan disable mishe، na delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('status', 16)->default('open');
            $table->string('subject', 200);
            $table->json('meta')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index(['user_id', 'id']);
            $table->index('last_activity_at');
        });

        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_staff')->default(false);
            $table->text('body');
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 24);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['ticket_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
