<?php

declare(strict_types=1);

use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Pricing\Providers\PricingServiceProvider;
use App\Providers\AppServiceProvider;

/*
 * Provider haye module dasti sabt mishan — auto-discovery nadarim.
 * Tartib mohem ast: module haye paye avval.
 */
return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PricingServiceProvider::class,
];
