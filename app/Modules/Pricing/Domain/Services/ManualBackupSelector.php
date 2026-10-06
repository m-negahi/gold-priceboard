<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

final readonly class ManualBackupSelector
{
    /**
     * Manual-only hata ba tanzim e ghabli ye auto-update hich manba'i nemigire.
     *
     * @param  list<string>  $chain
     * @return list<string>
     */
    public static function providerKeys(bool $manualOnly, string $source, array $chain): array
    {
        if ($manualOnly || $source === 'none' || $source === PriorityChain::MANUAL) {
            return [];
        }

        return $source === 'priority'
            ? array_values(array_filter($chain, static fn (string $key): bool => $key !== PriorityChain::MANUAL))
            : [$source];
    }
}
