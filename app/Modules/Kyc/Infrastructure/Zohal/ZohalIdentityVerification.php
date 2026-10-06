<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Infrastructure\Zohal;

use App\Modules\Kyc\Application\Dto\VerificationResultDto;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class ZohalIdentityVerification implements IdentityVerificationPort
{
    private const ENDPOINTS = ['shahkar' => 'shahkar', 'national_identity' => 'national_identity_inquiry', 'company' => 'company_inquiry', 'postal_code' => 'postal_code_inquiry', 'enamad' => 'enamad_inquiry'];

    public function __construct(private string $token, private string $baseUrl, private int $timeout = 12) {}

    public function verify(string $check, array $input): VerificationResultDto
    {
        if (trim($this->token) === '') {
            return new VerificationResultDto('warning', 'سرویس زحل تنظیم نشده است.');
        }
        if (! isset(self::ENDPOINTS[$check])) {
            return new VerificationResultDto('warning', 'نوع استعلام معتبر نیست.');
        }
        try {
            $response = Http::withToken($this->token)->acceptJson()->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/').'/services/inquiry/'.self::ENDPOINTS[$check], $input);
            $body = $response->json();
        } catch (Throwable) {
            return new VerificationResultDto('warning', 'ارتباط با سرویس استعلام برقرار نشد.');
        }
        $raw = is_array($body) ? $this->sanitize($body) : [];
        if (! $response->successful() || ! is_array($body) || ! is_array($body['response_body'] ?? null)) {
            return new VerificationResultDto('warning', 'پاسخ سرویس استعلام معتبر نیست.', raw: $raw);
        }
        $envelope = $body['response_body'];
        if (($body['result'] ?? null) !== 1 || ! empty($envelope['error_code'])) {
            return new VerificationResultDto('warning', 'سرویس استعلام خطا برگرداند؛ بررسی دستی لازم است.', raw: $raw);
        }
        $data = $envelope['data'] ?? null;
        if (! is_array($data) || $data === []) {
            return new VerificationResultDto('warning', 'اطلاعات کافی در پاسخ وجود ندارد.', raw: $raw);
        }
        $passed = match ($check) {
            'shahkar' => is_bool($data['matched'] ?? null) ? $data['matched'] : null,
            'national_identity' => is_bool($data['matched'] ?? null) && is_bool($data['alive'] ?? null) ? $data['matched'] && $data['alive'] : null,
            'company' => isset($data['national_id'], $data['name']) ? empty($data['activity_end_date']) && (($data['status'] ?? '') === 'فعال') : null,
            'enamad' => isset($data['domain']) && is_bool($data['expired'] ?? null) ? ! $data['expired'] : null,
            'postal_code' => isset($data['postal_code']) ? (string) $data['postal_code'] === ($input['postal_code'] ?? '') : null,
        };

        return new VerificationResultDto($passed === null ? 'warning' : ($passed ? 'passed' : 'failed'), $passed === null ? 'پاسخ نیازمند بررسی دستی است.' : ($passed ? 'استعلام موفق بود.' : 'اطلاعات تأیید نشد.'), $this->sanitize($data), $raw);
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/token|authorization|secret|password|cookie|api.?key/i', (string) $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $values[$key] = mb_substr(str_replace($this->token, '[redacted]', $value), 0, 4000);
            }
        }

        return $values;
    }
}
