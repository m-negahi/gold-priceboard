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
     * Fetch — retry baraye shekast haye GOZARA ye sath e ettesal.
     *
     * `max_attempts = 1` ya'ni kamelan khamoosh.
     *
     * 🔴 Faghat khata haye ettesal ke SARI' miterkan retry mishan
     * (`cURL 35` e TLS va hamsayeh hash) — timeout (`28`) amdan birun ast
     * chun budget e kamel ro ghablan kharj karde. Lista va dalil e har
     * mored tu `Domain/Services/TransientFailure.php`.
     */
    'fetch' => [
        'max_attempts' => (int) env('PRICING_FETCH_MAX_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('PRICING_FETCH_RETRY_DELAY_MS', 300),
    ],

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

    /*
     * Saghf e ekhtelaf ba provider haye DIGAR، faghat baraye AVVALIN tick e
     * yek (provider, symbol).
     *
     * 🔴 In tanha darvaze-i-e ke rakhne ye «provider e taze ba vahed e
     * eshtebah» ro mibande: band e motlagh khata ye 10x ro nemitune begire
     * (tavarrom e rial) va check e enheraf be `previous` niaz dare ke
     * avvalin tick nadare.
     *
     * `0` ya'ni khamoosh. Dalil e adad e 20 tu `PriceSanity` ast.
     */
    'max_cross_provider_deviation' => (float) env('PRICING_MAX_CROSS_PROVIDER_DEVIATION', 20),

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
     * Store e cache baraye series e candle.
     *
     * `null` = store e default e app.
     *
     * 🔴 Chera sarih: `CACHE_STORE=database` chart ro mindaze ru POSTGRES —
     * hamun DB-i ke kol e cache baraye mohafezat azash hast. Yek series e
     * `h1` e yek-sale 8,760 candle ast؛ un ro az Postgres khundan va unja
     * zakhire kardan daghighan aks e hadaf ast. `file` ham zire Octane ba
     * chand worker bi-ma'ni-e.
     *
     * ⚠️ In store e `redis` e OMUMI-e (ba `allkeys-lru`)، na `redis-prices`.
     * Baraye cache e candle DOROST ast ke evict beshe — un yek cache e
     * vaghei-e، bar khalaf e tablo ye dagh ke manba' e khundan ast.
     */
    'cache_store' => env('PRICING_CACHE_STORE'),

    /*
     * Log e feed — «kodum provider chi dad» tu storage/logs/pricing.
     * Channel tu `config/logging.php` ta'rif mishe.
     *
     * `enabled => false` -> `NullLogger`. (Ghablan in kelid khunde
     * NEMISHOD: `PRICING_LOG=false` hich kari nemikard.)
     */
    'log' => [
        'enabled' => (bool) env('PRICING_LOG', true),
        'channel' => env('PRICING_LOG_CHANNEL', 'pricing'),
    ],

];
