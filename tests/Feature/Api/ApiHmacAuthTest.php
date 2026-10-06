<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Application\Actions\ApiClientAction;
use App\Modules\Api\Domain\Services\HmacSignature;
use App\Modules\Api\Infrastructure\Persistence\Models\ApiClient;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Auth e HMAC (P4-04/P4-08) az rooye HTTP.
 *
 * `/api/v1/instruments` MAJANI-e، pas in test ha faghat AUTH ro
 * misanjan — bedun e dast zadan be wallet.
 */
final class ApiHmacAuthTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $made = (new ApiClientAction)->create(User::factory()->create()->id, 'test');
        $this->client = $made['client'];
        $this->secret = $made['secret'];
    }

    /** @param array<string, string> $overrides */
    private function signedGet(string $uri, array $overrides = []): TestResponse
    {
        $timestamp = $overrides['timestamp'] ?? (string) time();
        $nonce = $overrides['nonce'] ?? bin2hex(random_bytes(8));
        $secret = $overrides['secret'] ?? $this->secret;
        $clientId = $overrides['client_id'] ?? $this->client->client_id;

        $signature = $overrides['signature']
            ?? HmacSignature::sign($secret, HmacSignature::canonical('GET', $uri, $timestamp, $nonce, ''));

        // ⚠️ `getJson` body ye `[]` mifreste (json_encode e array e khali) va
        // emza ye body ye khali ro mishkune — `get` e bi-body lazem e.
        return $this->get($uri, [
            'Accept' => 'application/json',
            'X-Pb-Client' => $clientId,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => $signature,
        ]);
    }

    public function test_a_correctly_signed_request_passes(): void
    {
        $this->signedGet('/api/v1/instruments')
            ->assertOk()
            ->assertJsonPath('data.0.symbol', 'xau-18k-g-irr');
    }

    public function test_missing_headers_are_rejected(): void
    {
        $this->getJson('/api/v1/instruments')->assertUnauthorized();
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        $this->signedGet('/api/v1/instruments', ['signature' => str_repeat('a', 64)])
            ->assertUnauthorized();
    }

    /** 🔴 Enumeration: client e nashenas DAGHIGHAN hamun javab e emza ye ghalat. */
    public function test_an_unknown_client_gets_the_same_answer_as_a_bad_signature(): void
    {
        $unknown = $this->signedGet('/api/v1/instruments', ['client_id' => 'pb_nist']);
        $badSig = $this->signedGet('/api/v1/instruments', ['signature' => str_repeat('b', 64)]);

        $unknown->assertUnauthorized();
        self::assertSame($badSig->json(), $unknown->json(), 'payam ha bayad gheyr e ghabel e tafkik bashan.');
    }

    public function test_a_stale_timestamp_is_rejected(): void
    {
        $this->signedGet('/api/v1/instruments', ['timestamp' => (string) (time() - 31)])
            ->assertUnauthorized();
    }

    /** 🔴 Zed e replay: hamun request e emza-shode bar e dovvom rad mishe. */
    public function test_a_replayed_nonce_is_rejected(): void
    {
        $timestamp = (string) time();
        $nonce = 'replay-nonce-1';

        $this->signedGet('/api/v1/instruments', ['timestamp' => $timestamp, 'nonce' => $nonce])->assertOk();
        $this->signedGet('/api/v1/instruments', ['timestamp' => $timestamp, 'nonce' => $nonce])->assertUnauthorized();
    }

    public function test_an_inactive_client_is_rejected(): void
    {
        (new ApiClientAction)->deactivate($this->client);

        $this->signedGet('/api/v1/instruments')->assertUnauthorized();
    }

    public function test_the_signature_covers_the_query_string(): void
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(8));
        // Emza baraye yek URI ye DIGE sakhte shode:
        $signature = HmacSignature::sign(
            $this->secret,
            HmacSignature::canonical('GET', '/api/v1/instruments?x=1', $timestamp, $nonce, ''),
        );

        $this->get('/api/v1/instruments?x=2', [
            'Accept' => 'application/json',
            'X-Pb-Client' => $this->client->client_id,
            'X-Pb-Timestamp' => $timestamp,
            'X-Pb-Nonce' => $nonce,
            'X-Pb-Signature' => $signature,
        ])->assertUnauthorized();
    }
}
