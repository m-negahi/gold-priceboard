<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Ports;

interface PaymentGatewayPort
{
    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array;

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array;
}
