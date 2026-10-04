<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\ValueObjects;

/**
 * Natije ye `PriceSanity::check()`.
 *
 * `holdAsOutlier` ya'ni: in meghdar rad shod, vali negah esh dar — agar tick e
 * ba'di hamin hodud ro ta'eed kard, harekat vaghei bude na parse error.
 * (Ghanun e «do outlier e mote'ali vaghei-an, yeki parse error ast».)
 */
final readonly class SanityVerdict
{
    private function __construct(
        public bool $accepted,
        public ?string $reason = null,
        public bool $holdAsOutlier = false,
        public bool $confirmedOutlier = false,
    ) {}

    public static function accept(bool $confirmedOutlier = false): self
    {
        return new self(accepted: true, confirmedOutlier: $confirmedOutlier);
    }

    public static function reject(string $reason, bool $holdAsOutlier = false): self
    {
        return new self(accepted: false, reason: $reason, holdAsOutlier: $holdAsOutlier);
    }

    public function rejected(): bool
    {
        return ! $this->accepted;
    }
}
