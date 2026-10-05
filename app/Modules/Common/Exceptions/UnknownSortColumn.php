<?php

declare(strict_types=1);

namespace App\Modules\Common\Exceptions;

/** `?sort=` sotooni ke Query e'lam nakarde — hich vaght be ORDER BY-e delkhah tabdil nemishe. */
final class UnknownSortColumn extends InvalidTableRequestException
{
    public function __construct(public readonly string $column)
    {
        parent::__construct('ستون مرتب‌سازی معتبر نیست.');
    }

    public function field(): string
    {
        return 'sort';
    }
}
