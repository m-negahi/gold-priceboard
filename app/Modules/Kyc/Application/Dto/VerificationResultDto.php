<?php

declare(strict_types=1);

namespace App\Modules\Kyc\Application\Dto;

final class VerificationResultDto
{
    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(public readonly string $status, public readonly string $message, public readonly array $data = [], public readonly array $raw = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'message' => $this->message, 'data' => $this->data, 'raw' => $this->raw];
    }
}
