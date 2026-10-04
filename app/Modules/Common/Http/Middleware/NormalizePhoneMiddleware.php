<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\PhoneHelper;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Normalize phone-like fields to E.164 format (+989XXXXXXXXX).
 * This middleware should run AFTER NormalizeStringMiddleware so that
 * Persian/Arabic digits are already converted.
 * NOTE: If normalization fails (invalid format) the original value is
 * preserved. Use a validation rule in the form request to reject invalid
 * phone numbers.
 */
final class NormalizePhoneMiddleware extends TransformsRequest
{
    /**
     * Field names (or *_suffix) treated as phone numbers.
     */
    protected const PHONE_FIELDS = [
        'phone',
        'mobile',
        'phone_number',
        'mobile_number',
        'tel',
        'telephone',
    ];

    protected function transform($key, $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (! $this->isPhoneField($key)) {
            return $value;
        }

        return PhoneHelper::normalize($value) ?? $value;
    }

    private function isPhoneField(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::PHONE_FIELDS as $field) {
            if ($key === $field || str_ends_with($key, '_'.$field)) {
                return true;
            }
        }

        return false;
    }
}
