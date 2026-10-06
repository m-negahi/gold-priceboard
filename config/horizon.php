<?php

declare(strict_types=1);

use App\Modules\Auth\Http\Middleware\EnsureAdmin;
use App\Modules\Common\Enums\QueueName;

return [
    'name' => env('HORIZON_NAME', 'PriceBoard'),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'priceboard_horizon:'),
    'middleware' => ['web', 'auth', EnsureAdmin::class],
    'waits' => array_fill_keys(array_map(fn (string $queue): string => 'redis:'.$queue, QueueName::values()), 60),
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080],
    'silenced' => [],
    'silenced_tags' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => false,
    'memory_limit' => 128,
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => QueueName::values(),
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => ['supervisor-1' => ['maxProcesses' => 6, 'balanceMaxShift' => 1, 'balanceCooldown' => 3]],
        'local' => ['supervisor-1' => ['maxProcesses' => 3]],
    ],
];
