<?php

declare(strict_types=1);

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Billing\Infrastructure\Payments\VandarGateway;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/billing.php', 'billing');
        // Tanha dargah Vandar ast (tasmim e sahebe proje، 2026-10-07 — Zibal hazf shod).
        // Dargah e jadid = adapter e jadid e `PaymentGatewayPort` + bind e sarih inja.
        $this->app->bind(PaymentGatewayPort::class, VandarGateway::class);
    }
}
