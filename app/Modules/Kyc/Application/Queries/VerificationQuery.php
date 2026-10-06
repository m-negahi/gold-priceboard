<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class VerificationQuery
{
    /** @return list<array<string, mixed>> */
    public function forTicket(int $ticketId): array
    {
        return $this->rows('ticket_id', $ticketId);
    }

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        return $this->rows('user_id', $userId);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $column, int $id): array
    {
        return DB::table('identity_verification_runs')->where($column, $id)->latest('id')->limit(20)->get()->map(static fn ($run): array => [
            'id' => $run->id, 'status' => $run->status, 'at' => Jalali::dateTime(CarbonImmutable::parse($run->created_at)),
            'checks' => self::checks(json_decode($run->results ?? '{}', true)),
        ])->all();
    }

    /** @param array<string, array<string, mixed>> $checks
     * @return list<array<string, mixed>>
     */
    private static function checks(array $checks): array
    {
        $labels = ['shahkar' => 'شاهکار', 'national_identity' => 'هویت', 'company' => 'شرکت', 'postal_code' => 'کد پستی', 'address_match' => 'تطبیق نشانی'];
        $rows = [];
        foreach ($checks as $key => $result) {
            $rows[] = [...$result, 'label' => $labels[$key] ?? $key,
                'icon' => match ($result['status']) {
                    'passed' => 'circle-check', 'failed' => 'circle-x', default => 'alert'
                },
                'rawPreview' => json_encode($result['raw'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ];
        }

        return $rows;
    }
}
