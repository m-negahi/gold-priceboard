<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Policy e namayesh e adad: **raghame Latin (0-9)** tu kol e site.
 *
 * Har raghame Farsi/Arabi ye baghi-munde tu khoruji e HTML (literal haye Blade,
 * mohtava ye zakhire-shode tu DB) ro be raghame Latin tabdil mikone — laye ye
 * nahayi va mokammel e helper ha (Jalali/StringHelper) ke khodeshun dige
 * raghame Farsi tolid nemikonan.
 *
 * 🔴 FAGHAT text/html — JSON e Livewire dast nemikhore chun snapshot esh
 * checksum dare va dastkari esh state e component ro na-motabar mikone
 * (fragment haye Livewire adadeshun az helper ha miad ke hala Latin ast).
 * Bi-halat va Octane-safe.
 */
final class LatinizeDigits
{
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $response->setContent(strtr($content, self::DIGITS));
        }

        return $response;
    }
}
