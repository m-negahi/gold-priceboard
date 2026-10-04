<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\StringHelper;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Normalizes every string in the request input:
 * Arabic letters → Persian equivalents, and Persian/Arabic digits → English.
 * Should run BEFORE NormalizePhoneMiddleware so phone fields already have
 * English digits when they are normalized to E.164.
 */
final class NormalizeStringMiddleware extends TransformsRequest
{
    protected function transform($key, $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (! StringHelper::hasNonAscii($value)) {
            return $value;
        }

        return StringHelper::normalize($value);
    }
}
