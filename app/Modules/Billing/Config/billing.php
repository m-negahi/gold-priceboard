<?php

declare(strict_types=1);

return [
    'zibal' => [
        'merchant' => env('ZIBAL_MERCHANT', ''),
    ],

    /*
     * Moshakhasat e FOROSHANDE ru factor e rasmi (panel e mali → چاپ).
     * Khali = ru factor chap nemishe. `vat_percent` faghat NAMAYESH e sahm e
     * VAT az mablagh e sabt-shode ast (mablagh shamel e VAT farz mishe)؛
     * hich mablaghi ro taghir nemide.
     */
    'seller' => [
        'name' => env('BILLING_SELLER_NAME', ''),
        'economic_code' => env('BILLING_SELLER_ECONOMIC_CODE', ''),
        'national_id' => env('BILLING_SELLER_NATIONAL_ID', ''),
        'registration_number' => env('BILLING_SELLER_REGISTRATION_NUMBER', ''),
        'address' => env('BILLING_SELLER_ADDRESS', ''),
        'postal_code' => env('BILLING_SELLER_POSTAL_CODE', ''),
        'phone' => env('BILLING_SELLER_PHONE', ''),
        'vat_percent' => (int) env('BILLING_VAT_PERCENT', 0),
    ],
];
