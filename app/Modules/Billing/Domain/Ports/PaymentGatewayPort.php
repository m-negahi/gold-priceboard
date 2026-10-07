<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Ports;

/**
 * Dargah e pardakht (IPG). Tanha adapter: `VandarGateway` (Zibal 2026-10-07 hazf shod).
 *
 * `track_id` shenase ye dargah ast (Vandar: token e alfanumeric)؛ reference e
 * ledger `{name}:{track_id}` mishe ta dargah haye ehtemali ye ayande ba ham ghati nashan.
 */
interface PaymentGatewayPort
{
    /** Esm e kutah e dargah (`vandar`) — tu reference e ledger va meta. */
    public function name(): string;

    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array;

    /** URL e safhe ye pardakht baraye yek track_id e ghablan sakhte-shode (masir e idempotent). */
    public function redirectUrl(string $trackId): string;

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array;
}
