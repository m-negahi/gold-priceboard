<?php

declare(strict_types=1);

namespace App\Modules\Api\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Api\Infrastructure\Persistence\Models\SiteSubscription;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Application\Actions\IssueInvoiceAction;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kharid e eshterak az wallet KASR mishe. Snapshot e shart haye plan
 * tu subscription mimune ta edit e plan tarikhche ro avaz nakone.
 */
final readonly class PurchasePlanAction
{
    public function __construct(
        private WalletAction $wallets,
        private ChargeApiCallAction $charger,
        private IssueInvoiceAction $invoices,
    ) {}

    public function purchase(ApiClient $client, ApiPlan $plan, string $reference): SiteSubscription
    {
        if ($reference === '' || strlen($reference) > 64) {
            throw new InvalidArgumentException('reference e kharid na-motabar ast');
        }
        $plan->refresh();

        return DB::transaction(function () use ($client, $plan, $reference): SiteSubscription {
            /** @var ApiClient $locked */
            $locked = ApiClient::query()->lockForUpdate()->findOrFail($client->id);
            $existing = SiteSubscription::query()->where('purchase_reference', $reference)->first();
            if ($existing !== null) {
                if ($existing->api_client_id !== $locked->id || $existing->api_plan_id !== $plan->id) {
                    throw new InvalidArgumentException('reference e kharid tekrari ba darkhast e motefavet');
                }

                return $existing;
            }
            if ($locked->status !== 'approved' || ! $locked->is_active || ! $plan->is_active) {
                throw new InvalidArgumentException('site ya plan baraye kharid faal nist');
            }
            if ($plan->duration_days <= 0 || $plan->price_minor < 0 || $plan->calls_included < 0 || $plan->cost_per_call_minor < 0) {
                throw new InvalidArgumentException('shart haye plan na-motabar ast');
            }

            $wallet = $this->wallets->forOwner($locked->user_id);
            if ($plan->price_minor > 0) {
                $this->wallets->debit($wallet, $plan->price_minor, 'plan_purchase', $reference, [
                    'client_id' => $locked->client_id, 'plan' => $plan->key,
                ]);
            }

            $now = CarbonImmutable::now();
            $latestEnd = SiteSubscription::query()->where('api_client_id', $locked->id)->max('ends_at');
            $start = $latestEnd === null ? $now : max($now, CarbonImmutable::parse($latestEnd));
            $subscription = SiteSubscription::query()->create([
                'api_client_id' => $locked->id,
                'api_plan_id' => $plan->id,
                'user_id' => $locked->user_id,
                'starts_at' => $start,
                'ends_at' => $start->addDays($plan->duration_days),
                'calls_included' => $plan->calls_included,
                'calls_per_day' => $plan->calls_per_day,
                'price_minor' => $plan->price_minor,
                'cost_per_call_minor' => $plan->cost_per_call_minor,
                'purchase_reference' => $reference,
            ]);
            $locked->forceFill(['plan_id' => $plan->id])->save();
            /** @var User $user */
            $user = User::query()->findOrFail($locked->user_id);
            $this->invoices->forSubscription($subscription, $plan, $user);
            DB::afterCommit(fn (): mixed => $this->charger->invalidateMirror($wallet));

            return $subscription;
        });
    }
}
