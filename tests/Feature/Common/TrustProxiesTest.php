<?php

declare(strict_types=1);

namespace Tests\Feature\Common;

use App\Modules\Common\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 🔴 In test AMNIATI-e، na raftari.
 *
 * Agar `TrustProxies` eshtebah kone، yek attacker ba set kardan e yek
 * header (`X-Forwarded-For: 1.2.3.4`) mitune **rate limit e IP e OTP ro dor
 * bezane** — faghat ba avaz kardan e un header baraye har talash. Un vaght
 * mohafez e phone tanha chizi mimune ke bein e yek bot va 100,000 SMS ast.
 *
 * Aks esh ham bad ast: agar IP ro NAGIRIM، hame ye karbar ha ru YEK bucket
 * mioftan va yek nafar mitune hame ro ban kone.
 */
final class TrustProxiesTest extends TestCase
{
    /** @param array<string, string> $headers */
    private function clientIp(string $remoteAddr, array $headers = []): string
    {
        $server = ['REMOTE_ADDR' => $remoteAddr];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = Request::create('/', 'GET', [], [], [], $server);

        (new TrustProxies)->handle($request, static fn (): Response => new Response);

        return (string) $request->server->get('REMOTE_ADDR');
    }

    // ── Masir e salem ───────────────────────────────────────────────

    public function test_a_real_client_ip_comes_through_x_forwarded_for(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('10.0.0.5', [
            'X-Forwarded-For' => '5.6.7.8',
        ]));
    }

    public function test_the_cdn_header_wins_over_x_forwarded_for(): void
    {
        // Olaviat: header haye makhsus e CDN > header haye ma'mul e proxy.
        self::assertSame('5.6.7.8', $this->clientIp('185.143.232.10', [
            'AR-Real-IP' => '5.6.7.8',
            'X-Forwarded-For' => '9.9.9.9',
        ]));
    }

    public function test_the_scheme_is_taken_from_x_forwarded_proto(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies)->handle($request, static fn (): Response => new Response);

        self::assertSame('on', $request->server->get('HTTPS'));
    }

    // ── 🔴 Zanjire ye chand-laye ────────────────────────────────────

    /**
     * Ghaleb e `X-Forwarded-For`: `client, proxy1, proxy2`.
     *
     * Az RAST jolo miaim va har proxy e mored e e'temad ro rad mikonim.
     * Chap-tarin meghdar ro KURKURANE bar nemidarim — bebin test e ja'l.
     */
    public function test_trusted_hops_are_skipped_from_the_right(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('10.0.0.5', [
            'X-Forwarded-For' => '5.6.7.8, 10.0.0.7, 172.17.0.3',
        ]));
    }

    /**
     * 🔴 Laye ye dakheli `X-Real-IP` ro ba neshani e KHODESH baznevise
     * mikone.
     *
     * Tu zanjire ye CDN -> Traefik -> Nginx، `X-Real-IP` mitune
     * `10.10.3.41` bashe. Agar un ro bavar konim، IP e vaghei gom mishe va
     * rate limit e IP bi-ma'ni mishe.
     */
    public function test_a_private_ip_in_a_header_is_not_mistaken_for_the_client(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('10.0.0.5', [
            'X-Real-IP' => '10.10.3.41',
            'X-Forwarded-For' => '5.6.7.8, 10.10.3.41',
        ]));
    }

    // ── 🔴🔴 Zed e ja'l ─────────────────────────────────────────────

    /**
     * 🔴 Request e MOSTAGHIM (na az posht e proxy) — header haye forwarded
     * bayad kamelan NADIDE gerefte shan.
     *
     * In mohem tarin test e in file ast: bedun e un، har kasi mitune ba yek
     * header rate limit e IP ro dor bezane.
     */
    public function test_forwarded_headers_from_an_untrusted_source_are_ignored(): void
    {
        self::assertSame('203.0.113.9', $this->clientIp('203.0.113.9', [
            'X-Forwarded-For' => '1.2.3.4',
            'AR-Real-IP' => '1.2.3.4',
            'CF-Connecting-IP' => '1.2.3.4',
            'X-Real-IP' => '1.2.3.4',
        ]));
    }

    public function test_an_untrusted_source_cannot_fake_https(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies)->handle($request, static fn (): Response => new Response);

        self::assertNull($request->server->get('HTTPS'));
    }

    /**
     * Header e ja'li ke client GHABL az residan be proxy haye ma gozashte.
     *
     * Un vorudi ha SAMT E CHAP mishinan، pas «avvalin az chap» kamelan
     * ghabl e ja'l ast. Chun az RAST mikhunim، avvalin gheyr-e-e'temad
     * hamun chizi-e ke proxy e ma DIDE.
     */
    public function test_a_prepended_fake_entry_does_not_win(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('10.0.0.5', [
            'X-Forwarded-For' => '1.2.3.4, 5.6.7.8, 10.0.0.7',
        ]));
    }

    public function test_garbage_in_a_header_is_ignored(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('10.0.0.5', [
            'X-Real-IP' => 'not-an-ip',
            'X-Forwarded-For' => '5.6.7.8',
        ]));
    }

    public function test_an_all_private_chain_leaves_remote_addr_alone(): void
    {
        // Hich IP e khareji-i tu zanjire nist (masalan monitoring e dakheli).
        // Nabayad chizi ro hads bezanim.
        self::assertSame('10.0.0.5', $this->clientIp('10.0.0.5', [
            'X-Forwarded-For' => '10.0.0.7, 172.17.0.3',
        ]));
    }

    // ── CIDR ────────────────────────────────────────────────────────

    public function test_the_cdn_ranges_are_trusted(): void
    {
        // `185.143.232.0/22` -> `185.143.234.x` dakhel e range ast.
        self::assertSame('5.6.7.8', $this->clientIp('185.143.234.1', [
            'X-Forwarded-For' => '5.6.7.8',
        ]));
    }

    public function test_an_ip_just_outside_a_cidr_is_not_trusted(): void
    {
        // `188.229.116.16/30` -> faghat .16 ta .19. Pas .20 birun ast.
        self::assertSame('188.229.116.20', $this->clientIp('188.229.116.20', [
            'X-Forwarded-For' => '1.2.3.4',
        ]));
    }

    public function test_ipv6_loopback_is_trusted(): void
    {
        self::assertSame('5.6.7.8', $this->clientIp('::1', [
            'X-Forwarded-For' => '5.6.7.8',
        ]));
    }

    public function test_ipv6_unique_local_is_trusted(): void
    {
        // `fc00::/7` — moghayese ru NIBBLE anjam mishe (7 bit = 1 nibble + 3 bit).
        self::assertSame('5.6.7.8', $this->clientIp('fd12:3456::1', [
            'X-Forwarded-For' => '5.6.7.8',
        ]));
    }

    public function test_a_public_ipv6_is_not_trusted(): void
    {
        self::assertSame('2001:db8::1', $this->clientIp('2001:db8::1', [
            'X-Forwarded-For' => '1.2.3.4',
        ]));
    }

    /** CLI / task e Octane — `REMOTE_ADDR` nadare va nabayad TypeError bede. */
    public function test_a_missing_remote_addr_does_not_blow_up(): void
    {
        $request = Request::create('/', 'GET');
        $request->server->remove('REMOTE_ADDR');

        $response = (new TrustProxies)->handle($request, static fn (): Response => new Response);

        self::assertSame(200, $response->getStatusCode());
    }
}
