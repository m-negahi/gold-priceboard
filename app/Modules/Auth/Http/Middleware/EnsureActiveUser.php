<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session e BAZ e karbar e disable-shode (P6-05) — ru HAME ye group e `web`.
 *
 * ── Chera middleware، na faghat pak kardan e `sessions` ──────────────
 * Pak kardan e radif haye `sessions` be driver vabaste-st (database/redis/
 * file) va cookie e «remember me» ro nemigire. Inja HAR request ba
 * karbar e TAZE-load-shode az DB check mishe — har driver، har cookie.
 * Hazine: hich query e ezafe — guard karbar ro hamin alan load karde.
 *
 * Guest ha dast nemikhoran؛ karbar e disable logout + session invalidate
 * mishe: HTML -> /login ba payam، JSON -> 403.
 */
final readonly class EnsureActiveUser
{
    public const FLASH_KEY = 'auth.disabled';

    public function __construct(private AuthFactory $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'حساب کاربری شما غیرفعال شده است.';

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], 403);
        }

        $request->session()->flash(self::FLASH_KEY, $message);

        return redirect()->route('login');
    }
}
