<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Payments;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ZibalGateway implements PaymentGatewayPort
{
    private const BASE_URL = 'https://gateway.zibal.ir';

    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array
    {
        $merchant = $this->merchant();
        if ($amountRial <= 1000) {
            throw new RuntimeException('mablagh e pardakht bayad bish az 1000 rial bashad');
        }
        $data = Http::timeout(10)->acceptJson()->post(self::BASE_URL.'/v1/request', [
            'merchant' => $merchant,
            'amount' => $amountRial,
            'callbackUrl' => $callbackUrl,
            'orderId' => $orderId,
        ])->throw()->json();
        if (! is_array($data) || (int) ($data['result'] ?? 0) !== 100 || ! isset($data['trackId'])) {
            throw new RuntimeException('darkhast e dargah rad shod');
        }
        $trackId = (string) $data['trackId'];
        if (preg_match('/^\d{1,64}$/', $trackId) !== 1) {
            throw new RuntimeException('shenase ye peygiri e dargah na-motabar ast');
        }

        return ['track_id' => $trackId, 'url' => self::BASE_URL.'/start/'.$trackId];
    }

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array
    {
        $merchant = $this->merchant();
        $data = Http::timeout(10)->acceptJson()->post(self::BASE_URL.'/v1/verify', [
            'merchant' => $merchant,
            'trackId' => $trackId,
        ])->throw()->json();
        if (! is_array($data)) {
            throw new RuntimeException('pasokh e dargah na-motabar ast');
        }
        if ((int) ($data['result'] ?? 0) === 201) {
            $data = Http::timeout(10)->acceptJson()->post(self::BASE_URL.'/v1/inquiry', [
                'merchant' => $merchant,
                'trackId' => $trackId,
            ])->throw()->json();
            if (! is_array($data)) {
                throw new RuntimeException('pasokh e estelam e dargah na-motabar ast');
            }
        }
        $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_INT);
        $reportedTrackId = isset($data['trackId']) ? (string) $data['trackId'] : $trackId;

        return [
            'verified' => (int) ($data['result'] ?? 0) === 100
                && (int) ($data['status'] ?? 0) === 1
                && $reportedTrackId === $trackId,
            'amount_rial' => is_int($amount) ? $amount : 0,
            'reference' => isset($data['refNumber']) ? (string) $data['refNumber'] : null,
            'data' => array_intersect_key($data, array_flip(['result', 'status', 'amount', 'refNumber', 'paidAt', 'orderId', 'trackId'])),
        ];
    }

    private function merchant(): string
    {
        $merchant = (string) config('billing.zibal.merchant', '');
        if ($merchant === '') {
            throw new RuntimeException('dargah e pardakht tanzim nashode ast');
        }

        return $merchant;
    }
}
