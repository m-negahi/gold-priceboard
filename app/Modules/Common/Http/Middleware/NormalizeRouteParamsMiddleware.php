<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\StringHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalizes Persian/Arabic digits in route parameters.
 * Prevents SQL / binding errors when a route receives e.g. "۱۰۲۲" instead
 * of "1022" (route model binding, numeric IDs, etc.).
 */
final class NormalizeRouteParamsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route) {
            foreach ($route->parameters() as $key => $value) {
                if (is_string($value) && StringHelper::hasNonAscii($value)) {
                    $route->setParameter($key, StringHelper::numbersToEnglish($value));
                }
            }
        }

        return $next($request);
    }
}
