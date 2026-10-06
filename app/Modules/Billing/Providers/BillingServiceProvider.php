<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Billing\Infrastructure\Payments\VandarGateway;
use App\Modules\Billing\Infrastructure\Payments\ZibalGateway;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/billing.php', 'billing');
        // Dargah az config (`BILLING_GATEWAY`): vandar (default) ya zibal.
        $this->app->bind(PaymentGatewayPort::class, static fn ($app): PaymentGatewayPort => match ((string) $app['config']->get('billing.gateway', 'vandar')) {
            'zibal' => $app->make(ZibalGateway::class),
            default => $app->make(VandarGateway::class),
        });
    }
}
