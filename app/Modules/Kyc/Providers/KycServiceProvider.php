<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use App\Modules\Kyc\Infrastructure\Zohal\ZohalIdentityVerification;

/**
 * Module e Kyc (Phase 7) — onboarding، estelam e hoviat (Zohal)، ta'eed e
 * sath. View haye `kyc::`.
 */
final class KycServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();
        $this->app->scoped(IdentityVerificationPort::class, fn () => new ZohalIdentityVerification(
            (string) config('modules.kyc.token', ''),
            (string) config('modules.kyc.base_url'),
            (int) config('modules.kyc.timeout', 12),
        ));
    }

    protected function module(): string
    {
        return 'Kyc';
    }
}
