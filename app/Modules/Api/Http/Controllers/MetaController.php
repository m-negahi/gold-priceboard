<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Modules\Pricing\Application\Queries\ProviderHealthQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use Illuminate\Http\JsonResponse;

/** Metadata ye API (P4-06) — instrument ha va provider ha. */
final readonly class MetaController
{
    public function __construct(private ProviderHealthQuery $health) {}

    public function instruments(): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(static fn (Instrument $i): array => [
                'symbol' => $i->value,
                'label' => $i->label(),
                'metal' => $i->metal()?->value,
                'unit' => $i->unit()->value,
                'quote' => $i->quote()->value,
                'scale' => $i->quote()->scale(),
                'displayDivisor' => $i->quote()->displayDivisor(),
                'weightGrams' => $i->weightGrams(),
                'hasNativeSides' => $i->hasNativeSides(),
            ], Instrument::cases()),
        ]);
    }

    public function providers(): JsonResponse
    {
        $rows = [];

        foreach ($this->health->overview() as $provider) {
            if (! $provider['isActive']) {
                continue;
            }

            // Faghat chizi ke be customer marbut e — hich latency/khata.
            $rows[] = [
                'key' => $provider['key'],
                'name' => $provider['name'],
                'state' => $provider['state']->value,
            ];
        }

        return new JsonResponse(['data' => $rows]);
    }
}
