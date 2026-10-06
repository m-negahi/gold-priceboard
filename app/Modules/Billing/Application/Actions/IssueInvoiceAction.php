<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Api\Infrastructure\Persistence\Models\ApiPlan;
use App\Modules\Api\Infrastructure\Persistence\Models\SiteSubscription;
use App\Modules\Auth\Domain\Enums\UserLevel;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingInvoice;
use App\Modules\Billing\Infrastructure\Persistence\Models\WalletTopupPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class IssueInvoiceAction
{
    public function forTopup(WalletTopupPayment $payment, User $user): ?BillingInvoice
    {
        return $this->issue(
            $user, 'wallet_topup', $payment->id, $payment->amount_minor,
            [['description' => 'افزایش اعتبار کیف پول', 'quantity' => 1, 'unit_price_rial' => $payment->amount_minor, 'amount_rial' => $payment->amount_minor]],
        );
    }

    public function forSubscription(SiteSubscription $subscription, ApiPlan $plan, User $user): ?BillingInvoice
    {
        return $this->issue(
            $user, 'subscription', $subscription->id, $subscription->price_minor,
            [[
                'description' => 'اشتراک '.$plan->name.' برای سایت #'.$subscription->api_client_id,
                'quantity' => 1,
                'unit_price_rial' => $subscription->price_minor,
                'amount_rial' => $subscription->price_minor,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'calls_included' => $subscription->calls_included,
            ]],
        );
    }

    /**
     * Sodur e DASTI az panel e mali: `kind` + id e manba'. `null` ya'ni
     * karbar `invoice_verified` nist (ya profile nadare) — hamun ghanun e
     * sodur e khodkar.
     *
     * @throws InvalidArgumentException manba' peyda nashod ya topup verify nashode
     */
    public function forSource(string $kind, int $sourceId): ?BillingInvoice
    {
        if ($kind === 'wallet_topup') {
            $payment = WalletTopupPayment::query()->find($sourceId);

            if ($payment === null || $payment->status !== 'verified') {
                throw new InvalidArgumentException('پرداخت تأییدشده‌ای با این شناسه پیدا نشد.');
            }

            return $this->forTopup($payment, User::query()->findOrFail($payment->user_id));
        }

        if ($kind === 'subscription') {
            $subscription = SiteSubscription::query()->find($sourceId);

            if ($subscription === null) {
                throw new InvalidArgumentException('اشتراکی با این شناسه پیدا نشد.');
            }

            return $this->forSubscription(
                $subscription,
                ApiPlan::query()->findOrFail($subscription->api_plan_id),
                User::query()->findOrFail($subscription->user_id),
            );
        }

        throw new InvalidArgumentException('نوع سند ناشناخته است.');
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function issue(User $user, string $kind, int $sourceId, int $amountRial, array $lines): ?BillingInvoice
    {
        if ($user->level !== UserLevel::InvoiceVerified || ! is_array($user->invoice_profile) || $user->invoice_profile === []) {
            return null;
        }

        return DB::transaction(function () use ($user, $kind, $sourceId, $amountRial, $lines): BillingInvoice {
            $sourceColumn = $kind === 'subscription' ? 'site_subscription_id' : 'wallet_topup_payment_id';
            $existing = BillingInvoice::query()->where($sourceColumn, $sourceId)->first();
            if ($existing !== null) {
                return $existing;
            }
            $at = CarbonImmutable::now();
            $year = (int) $at->format('Y');
            DB::table('billing_invoice_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $sequence = DB::table('billing_invoice_sequences')->where('year', $year)->lockForUpdate()->first();
            $next = (int) $sequence->last_number + 1;
            DB::table('billing_invoice_sequences')->where('year', $year)->update(['last_number' => $next]);

            return BillingInvoice::query()->create([
                'user_id' => $user->id,
                $sourceColumn => $sourceId,
                'kind' => $kind,
                'number' => 'PB-'.$year.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'amount_minor' => $amountRial,
                'buyer_snapshot' => [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'national_code' => $user->national_code,
                    'customer_type' => $user->customer_type->value,
                    'profile' => $user->invoice_profile,
                ],
                'lines_snapshot' => $lines,
                'issued_at' => $at,
            ]);
        });
    }
}
