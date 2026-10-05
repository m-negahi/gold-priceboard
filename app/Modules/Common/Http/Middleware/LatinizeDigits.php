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
 * 🔴 FAGHAT `text/html` — va fail-CLOSED: content-type e NA-MA'LUM ham rad
 * mishe.
 *
 * Do dalil، har do vaghei:
 *   1. **JSON e Livewire**: snapshot esh checksum dare، pas bazneveshtan e
 *      byte haye un state e component ro na-motabar mikone. (Fragment haye
 *      Livewire adadeshun az helper ha miad ke hala Latin ast، pas niazi
 *      ham nist.)
 *   2. **Payload e emza-shode** (Phase 4: API ba HMAC): avaz kardan e yek
 *      raghame vasat e body، emza ro mishkune. Un ja'li tarin nou' e bug
 *      ast — «emza ghalat ast» va hichkas nemidune chera.
 *
 * Be hamin dalil shart «agar `text/html` NIST rad kon» ast، na «agar
 * ma'lum ast va `text/html` nist rad kon». Yek pasokh e bedun e header
 * ghablan AZ qalat rad mishod va alan rad MISHE.
 *
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

        // Fail-closed: faghat `text/html` e SARIH dast mikhore.
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            $response->setContent(strtr($content, self::DIGITS));
        }

        return $response;
    }
}
