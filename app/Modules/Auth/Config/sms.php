<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
| driver ha:
|   kavenegar -> ersal e vaghei (fe'lan tanha driver e vaghei؛ fallback ba'dan)
|   log       -> dev: code tu log e laravel chap mishe
|   fake      -> test/demo: hich IO، tu hafeze sabt mishe
*/

return [

    'driver' => env('SMS_DRIVER', 'log'),

    'timeout' => (int) env('SMS_TIMEOUT', 10),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY', ''),
        'sender' => env('KAVENEGAR_SENDER', ''),
        // Esm e template e OTP tu panel e Kavenegar (verify/lookup).
        'template' => env('KAVENEGAR_TEMPLATE_OTP', 'verify'),
        'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
    ],

];
