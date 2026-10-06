<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ ticket_attachments — madarek e ticket (P7-02)                       ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * File ru disk e PRIVATE e `tickets` (birun az `public/` va birun az root e
 * disk e `local` ke `serve` dare) — download faghat az controller e
 * emza-dar ba check e saheb/admin.
 *
 * - `path` esm e TASADOFI (uuid) + pasvand az mime e VAGHEI ye mohtava؛
 *   esm e karbar faghat baraye namayesh (`original_name`) — path traversal
 *   va overwrite az esm e file momken nist.
 * - `sha256` baraye tashkhis e tekrari/dast-khordegi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ticket_messages')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestampTz('created_at')->nullable();

            $table->index(['ticket_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
