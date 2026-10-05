<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login ba MOBILE + OTP — na email/password.
 *
 * ── Chera in soton ha ───────────────────────────────────────────────
 * `phone` hoviat e login ast، pas `unique` va NOT NULL. Format: E.164
 * (`+989XXXXXXXXX`) — `PhoneHelper::normalize()` + middleware ye
 * `NormalizePhone` un ro ta'min mikonan. 16 character kafi-e (`+` + max 15
 * ragham e E.164).
 *
 * 🔴 `email`، `password` va `name` NULLABLE mishan: karbar ba phone + OTP
 * sabt-e-nam mikone va tu un lahze hich kodum az in se ro NADARE. Majbur
 * kardaneshun ya'ni ya mohtava ye ja'li (`"-"`, `"x@x.x"`) ya yek form e
 * tulani tar — har do badtar az nullable.
 *
 * ── ⚠️ In migration ru jadval e POR fail mishe — AMDAN ──────────────
 * `phone` NOT NULL e bedun e default ru radif haye mojud shekast mikhore.
 * Dorost ast: karbar e bedun e hoviat e login nabayad SAKET sakhte beshe.
 * (Alan hich user-i vojud nadare — auth hanuz sakhte nashode bud.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 16)->unique()->after('id');
            $table->timestampTz('phone_verified_at')->nullable()->after('phone');

            // 🔴 Tu model FILLABLE NIST — bebin docblock e `User`.
            $table->boolean('is_admin')->default(false)->after('phone_verified_at');

            // Baraye `CustomerList` e panel (Phase 4).
            $table->timestampTz('last_login_at')->nullable();

            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'phone_verified_at', 'is_admin', 'last_login_at']);
        });
    }
};
