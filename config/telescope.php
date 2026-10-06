<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Common\Enums\QueueName;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    'enabled' => env('TELESCOPE_ENABLED', env('APP_ENV') === 'local'),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => 'telescope',
    'driver' => 'database',
    'storage' => ['database' => ['connection' => env('DB_CONNECTION', 'pgsql'), 'chunk' => 1000]],
    'queue' => ['connection' => null, 'queue' => QueueName::Default->value, 'delay' => 10],
    'middleware' => ['web', 'auth', EnsureAdmin::class, Authorize::class],
    'only_paths' => [],
    // Payload e hoviat va credential tu debug zakhire nemishe.
    'ignore_paths' => ['livewire*', '_boost*', 'login*', 'onboarding*', 'account/*', 'dashboard/tickets*', 'dashboard/users*'],
    'ignore_commands' => ['boost:*'],
    'watchers' => [
        Watchers\BatchWatcher::class => true,
        Watchers\CacheWatcher::class => false,
        Watchers\ClientRequestWatcher::class => false,
        Watchers\CommandWatcher::class => ['enabled' => true, 'ignore' => []],
        Watchers\DumpWatcher::class => false,
        Watchers\EventWatcher::class => false,
        Watchers\ExceptionWatcher::class => true,
        Watchers\GateWatcher::class => false,
        Watchers\JobWatcher::class => true,
        Watchers\LogWatcher::class => ['enabled' => true, 'level' => 'error'],
        Watchers\MailWatcher::class => false,
        Watchers\ModelWatcher::class => false,
        Watchers\NotificationWatcher::class => false,
        // Query/Redis/client payload momkene secret ya madarek dashte bashe.
        Watchers\QueryWatcher::class => false,
        Watchers\RedisWatcher::class => false,
        Watchers\RequestWatcher::class => ['enabled' => true, 'size_limit' => 64, 'ignore_http_methods' => [], 'ignore_status_codes' => []],
        Watchers\ScheduleWatcher::class => true,
        Watchers\ViewWatcher::class => false,
    ],
];
