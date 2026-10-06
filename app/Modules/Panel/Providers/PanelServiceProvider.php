<?php

declare(strict_types=1);

namespace App\Modules\Panel\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;

/**
 * ⚠️ P3-00: `SeedDashboardCommand` + `DashboardQuery`/`DashboardController` e
 * noskhe ye ghabli HAZF shodan — be Pricing e proje ye Desktop (model haye
 * `PriceQuote`/`PriceSource` va module ye `Setting`) import dashtan ke inja
 * vojud nadaran؛ register kardaneshun HAR artisan command ro mishekast.
 * Dashboard e vaghei (P3-05) az `BoardQuery` e IN proje sakhte mishe.
 * Blade haye `Resources/views` be onvan e marja' e UI negah dashte shodan.
 */
final class PanelServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Panel';
    }
}
