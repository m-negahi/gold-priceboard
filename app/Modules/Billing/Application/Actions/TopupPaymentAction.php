<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Api\Application\Actions\ChargeApiCallAction;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use App\Modules\Billing\Infrastructure\Persistence\Models\WalletTopupPayment;
use App\Modules\Wallet\Application\Actions\WalletAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class TopupPaymentAction
{
    public function __construct(
        private PaymentGatewayPort $gateway,
        private WalletAction $wallets,
        private ChargeApiCallAction $charger,
        private IssueInvoiceAction $invoices,
    ) {}

    /** @return array{payment: WalletTopupPayment, url: string} */
    public function start(int $userId, int $amountRial, string $requestKey, string $callbackUrl): array
    {
        if ($amountRial <= 1000 || $amountRial > 1_000_000_000_000_000 || $requestKey === '' || mb_strlen($requestKey) > 36) {
            throw new InvalidArgumentException('mablagh ya kelid e pardakht na-motabar ast');
        }

        try {
            return DB::transaction(function () use ($userId, $amountRial, $requestKey, $callbackUrl): array {
                $payment = WalletTopupPayment::query()
                    ->where('user_id', $userId)->where('request_key', $requestKey)
                    ->lockForUpdate()->first();
                if ($payment !== null) {
                    if ($payment->amount_minor !== $amountRial || $payment->track_id === null) {
                        throw new InvalidArgumentException('darkhast e tekrari e pardakht na-motabar ast');
                    }

                    return ['payment' => $payment, 'url' => $this->gateway->redirectUrl((string) $payment->track_id)];
                }
                $payment = WalletTopupPayment::query()->create([
                    'user_id' => $userId, 'amount_minor' => $amountRial,
                    'request_key' => $requestKey, 'status' => 'initiated',
                ]);
                $gateway = $this->gateway->request($amountRial, $callbackUrl, (string) $payment->id);
                $payment->forceFill(['track_id' => $gateway['track_id'], 'status' => 'pending'])->save();

                return ['payment' => $payment, 'url' => $gateway['url']];
            });
        } catch (QueryException $exception) {
            $payment = WalletTopupPayment::query()->where('user_id', $userId)->where('request_key', $requestKey)->first();
            if ($payment === null) {
                throw $exception;
            }
            if ($payment->amount_minor !== $amountRial || $payment->track_id === null) {
                throw new InvalidArgumentException('darkhast e tekrari e pardakht na-motabar ast', previous: $exception);
            }

            return ['payment' => $payment, 'url' => $this->gateway->redirectUrl((string) $payment->track_id)];
        }
    }

    public function verify(string $trackId): WalletTopupPayment
    {
        return DB::transaction(function () use ($trackId): WalletTopupPayment {
            /** @var WalletTopupPayment $payment */
            $payment = WalletTopupPayment::query()->where('track_id', $trackId)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'verified') {
                return $payment;
            }
            if ($payment->status !== 'pending') {
                throw new InvalidArgumentException('vaziat e pardakht baraye verify na-motabar ast');
            }

            $result = $this->gateway->verify($trackId);
            if (! $result['verified']) {
                throw new RuntimeException('pardakht tavasot e dargah taeed nashod');
            }
            if (isset($result['data']['orderId']) && (string) $result['data']['orderId'] !== (string) $payment->id) {
                throw new RuntimeException('shenase ye sefaresh ba pardakht motabeghat nadarad');
            }
            if ($result['amount_rial'] !== $payment->amount_minor) {
                $payment->forceFill(['status' => 'mismatch', 'verify_data' => $result['data']])->save();

                return $payment;
            }

            $wallet = $this->wallets->forOwner($payment->user_id);
            $this->wallets->credit($wallet, $payment->amount_minor, 'topup', $this->gateway->name().':'.$trackId, [
                'gateway' => $this->gateway->name(), 'payment_id' => $payment->id,
            ]);
            $payment->forceFill([
                'status' => 'verified', 'gateway_reference' => $result['reference'],
                'verify_data' => $result['data'], 'verified_at' => CarbonImmutable::now(),
            ])->save();

            /** @var User $user */
            $user = User::query()->findOrFail($payment->user_id);
            $this->invoices->forTopup($payment, $user);
            DB::afterCommit(fn (): mixed => $this->charger->invalidateMirror($wallet));

            return $payment;
        });
    }
}
