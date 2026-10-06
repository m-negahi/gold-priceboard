<?php

declare(strict_types=1);

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Panel\Providers\PanelServiceProvider;
use App\Modules\Pricing\Providers\PricingServiceProvider;
use App\Providers\AppServiceProvider;

/*
 * Provider haye module dasti sabt mishan — auto-discovery nadarim.
 * Tartib mohem ast: module haye paye avval.
 *
 * `MediaServiceProvider` AMDAN hanuz nist: Media migration va route nadare
 * (P3-00b — ba P3-06 miad ke logo ye provider lazemesh dare).
 */
return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PricingServiceProvider::class,
    PanelServiceProvider::class,
];
