<?php

declare(strict_types=1);

namespace App\Modules\Panel\Http\Middleware;

use App\Modules\Common\Helpers\Env;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `local-only`: route-hayi ke faghat baraye developer-and (kit-e primitive ha). Kharej az
 * local/testing 404 mide, na 403 — vojoodeshun ham nabayad ma'loom bashe. Per-request check
 * mishe, na sar-e sabt-e route, ta yek test betoone environment ro avaz kone va 404 ro bebine.
 */
final readonly class EnsureLocalEnvironment
{
    public function __construct(private Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Env::isDev($this->app), 404);

        return $next($request);
    }
}
