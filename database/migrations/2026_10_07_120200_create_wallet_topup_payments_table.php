<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topup_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('request_key', 36);
            $table->string('track_id', 64)->nullable()->unique();
            $table->string('status', 16)->default('initiated');
            $table->string('gateway_reference', 100)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->jsonb('verify_data')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
        });
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('wallet_topup_payment_id')->nullable()->unique()->constrained('wallet_topup_payments')->restrictOnDelete();
            $table->foreignId('site_subscription_id')->nullable()->unique()->constrained('site_subscriptions')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('number', 32)->unique();
            $table->bigInteger('amount_minor');
            $table->jsonb('buyer_snapshot');
            $table->jsonb('lines_snapshot');
            $table->timestampTz('issued_at');
            $table->timestamps();
        });
        Schema::create('billing_invoice_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION billing_invoice_no_change() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'invoice is immutable'; END; \$\$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER billing_invoices_immutable BEFORE UPDATE OR DELETE ON billing_invoices FOR EACH ROW EXECUTE FUNCTION billing_invoice_no_change()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_sequences');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('wallet_topup_payments');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS billing_invoice_no_change()');
        }
    }
};
