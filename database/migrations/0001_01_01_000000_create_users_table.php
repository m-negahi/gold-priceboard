<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║ users — login ba MOBILE + OTP، na email/password                   ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 *
 * CHI HAST:   karbar ha (admin + moshtari haye API e Phase 4).
 *             Hoviat e login `phone` ast: unique، NOT NULL، format e E.164
 *             (`+989XXXXXXXXX` — `PhoneHelper` + middleware ta'min mikonan).
 *
 * CHERA `name`/`email`/`password` NULLABLE-AND:
 *   karbar ba phone + OTP sabt-e-nam mikone va tu un lahze HICH kodum az in
 *   se ro nadare. Majbur kardaneshun ya'ni ya mohtava ye ja'li ya form e
 *   tulani tar — har do badtar az nullable.
 *
 * 🔴 `is_admin` tu model FILLABLE NIST — set esh faghat ba `forceFill()`
 *   (dalil e kamel tu docblock e `User`: privilege escalation ba mass
 *   assignment).
 *
 * `password_reset_tokens` va `sessions` hamun default e Laravel-and
 * (session driver e DB config-pazir ast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Hoviat e login. 16 char = `+` + hadaksar 15 raghame E.164.
            $table->string('phone', 16)->unique();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->string('status', 16)->default('active')->index();
            $table->timestampTz('disabled_at')->nullable();
            $table->string('disabled_reason', 255)->nullable();
            $table->string('level', 24)->default('basic')->index();
            $table->string('customer_type', 16)->default('individual');
            $table->string('national_code', 11)->nullable();
            $table->timestampTz('onboarded_at')->nullable();
            $table->boolean('invoice_requested')->nullable();
            $table->json('identity_profile')->nullable();
            $table->json('invoice_profile')->nullable();

            // Baraye `CustomerList` e panel (Phase 4).
            $table->timestampTz('last_login_at')->nullable();

            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
