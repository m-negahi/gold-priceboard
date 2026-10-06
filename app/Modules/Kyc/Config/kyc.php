<?php

declare(strict_types=1);

return [
    'token' => env('ZOHAL_TOKEN', ''),
    'base_url' => env('ZOHAL_BASE_URL', 'https://sandbox.zohal.io/api/v0'),
    'timeout' => 12,
];
