<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Payments;

use App\Modules\Billing\Domain\Ports\PaymentGatewayPort;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vandar IPG (`ipg.vandar.io`) — entekhab e sahebe proje (2026-10-06).
 *
 * Jaryan (mostanad e rasmi، v3؛ v4 hamun shekl ba segment e version):
 *   1. `POST /api/{v}/send`  {api_key, amount (RIAL ≥ 1000), callback_url, factorNumber}
 *        → {status: 1, token}
 *   2. redirect e karbar be `/{v}/{token}`
 *   3. callback: `?token=…&payment_status=OK|NOK`
 *   4. `POST /api/{v}/verify` {api_key, token}
 *        → {status: 1|2|3, amount, realAmount, wage, transId, factorNumber, …}
 *      status 1 = movafagh، 2 = ghablan verify shode (idempotent e khod e dargah)،
 *      3 = 20 daghighe gozashte. Khata: {status: 0, errors: [...]}.
 *
 * ── Nokte ha ───────────────────────────────────────────────────────
 * - `amount` e verify bayad DAGHIGHAN barabar e mablagh e darkhasti bashe
 *   (`TopupPaymentAction` moghayese mikone؛ ekhtelaf → `mismatch`). Agar
 *   karmozd ru pazirande bashe، Vandar `amount` ro shamel e karmozd mide —
 *   pas tanzim e «کارمزد از پرداخت‌کننده» tu panel e Vandar lazem ast، ya
 *   admin radif e mismatch ro dasti tasviye kone.
 * - `factorNumber` = id e `wallet_topup_payments` (hamun `orderId` e ma) —
 *   verify un ro bar migardune va Action ba id e pardakht check mikone.
 * - Hich kelid-i log nemishe؛ `data` faghat field haye bi-khatar.
 */
final readonly class VandarGateway implements PaymentGatewayPort
{
    private const BASE_URL = 'https://ipg.vandar.io';

    public function name(): string
    {
        return 'vandar';
    }

    public function redirectUrl(string $trackId): string
    {
        return self::BASE_URL.'/'.$this->version().'/'.$trackId;
    }

    /** @return array{track_id: string, url: string} */
    public function request(int $amountRial, string $callbackUrl, string $orderId): array
    {
        $apiKey = $this->apiKey();

        if ($amountRial < 1000) {
            throw new RuntimeException('mablagh e pardakht bayad hadaghal 1000 rial bashad');
        }

        $data = Http::timeout(10)->acceptJson()->post(self::BASE_URL.'/api/'.$this->version().'/send', [
            'api_key' => $apiKey,
            'amount' => $amountRial,
            'callback_url' => $callbackUrl,
            'factorNumber' => $orderId,
            'description' => 'شارژ کیف پول #'.$orderId,
        ])->throw()->json();

        if (! is_array($data) || (int) ($data['status'] ?? 0) !== 1 || ! is_string($data['token'] ?? null)) {
            throw new RuntimeException('darkhast e dargah rad shod: '.$this->errors($data));
        }

        $token = $data['token'];

        if (preg_match('/^[A-Za-z0-9_-]{1,128}$/', $token) !== 1) {
            throw new RuntimeException('token e dargah na-motabar ast');
        }

        return ['track_id' => $token, 'url' => $this->redirectUrl($token)];
    }

    /** @return array{verified: bool, amount_rial: int, reference: string|null, data: array<string, mixed>} */
    public function verify(string $trackId): array
    {
        $data = Http::timeout(10)->acceptJson()->post(self::BASE_URL.'/api/'.$this->version().'/verify', [
            'api_key' => $this->apiKey(),
            'token' => $trackId,
        ])->throw()->json();

        if (! is_array($data)) {
            throw new RuntimeException('pasokh e dargah na-motabar ast');
        }

        $status = (int) ($data['status'] ?? 0);
        $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_INT);

        return [
            // 1 = movafagh، 2 = ghablan verify shode — har do pardakht e vaghei-and.
            'verified' => in_array($status, [1, 2], true) && is_int($amount) && $amount > 0,
            'amount_rial' => is_int($amount) ? $amount : 0,
            'reference' => isset($data['transId']) ? (string) $data['transId'] : null,
            'data' => [
                ...array_intersect_key($data, array_flip(['status', 'amount', 'realAmount', 'wage', 'transId', 'factorNumber', 'paymentDate', 'cardNumber', 'message'])),
                // Gharardad e Action: `orderId` = shenase ye pardakht e ma.
                'orderId' => isset($data['factorNumber']) ? (string) $data['factorNumber'] : null,
            ],
        ];
    }

    private function apiKey(): string
    {
        $key = (string) config('billing.vandar.api_key', '');

        if ($key === '') {
            throw new RuntimeException('dargah e pardakht tanzim nashode ast');
        }

        return $key;
    }

    private function version(): string
    {
        $version = (string) config('billing.vandar.version', 'v3');

        return in_array($version, ['v3', 'v4'], true) ? $version : 'v3';
    }

    private function errors(mixed $data): string
    {
        $errors = is_array($data) ? ($data['errors'] ?? []) : [];

        return is_array($errors) && $errors !== [] ? implode('، ', array_map('strval', $errors)) : 'pasokh e na-motabar';
    }
}
