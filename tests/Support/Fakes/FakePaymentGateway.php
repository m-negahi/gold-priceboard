<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;

final class FakePaymentGateway implements PaymentGatewayPort
{
    public int $requestCount = 0;

    public int $verifyCount = 0;

    public function __construct(public int $verifiedAmountRial, public bool $isVerified = true) {}

    public function name(): string
    {
        return 'fake';
    }

    public function redirectUrl(string $trackId): string
    {
        return 'https://gateway.zibal.ir/start/'.$trackId;
    }

    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array
    {
        $this->requestCount++;

        return ['track_id' => '123456', 'url' => 'https://gateway.zibal.ir/start/123456'];
    }

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array
    {
        $this->verifyCount++;

        return [
            'verified' => $this->isVerified,
            'amount_rial' => $this->verifiedAmountRial,
            'reference' => 'bank-ref-1',
            'data' => ['result' => $this->isVerified ? 100 : 202, 'amount' => $this->verifiedAmountRial],
        ];
    }
}
