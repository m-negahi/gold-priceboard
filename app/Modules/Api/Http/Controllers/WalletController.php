<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Wallet\Application\Queries\WalletQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mande va ledger e customer (P4-06) — MAJANI (charge nadare). */
final readonly class WalletController
{
    public function __construct(private WalletQuery $wallets) {}

    public function show(Request $request): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');

        $wallet = $this->wallets->findForOwner($client->user_id);

        if ($wallet === null) {
            return new JsonResponse(['data' => ['balanceMinor' => 0, 'currency' => 'IRR', 'ledger' => []]]);
        }

        return new JsonResponse([
            'data' => [
                'balanceMinor' => $this->wallets->balanceFromLedger($wallet),
                'currency' => $wallet->currency,
                'isBlocked' => (bool) $wallet->is_blocked,
                'ledger' => $this->wallets->ledger($wallet, 20),
            ],
        ]);
    }
}
