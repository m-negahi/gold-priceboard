<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;

/**
 * Module e Kyc (Phase 7) — onboarding، estelam e hoviat (Zohal)، ta'eed e
 * sath. View haye `kyc::`.
 */
final class KycServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Kyc';
    }
}
