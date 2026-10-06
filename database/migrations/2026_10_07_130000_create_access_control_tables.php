<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->json('permissions');
            $table->timestamps();
        });
        Schema::create('admin_access_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('full_access')->default(false);
            $table->timestamps();
        });
        Schema::create('admin_role_assignments', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('access_roles')->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('access_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audits');
        Schema::dropIfExists('admin_role_assignments');
        Schema::dropIfExists('admin_access_profiles');
        Schema::dropIfExists('access_roles');
    }
};
