<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('path')->unique();
            $table->string('name');
            $table->string('mime', 64)->index();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt');
            $table->string('title')->nullable();
            $table->json('derivatives')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            // FK hamzaman ba lock e DeleteMediaAction jolo e hazf e tasvir e masraf-shode ro migire.
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->morphs('usable');
            $table->string('field', 64);
            $table->timestamps();
            $table->unique(['media_id', 'usable_type', 'usable_id', 'field'], 'media_usages_unique');
        });
        Schema::table('providers', function (Blueprint $table): void {
            $table->foreign('logo_media_id')->references('id')->on('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->dropForeign(['logo_media_id']);
        });
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media');
    }
};
