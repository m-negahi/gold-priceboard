<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http\Middleware;

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
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user instanceof User || ! $user->is_admin) {
            abort(404);
        }

        return $next($request);
    }
}
