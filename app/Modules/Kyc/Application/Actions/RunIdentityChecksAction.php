<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Actions;

use App\Modules\Common\Helpers\PhoneHelper;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;
use Illuminate\Support\Facades\DB;

final readonly class RunIdentityChecksAction
{
    public function __construct(private IdentityVerificationPort $verification) {}

    public function execute(int $runId): void
    {
        // Claim e atomic: retry e queue hich estelam e pooli ro tekrar nemikone.
        if (DB::table('identity_verification_runs')->where('id', $runId)->where('status', 'queued')->update(['status' => 'running', 'updated_at' => now()]) !== 1) {
            return;
        }
        $run = DB::table('identity_verification_runs')->find($runId);
        $input = json_decode($run->input, true, flags: JSON_THROW_ON_ERROR);
        $national = (string) ($input['representative_national_code'] ?? $input['national_code'] ?? '');
        $birth = (string) ($input['representative_birth_date'] ?? $input['birth_date'] ?? '');
        $checks = [
            'shahkar' => ['mobile' => PhoneHelper::toLocal((string) ($input['mobile'] ?? '')) ?? '', 'national_code' => $national],
            'national_identity' => ['national_code' => $national, 'birth_date' => $birth],
            'postal_code' => ['postal_code' => (string) ($input['postal_code'] ?? '')],
        ];
        if (! empty($input['company_national_id'])) {
            $checks['company'] = ['national_id' => (string) $input['company_national_id']];
        }
        $results = [];
        foreach ($checks as $check => $arguments) {
            $results[$check] = in_array('', $arguments, true)
                ? ['status' => 'warning', 'message' => 'اطلاعات لازم برای استعلام کامل نیست.', 'data' => [], 'raw' => []]
                : $this->verification->verify($check, $arguments)->toArray();
            if ($check === 'company' && $results[$check]['status'] === 'passed') {
                $company = $results[$check]['data'];
                $matches = $this->normalize((string) ($company['name'] ?? '')) === $this->normalize((string) ($input['registered_name'] ?? ''))
                    && (string) ($company['national_id'] ?? '') === (string) $input['company_national_id']
                    && (string) ($company['postal_code'] ?? '') === (string) ($input['postal_code'] ?? '');
                if (! $matches) {
                    $results[$check]['status'] = 'failed';
                    $results[$check]['message'] = 'نام، شناسه ملی یا کد پستی با اطلاعات شرکت تطبیق ندارد.';
                }
                if ($this->normalize((string) ($company['address'] ?? '')) !== $this->normalize((string) ($input['address'] ?? ''))) {
                    $results['address_match'] = ['status' => 'warning', 'message' => 'نشانی شرکت با نشانی ثبت‌شده یکسان نیست؛ بررسی دستی لازم است.', 'data' => [], 'raw' => []];
                }
            }
            DB::table('identity_verification_runs')->where('id', $runId)->update(['results' => json_encode($results, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }
        DB::table('identity_verification_runs')->where('id', $runId)->update(['status' => 'completed', 'updated_at' => now()]);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[\s\x{200c}،,]+/u', '', str_replace(['ي', 'ك'], ['ی', 'ک'], trim($value))) ?? '';
    }
}
