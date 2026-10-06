<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Ports;

/**
 * Dargah e pardakht (IPG). Adapter ha: `VandarGateway` (default)، `ZibalGateway`.
 *
 * `track_id` shenase ye dargah ast (Zibal: adad، Vandar: token e alfanumeric)؛
 * reference e ledger `{name}:{track_id}` mishe ta do dargah ba ham ghati nashan.
 */
interface PaymentGatewayPort
{
    /** Esm e kutah e dargah (`vandar`/`zibal`) — tu reference e ledger va meta. */
    public function name(): string;

    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array;

    /** URL e safhe ye pardakht baraye yek track_id e ghablan sakhte-shode (masir e idempotent). */
    public function redirectUrl(string $trackId): string;

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array;
}
