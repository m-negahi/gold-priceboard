<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Billing\Infrastructure\Payments\ZibalGateway;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/billing.php', 'billing');
        $this->app->bind(PaymentGatewayPort::class, ZibalGateway::class);
    }
}
