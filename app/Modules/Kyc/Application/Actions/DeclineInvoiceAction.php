<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Actions;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Carbon\CarbonImmutable;

/**
 * Javab e «خیر» be soal e factor e rasmi (P7-03): onboarding tamum، level
 * `basic` mimune. Karbar ba'dan har vaght mitune az hamun safhe darkhast e
 * ertegha bede.
 */
final readonly class DeclineInvoiceAction
{
    public function execute(User $user): void
    {
        $user->forceFill([
            'onboarded_at' => $user->onboarded_at ?? CarbonImmutable::now(),
            'invoice_requested' => false,
        ])->save();
    }
}
