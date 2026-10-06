<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
| driver ha:
|   kavenegar -> ersal e vaghei (fe'lan tanha driver e vaghei؛ fallback ba'dan)
|   fake      -> dev/test/demo: hich IO، tu hafeze sabt mishe + log mizane
|
| (`log` e joda HAZF shod — `fake` khodesh log mizane. 2026-10-05)
|
| 🔴 Default `kavenegar` ast، na `fake`: yek deploy e production ke
| `SMS_DRIVER` ro faramush karde bayad BOLAND beterke (missingApiKey)،
| na inke saket code ha ro tu log berize va hich SMS-i nare.
*/

return [

    'driver' => env('SMS_DRIVER', 'kavenegar'),

    'timeout' => (int) env('SMS_TIMEOUT', 10),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY', ''),
        'sender' => env('KAVENEGAR_SENDER', ''),
        // Esm e template e OTP tu panel e Kavenegar (verify/lookup).
        'template' => env('KAVENEGAR_TEMPLATE_OTP', 'verify'),
        'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
    ],

];
