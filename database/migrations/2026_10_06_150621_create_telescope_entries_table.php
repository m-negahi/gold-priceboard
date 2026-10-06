<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connection e storage e Telescope.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Sakht e jadval ha; setup e nime-kare bedun e hazf e data edame peyda mikone.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('telescope_entries')) {
            $schema->create('telescope_entries', function (Blueprint $table) {
                $table->bigIncrements('sequence');
                $table->uuid('uuid');
                $table->uuid('batch_id');
                $table->string('family_hash')->nullable();
                $table->boolean('should_display_on_index')->default(true);
                $table->string('type', 20);
                $table->longText('content');
                $table->dateTime('created_at')->nullable();

                $table->unique('uuid');
                $table->index('batch_id');
                $table->index('family_hash');
                $table->index('created_at');
                $table->index(['type', 'should_display_on_index']);
            });
        }

        if (! $schema->hasTable('telescope_entries_tags')) {
            $schema->create('telescope_entries_tags', function (Blueprint $table) {
                $table->uuid('entry_uuid');
                $table->string('tag');

                $table->primary(['entry_uuid', 'tag']);
                $table->index('tag');

                $table->foreign('entry_uuid')
                    ->references('uuid')
                    ->on('telescope_entries')
                    ->cascadeOnDelete();
            });
        }

        if (! $schema->hasTable('telescope_monitoring')) {
            $schema->create('telescope_monitoring', function (Blueprint $table) {
                $table->string('tag')->primary();
            });
        }
    }

    /**
     * Bargasht e jadval haye Telescope.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->dropIfExists('telescope_entries_tags');
        $schema->dropIfExists('telescope_entries');
        $schema->dropIfExists('telescope_monitoring');
    }
};
