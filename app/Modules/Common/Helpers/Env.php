<?php

declare(strict_types=1);

namespace App\Modules\Common\Helpers;

use Illuminate\Contracts\Foundation\Application;

/**
 * Yek ta'rif baraye «development»: `local` ya `testing`. Har guard-e local-only (bypass-e OTP,
 * header-haye debug-e captcha, command-haye flush/seed, middleware-e `local-only`) az hamin
 * mikhoone ta yek environment-e jadid (staging) hame ja yek-jur raftar kone. Application ra
 * seda-zanande mide (constructor injection), na service locator.
 */
final class Env
{
    public static function isDev(Application $app): bool
    {
        return (bool) $app->environment(['local', 'testing']);
    }
}
