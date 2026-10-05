<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\TimeSeries;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Amaliat e modiriati ye Influx — bucket, task va token.
 *
 * Jodā az `InfluxTimeSeries` ast chun:
 *   - token e ADMIN mikhahe (na read/write e mahdud)
 *   - faghat tu CLI seda mishe (`pricing:influx:*`), hargez tu request path
 *
 * Hame ye method ha **idempotent**-and: chizi ke hast update mishe, chizi ke
 * nist sakhte mishe. Pas `provision` ro mishe har chand bar zad.
 */
final readonly class InfluxAdmin
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config) {}

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(3)->get($this->url().'/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function orgId(): string
    {
        $response = $this->client()->get('/api/v2/orgs', ['org' => $this->org()]);

        $this->assertOk($response->status(), $response->body(), 'khundan e org');

        $id = $response->json('orgs.0.id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException("influx: org e «{$this->org()}» peyda nashod.");
        }

        return $id;
    }

    /**
     * Lista ye bucket haye mojud: `name => ['id' => …, 'retention' => saniye]`.
     *
     * @return array<string, array{id: string, retention: int}>
     */
    public function buckets(): array
    {
        $response = $this->client()->get('/api/v2/buckets', [
            'org' => $this->org(),
            'limit' => 100,
        ]);

        $this->assertOk($response->status(), $response->body(), 'khundan e bucket ha');

        $buckets = [];

        foreach ((array) $response->json('buckets', []) as $bucket) {
            $name = (string) ($bucket['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $buckets[$name] = [
                'id' => (string) ($bucket['id'] ?? ''),
                'retention' => (int) ($bucket['retentionRules'][0]['everySeconds'] ?? 0),
            ];
        }

        return $buckets;
    }

    /**
     * Sakhtan ya tanzim e yek bucket.
     *
     * `$retentionSeconds === 0` ya'ni bi-nahayat (baraye `candles_4h`/`_1d`).
     *
     * @return string chi shod: `created` | `updated` | `unchanged`
     */
    public function ensureBucket(string $name, int $retentionSeconds, string $orgId): string
    {
        $existing = $this->buckets()[$name] ?? null;

        $rules = $retentionSeconds > 0
            ? [['type' => 'expire', 'everySeconds' => $retentionSeconds]]
            : [];

        if ($existing === null) {
            $response = $this->client()->post('/api/v2/buckets', [
                'orgID' => $orgId,
                'name' => $name,
                'retentionRules' => $rules,
            ]);

            $this->assertOk($response->status(), $response->body(), "sakhtan e bucket e «{$name}»");

            return 'created';
        }

        if ($existing['retention'] === $retentionSeconds) {
            return 'unchanged';
        }

        // Retention darift karde — eslah mishe. Bedun e in, yek taghir e
        // config saket bi-asar mimune.
        $response = $this->client()->patch("/api/v2/buckets/{$existing['id']}", [
            'retentionRules' => $rules,
        ]);

        $this->assertOk($response->status(), $response->body(), "be-rooz kardan e bucket e «{$name}»");

        return 'updated';
    }

    /**
     * Lista ye task ha: `name => ['id' => …, 'flux' => …, 'status' => …]`.
     *
     * @return array<string, array{id: string, flux: string, status: string}>
     */
    public function tasks(): array
    {
        $response = $this->client()->get('/api/v2/tasks', [
            'org' => $this->org(),
            'limit' => 100,
        ]);

        $this->assertOk($response->status(), $response->body(), 'khundan e task ha');

        $tasks = [];

        foreach ((array) $response->json('tasks', []) as $task) {
            $name = (string) ($task['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $tasks[$name] = [
                'id' => (string) ($task['id'] ?? ''),
                'flux' => (string) ($task['flux'] ?? ''),
                'status' => (string) ($task['status'] ?? 'active'),
            ];
        }

        return $tasks;
    }

    /**
     * Sakhtan ya be-rooz kardan e yek task.
     *
     * Manba' e haghighat file haye `resources/influx/tasks/*.flux`-and —
     * na chizi ke tu UI click shode. Agar flux e zakhire-shode ba file fargh
     * dashte bashe، file barande ast.
     *
     * @return string `created` | `updated` | `unchanged`
     */
    public function ensureTask(string $name, string $flux, string $orgId): string
    {
        $existing = $this->tasks()[$name] ?? null;

        if ($existing === null) {
            $response = $this->client()->post('/api/v2/tasks', [
                'orgID' => $orgId,
                'flux' => $flux,
                'status' => 'active',
            ]);

            $this->assertOk($response->status(), $response->body(), "sakhtan e task e «{$name}»");

            return 'created';
        }

        if ($this->normalise($existing['flux']) === $this->normalise($flux) && $existing['status'] === 'active') {
            return 'unchanged';
        }

        $response = $this->client()->patch("/api/v2/tasks/{$existing['id']}", [
            'flux' => $flux,
            'status' => 'active',
        ]);

        $this->assertOk($response->status(), $response->body(), "be-rooz kardan e task e «{$name}»");

        return 'updated';
    }

    /**
     * Akharin ejra haye yek task.
     *
     * @return array<int, array{status: string, startedAt: ?string, error: ?string}>
     */
    public function taskRuns(string $taskId, int $limit = 5): array
    {
        $response = $this->client()->get("/api/v2/tasks/{$taskId}/runs", ['limit' => $limit]);

        if (! $response->successful()) {
            return [];
        }

        return array_map(
            static fn (array $run): array => [
                'status' => (string) ($run['status'] ?? 'unknown'),
                'startedAt' => $run['startedAt'] ?? null,
                'error' => $run['error'] ?? null,
            ],
            (array) $response->json('runs', []),
        );
    }

    /**
     * Sakhtan e yek token ba dastresi ye mahdud.
     *
     * Do token darim: `write` faghat ru `ticks` mineviseh، `read` faghat
     * mikhune. Agar token e web lo bere، kasi natune tarikhche ja'l kone.
     *
     * @param  array<int, string>  $readBuckets
     * @param  array<int, string>  $writeBuckets
     */
    public function createToken(
        string $description,
        string $orgId,
        array $readBuckets,
        array $writeBuckets,
    ): string {
        $bucketIds = $this->buckets();

        // ⚠️ Dastresi ye khundan e khod e ORG ejbari-e.
        // Bedun esh، har query-i ke ba in token ejra beshe ba
        // `404 could not find org "…"` rad mishe — chun Flux bayad betune
        // esm e org ro be id resolve kone. Faghat `buckets` kafi nist.
        $permissions = [
            ['action' => 'read', 'resource' => ['type' => 'orgs', 'id' => $orgId]],
        ];

        foreach (['read' => $readBuckets, 'write' => $writeBuckets] as $action => $names) {
            foreach ($names as $name) {
                $id = $bucketIds[$name]['id'] ?? null;

                if ($id === null) {
                    throw new RuntimeException("influx: bucket e «{$name}» baraye token peyda nashod.");
                }

                $permissions[] = [
                    'action' => $action,
                    'resource' => ['type' => 'buckets', 'id' => $id, 'orgID' => $orgId],
                ];
            }
        }

        $response = $this->client()->post('/api/v2/authorizations', [
            'orgID' => $orgId,
            'description' => $description,
            'permissions' => $permissions,
        ]);

        $this->assertOk($response->status(), $response->body(), 'sakhtan e token');

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('influx: token sakhte nashod.');
        }

        return $token;
    }

    /** Token-i ba hamin tozih ghablan sakhte shode? */
    public function tokenExists(string $description): bool
    {
        $response = $this->client()->get('/api/v2/authorizations', ['org' => $this->org()]);

        if (! $response->successful()) {
            return false;
        }

        foreach ((array) $response->json('authorizations', []) as $auth) {
            if (($auth['description'] ?? null) === $description) {
                return true;
            }
        }

        return false;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->adminToken(), 'Token')
            ->timeout(10)
            ->acceptJson()
            ->baseUrl($this->url());
    }

    /**
     * Moghayese ye flux bedun e tafavot haye bi-ma'ni (faseleh/khat e khali).
     * Bedun e in، har `provision` hame ye task ha ro «updated» neshun mide.
     */
    private function normalise(string $flux): string
    {
        return preg_replace('/\s+/', ' ', trim($flux)) ?? $flux;
    }

    private function assertOk(int $status, string $body, string $what): void
    {
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("influx: {$what} shekast khord ({$status}): {$body}");
        }
    }

    private function url(): string
    {
        return rtrim((string) ($this->config['url'] ?? 'http://influxdb:8086'), '/');
    }

    private function org(): string
    {
        return (string) ($this->config['org'] ?? 'priceboard');
    }

    private function adminToken(): string
    {
        $token = (string) ($this->config['admin_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('influx: `INFLUX_ADMIN_TOKEN` tanzim nashode.');
        }

        return $token;
    }
}
