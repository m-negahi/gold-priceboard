<?php

declare(strict_types=1);

namespace App\Modules\Media\Providers;

use App\Modules\Common\Providers\ModuleServiceProvider;

/** Ketabkhane-ye rasane: upload, moshtaghat, alt, «koja estefade shode». */
final class MediaServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Media';
    }
}
