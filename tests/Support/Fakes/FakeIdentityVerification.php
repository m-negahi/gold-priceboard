<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Kyc\Application\Dto\VerificationResultDto;
use App\Modules\Kyc\Domain\Ports\IdentityVerificationPort;

final class FakeIdentityVerification implements IdentityVerificationPort
{
    /** @var list<array{check: string, input: array<string, string>}> */
    public array $calls = [];

    /** @var array<string, VerificationResultDto> */
    public array $results = [];

    public function verify(string $check, array $input): VerificationResultDto
    {
        $this->calls[] = ['check' => $check, 'input' => $input];

        return $this->results[$check] ?? new VerificationResultDto('warning', 'پاسخ آزمایشی تنظیم نشده است.');
    }
}
