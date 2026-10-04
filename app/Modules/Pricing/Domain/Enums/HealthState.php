<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Enums;

use App\Modules\Common\Traits\EnumTrait;

/** Salamat e yek provider — ru `consecutive_failures` mohasebe mishe. */
enum HealthState: string
{
    use EnumTrait;

    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    public static function fromFailures(int $consecutiveFailures): self
    {
        return match (true) {
            $consecutiveFailures === 0 => self::Healthy,
            $consecutiveFailures >= 10 => self::Down,
            $consecutiveFailures >= 3 => self::Degraded,
            default => self::Healthy,
        };
    }

    /** Token e rang tu design system. */
    public function colorToken(): string
    {
        return match ($this) {
            self::Healthy => 'ok',
            self::Degraded => 'degraded',
            self::Down => 'down-state',
            self::Unknown => 'flat',
        };
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::Healthy->value => 'سالم',
            self::Degraded->value => 'نیازمند بررسی',
            self::Down->value => 'قطع',
            self::Unknown->value => 'نامشخص',
        ];
    }
}
