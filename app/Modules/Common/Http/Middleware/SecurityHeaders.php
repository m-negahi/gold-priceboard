<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header haye amniati ru har pasokh.
 *
 * Bi-halat va Octane-safe — hich chizi beyn e request ha negah dashte
 * nemishe.
 *
 * ── Chera CSP INJA nist ─────────────────────────────────────────────
 * Yek CSP e vaghei be nonce e per-request niaz dare (Alpine va Livewire har
 * do inline script tolid mikonan) va bedun e un bayad `unsafe-inline`
 * bezarim — ke CSP ro amalan bi-ma'ni mikone. Yek CSP e ja'li badtar az
 * nabudan esh ast: hess e amniat mide bedun e amniat. CSP ba nonce tu
 * `P3-03` (layout) miad، jayi ke `@script` ha ma'lum-and.
 */
final class SecurityHeaders
{
    /**
     * Header haye SABET.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        // Jologiri az MIME sniffing: yek upload e `.jpg` ke vaghe'an HTML ast
        // nabayad be onvan e HTML ejra beshe.
        'X-Content-Type-Options' => 'nosniff',

        // Site e ma hichvaght tu iframe nemishine -> clickjacking.
        'X-Frame-Options' => 'DENY',

        /*
         * Referrer: faghat origin baraye maghsad haye CROSS-origin.
         *
         * 🔴 `strict-origin-when-cross-origin` amdi-e. URL haye panel mitunan
         * shenase dashte bashan (`/dashboard/providers/7`)؛ ba policy e
         * default e ba'zi browser ha un masir e kamel be har domain e
         * biruni-i ke karbar ru un click kone frestade mishe.
         */
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Hich API e hassas-i lazem nadarim.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',

        // Faghat baraye pasokh haye API — jologiri az tafsir e JSON be onvan
        // chizi ke nist.
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            // `setIfNone`-manand: agar yek route amdan header e khodesh ro
            // gozashte (masalan yek safhe ye embed-shodani)، un ro
            // BAZNEVISI nemikonim.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        /*
         * HSTS faghat ru HTTPS.
         *
         * 🔴 Ru HTTP frestadanesh bi-fayede nist — KHATARNAK ast: tu dev
         * (`http://localhost:8300`) browser un domain ro baraye mah ha
         * HTTPS-only mikone va ba'd dev e hame ye projeh haye digar ru hamun
         * `localhost` misheknae. Pak kardanesh dasti-e va aksar bi-sar-o-seda
         * ruz ha vaght migire ta kasi befahme chera.
         */
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
