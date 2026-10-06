<?php

declare(strict_types=1);

use App\Modules\Api\Providers\ApiServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Kyc\Providers\KycServiceProvider;
use App\Modules\Media\Providers\MediaServiceProvider;
use App\Modules\Panel\Providers\PanelServiceProvider;
use App\Modules\Pricing\Providers\PricingServiceProvider;
use App\Modules\Seo\Providers\SeoServiceProvider;
use App\Modules\Ticket\Providers\TicketServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

/*
 * Provider haye module dasti sabt mishan — auto-discovery nadarim.
 * Tartib mohem ast: module haye paye avval.
 */
return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    AuthServiceProvider::class,
    ApiServiceProvider::class,
    MediaServiceProvider::class,
    PricingServiceProvider::class,
    PanelServiceProvider::class,
    SeoServiceProvider::class,
    TicketServiceProvider::class,
    KycServiceProvider::class,
];
