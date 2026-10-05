<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use App\Modules\Common\Helpers\StringHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raghamha ye Farsi/Arabi tu PARAMETER haye route.
 *
 * Chera jodagane az `NormalizeStringMiddleware`: un yeki faghat input
 * (query/body) ro dast mizane، na parameter haye route ro. Yek URL e
 * copy-paste shode az yek matn e Farsi mitune `/providers/۱۰۲۲` bashe va un
 * vaght route model binding (ya yek cast e int) saket 404 ya khata ye SQL
 * mide.
 *
 * ⚠️ Faghat RAGHAM — horoof dast nemikhoran: yek slug e Farsi motabar ast va
 * tabdil e `ك→ک` tu URL mitune un ro beshkune.
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
