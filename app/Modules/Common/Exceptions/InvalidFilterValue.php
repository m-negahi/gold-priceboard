<?php

declare(strict_types=1);

namespace App\Modules\Common\Exceptions;

/** `filters[key]=` meghdari kharej az list-e mojaz-e un filter. */
final class InvalidFilterValue extends InvalidTableRequestException
{
    public function __construct(public readonly string $key, public readonly string $value)
    {
        parent::__construct('مقدار فیلتر معتبر نیست.');
    }

    public function field(): string
    {
        return "filters.{$this->key}";
    }
}
