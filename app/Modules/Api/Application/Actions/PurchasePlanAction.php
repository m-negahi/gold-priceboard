<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Wallet\Application\Actions\WalletAction;
use App\Modules\Wallet\Infrastructure\Persistence\Models\WalletTransaction;

/**
 * Kharid e plan baraye yek website/client (P4-09).
 *
 * Model (khaste ye sahebe proje): pardakht e birooni (gateway — ba'dan)
 * -> wallet be andaze ye GHEYMAT E PLAN sharj mishe -> masraf
 * pay-as-you-go ba nerkh e plan kasr mishe -> saghf e ruzane ye plan ru
 * charge e'mal mishe.
 *
 * Idempotent ba `reference` (id e pardakht): callback e tekrari ye
 * gateway do bar sharj NEMIKONE.
 */
final readonly class PurchasePlanAction
{
    public function __construct(
        private WalletAction $wallets,
        private ChargeApiCallAction $charger,
    ) {}

    public function purchase(ApiClient $client, ApiPlan $plan, string $reference): WalletTransaction
    {
        $wallet = $this->wallets->forOwner($client->user_id);

        $transaction = $this->wallets->credit($wallet, $plan->price_minor, 'plan_purchase', $reference, [
            'client_id' => $client->client_id,
            'plan' => $plan->key,
        ]);

        // Plan hamishe set mishe — hatta age credit e in reference ghablan
        // zade shode bashe (retry e callback).
        $client->forceFill(['plan_id' => $plan->id])->save();

        // 🔴 Mirror e tasmim e Redis dige mande ye vaghei ro nemidune —
        // batel، ta avvalin call az LEDGER seed she.
        $this->charger->invalidateMirror($wallet);

        return $transaction;
    }
}
