<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Baseline e 2026-10-07 panj migration e ghadimi ro tu `create_*` ha edgham
 * kard (P10-03)؛ file eshun hazf shod vali radif eshun tu jadval e
 * `migrations` e env haye deploy-shode (dev) mund. Laravel un ha ro nadide
 * migire، vali `migrate:status` gomrah-konande-st va rollback e tasadofi
 * ru esm e file e namojud khata mide. Hazf e radif haye yatim — hich
 * schema/data-i dast nemikhore. Ru nasb e taze hich radif-i vojud nadare.
 */
return new class extends Migration
{
    private const MERGED = [
        '2026_10_06_120000_create_api_plans_table',
        '2026_10_06_160000_add_status_and_level_to_users_table',
        '2026_10_06_170000_guard_wallet_transactions_append_only',
        '2026_10_06_192000_add_onboarding_to_users_table',
        '2026_10_07_120000_add_phase_eight_site_billing_columns',
    ];

    public function up(): void
    {
        DB::table('migrations')->whereIn('migration', self::MERGED)->delete();
    }

    public function down(): void
    {
        // Radif haye yatim bargasht-pazir nistan (file eshun vojud nadare).
    }
};
