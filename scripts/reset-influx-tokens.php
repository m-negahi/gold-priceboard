<?php

declare(strict_types=1);

/*
 * Hazf e token haye `priceboard-*` ta `pricing:influx:provision --tokens`
 * dobare besazatesh.
 *
 * `provision` amdan token e mojud ro dast nemizane (nabayad token e zende ye
 * production ro saket avaz kone), pas baraye ja be ja kardan e ejaze ha avval
 * bayad pak shan.
 *
 * Ejra: docker compose exec octane php scripts/reset-influx-tokens.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

$url = rtrim((string) config('pricing.influx.url'), '/');
$org = (string) config('pricing.influx.org');
$admin = (string) env('INFLUX_ADMIN_TOKEN');

$client = Http::withToken($admin, 'Token')->timeout(10)->acceptJson()->baseUrl($url);

$response = $client->get('/api/v2/authorizations', ['org' => $org]);

foreach ((array) $response->json('authorizations', []) as $auth) {
    $description = (string) ($auth['description'] ?? '');

    if (! str_starts_with($description, 'priceboard-')) {
        continue;
    }

    $client->delete('/api/v2/authorizations/'.$auth['id']);
    echo "hazf shod: {$description}\n";
}

echo "tamum.\n";
