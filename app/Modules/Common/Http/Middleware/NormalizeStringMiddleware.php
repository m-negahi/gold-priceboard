<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\StringHelper;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Normalize kardan e HAR string e vorudi: horoof e Arabi -> Farsi (`ي→ی`،
 * `ك→ک`) va raghamha ye Farsi/Arabi -> English.
 *
 * 🔴 Bayad GHABL az `NormalizePhoneMiddleware` ejra beshe، vagarna un yeki
 * yek shomare ba raghame Farsi (`۰۹۱۲…`) mibine va nemitune parse kone.
 * Tartib e sabt tu `bootstrap/app.php` load-bearing ast.
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
