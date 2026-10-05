<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Pricing — config e module
|--------------------------------------------------------------------------
| Ba `mergeConfigFrom` tu `PricingServiceProvider` load mishe; publish
| nemishe tu `config/`.
*/

return [

    /*
     * Grid e tick. HAME ye timestamp ha ru hamin floor mishan — paye ye
     * idempotency e Influx. Avaz kardanesh ya'ni tarikhche ye ghabli ba
     * tarikhche ye jadid hamtaraz nist.
     */
    'tick_seconds' => (int) env('PRICING_TICK_SECONDS', 15),

    'timeout' => (int) env('PRICING_TIMEOUT', 10),

    /*
     * Offset e Tehran baraye window e candle.
     *
     * 🔴 Bedun e in, `window(every: 1d)` be nime-shab e UTC align mishe ke
     * 03:30 e Tehran ast — va har candle e ruzane DO rooz e kari ye Irani ro
     * sovar mishe. `timezone.fixed` (na `location`) chun Iran az 2022 DST
     * nadare va offset e sabet vabastegi be tzdata ro hazf mikone.
     */
    'tz_offset' => env('PRICING_TZ_OFFSET', '3h30m'),

    /*
     * Saghf e enheraf e tick-be-tick (darsad).
     *
     * In mohafez e ASLI ye runtime zed e khata ye 10x ast — band e motlagh e
     * `Instrument::sanityBand()` un ro NEMIGIRE (tozih tu hamun method).
     */
    'max_deviation' => [
        'IRR' => (float) env('PRICING_MAX_DEVIATION_IRR', 10),
        'USD' => (float) env('PRICING_MAX_DEVIATION_USD', 5),
    ],

    'influx' => [
        'url' => env('PRICING_INFLUX_URL', 'http://influxdb:8086'),
        // Takrar e amdi az `tz_offset` e bala — `rebuildMinuteCandles` faghat
        // config e `influx` ro migire va nabayad majbur bashe be riste ye
        // config e bala sar bezane.
        'tz_offset' => env('PRICING_TZ_OFFSET', '3h30m'),
        'org' => env('PRICING_INFLUX_ORG', 'priceboard'),

        // Do token e joda: worker faghat mineviseh, web faghat mikhune.
        'write_token' => env('PRICING_INFLUX_WRITE_TOKEN', ''),
        'read_token' => env('PRICING_INFLUX_READ_TOKEN', ''),

        'query_timeout' => (int) env('PRICING_INFLUX_QUERY_TIMEOUT', 5),
        'write_timeout' => (int) env('PRICING_INFLUX_WRITE_TIMEOUT', 2),

        'buckets' => [
            'ticks' => 'ticks',
            'm1' => 'candles_1m',
            'm5' => 'candles_5m',
            'm15' => 'candles_15m',
            'h1' => 'candles_1h',
            'h4' => 'candles_4h',
            'd1' => 'candles_1d',
        ],

        // Saghf e spool: ~40k khat ≈ 2 saat tick.
        'spool_max_lines' => (int) env('PRICING_INFLUX_SPOOL_MAX', 40_000),
    ],

    /*
     * Log e feed — «kodum provider chi dad» tu storage/logs/pricing.
     * Channel tu `config/logging.php` ta'rif mishe.
     */
    'log' => [
        'enabled' => (bool) env('PRICING_LOG', true),
        'channel' => env('PRICING_LOG_CHANNEL', 'pricing'),
    ],

];
