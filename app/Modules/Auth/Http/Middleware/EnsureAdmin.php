<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Access\Application\Queries\AccessQuery;
use App\Modules\Access\Domain\PermissionCatalog;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Darvaze ye panel: faghat admin.
 *
 * ── Guest -> login، karbar e gheyr-admin -> 404 ─────────────────────
 * 🔴 404، na 403: yek 403 be karbar e login-shode mige «in masir VOJUD
 * DARE vali to nemituni» — ya'ni masir e panel ro ta'eed mikone. Panel e
 * admin baraye gheyr-admin aslan nabayad «vojud dashte bashe».
 */
final class EnsureAdmin
{
    public function __construct(private readonly AccessQuery $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user instanceof User || ! $user->is_admin) {
            abort(404);
        }

        if ($request->routeIs('panel.no-access')) {
            return $next($request);
        }

        $permission = PermissionCatalog::forRoute((string) $request->route()?->getName(), ! $request->isMethodSafe());
        // Telescope baraye list e read-only ham POST mizane va route haye API esm nadaran.
        if (str_starts_with((string) $request->route()?->getActionName(), 'Laravel\\Telescope\\Http\\Controllers\\')) {
            $permission = in_array($request->route()->getActionMethod(), ['index', 'show'], true)
                ? 'monitoring.view' : 'monitoring.manage';
        }
        abort_unless($this->access->allows($user, $permission), 403);

        return $next($request);
    }
}
