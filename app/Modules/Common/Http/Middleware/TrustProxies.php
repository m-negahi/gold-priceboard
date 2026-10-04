<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Trust proxies for proper client IP and scheme detection.
 * Note: Laravel's default TrustProxies uses Symfony's static setTrustedProxies()
 * which doesn't work reliably with Octane's long-running process model.
 * Workaround: Directly modify REMOTE_ADDR and HTTPS server variables.
 *
 * @see https://github.com/laravel/framework/issues/57667
 * @see https://github.com/laravel/octane/issues/371
 */
final class TrustProxies extends Middleware
{
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    /**
     * Trusted local/private IP ranges.
     * Includes all RFC 1918 private ranges + common infrastructure IPs.
     *
     * @var array<string>
     */
    private const TRUSTED_IPS = [
        // IPv4 Loopback
        '127.0.0.0/8',

        // IPv4 Private Ranges (RFC 1918)
        '10.0.0.0/8',       // Class A - Docker Swarm, Kubernetes, AWS VPC
        '172.16.0.0/12',    // Class B - Docker default bridge (172.17-31.x.x)
        '192.168.0.0/16',   // Class C - Home networks, Docker Compose

        // IPv4 Link-Local (APIPA)
        '169.254.0.0/16',

        // IPv4 CGNAT (Carrier-grade NAT)
        '100.64.0.0/10',    // Some cloud providers use this

        // IPv6 Loopback
        '::1',

        // IPv6 Unique Local Addresses (RFC 4193) - equivalent to private IPv4
        'fc00::/7',

        // IPv6 Link-Local
        'fe80::/10',
    ];

    /**
     * Range haye IP e CDN — sabet tu hamin file (bedun e fetch/command).
     * Range haye CDN avaz shodan → hamin lista ro dasti be-rooz kon.
     *
     * @var array<string>
     */
    private const CDN_IPS = [
        '185.143.232.0/22',
        '188.229.116.16/30',
        '94.101.182.0/27',
        '2.144.3.128/28',
        '37.32.16.0/27',
        '37.32.17.0/27',
        '37.32.18.0/27',
        '37.32.19.0/27',
        '185.215.232.0/22',
        '178.131.120.48/28',
    ];

    public function __construct()
    {
        $this->proxies = array_merge(self::TRUSTED_IPS, self::CDN_IPS);
    }

    public function handle(Request $request, Closure $next): mixed
    {
        SymfonyRequest::setTrustedProxies($this->proxies(), $this->getTrustedHeaderNames());

        // Octane workaround: directly set server variables
        if ($this->isFromTrustedProxy($request)) {
            $this->overrideClientIp($request);
            $this->overrideScheme($request);
        }

        return $next($request);
    }

    private function overrideClientIp(Request $request): void
    {
        // Priority: CDN-specific headers > Standard proxy headers.
        // Har candidate-i ke khodesh yek proxy e mored e e'temad bashe rad mishe:
        // tu zanjire ye chand-laye (CDN -> Traefik -> Nginx) laye ye dakheli
        // `X-Real-IP` ro ba neshani e **khodesh** baznevise mikone (masalan
        // `10.10.3.41`). Agar hamun ro bavar konim IP e vaghei ye client gom
        // mishe — va rate limit e IP e OTP ya allowlist e API bi-dalil kharab mishe.
        $realIp = $this->firstExternalIp([
            $request->header('AR-Real-IP'),         // CDN (real-IP header)
            $request->header('CF-Connecting-IP'),   // Cloudflare
            $request->header('True-Client-IP'),     // Akamai, Cloudflare Enterprise
            $request->header('X-Real-IP'),          // Nginx
        ]) ?? $this->getForwardedClientIp($request); // X-Forwarded-For (Traefik, HAProxy, etc.)

        if ($realIp !== null) {
            $request->server->set('REMOTE_ADDR', $realIp);
        }
    }

    /**
     * Avvalin neshani ye motabar-i ke proxy e mored e e'temad e ma NIST.
     *
     * @param  array<int, string|null>  $candidates
     */
    private function firstExternalIp(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $ip = is_string($candidate) ? trim($candidate) : '';

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && ! $this->ipInRange($ip, $this->proxies())) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * IP e client az `X-Forwarded-For`.
     *
     * Ghalеb: `client, proxy1, proxy2` — az **rast** jolo miaim va har proxy e
     * mored e e'temad ro rad mikonim; avvalin neshani ye gheyr e mored e e'temad
     * hamun client ast. Chap-tarin meghdar ro kurkurane bar nemidarim chun client
     * mitune khodesh header ro ja'l kone va vorudi haye ja'li ghabl az residan e
     * request be proxy haye ma neshastan.
     */
    private function getForwardedClientIp(Request $request): ?string
    {
        $forwardedFor = $request->header('X-Forwarded-For');

        if (! is_string($forwardedFor) || trim($forwardedFor) === '') {
            return null;
        }

        $ips = array_reverse(array_map('trim', explode(',', $forwardedFor)));

        return $this->firstExternalIp($ips);
    }

    private function overrideScheme(Request $request): void
    {
        if ($request->header('X-Forwarded-Proto') === 'https') {
            $request->server->set('HTTPS', 'on');
        }
    }

    private function isFromTrustedProxy(Request $request): bool
    {
        $remoteAddr = $request->server('REMOTE_ADDR');

        // Momkene REMOTE_ADDR null bashe (CLI / task e Octane / test) — bedun e IP
        // nemishe be proxy e'temad kard; va ba strict_types pass dadan e null be
        // ipInRange TypeError mide.
        if (! is_string($remoteAddr) || $remoteAddr === '') {
            return false;
        }

        return $this->ipInRange($remoteAddr, $this->proxies());
    }

    private function ipInRange(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (! str_contains($range, '/')) {
                if ($ip === $range) {
                    return true;
                }

                continue;
            }

            [$subnet, $bits] = explode('/', $range);
            $bits = (int) $bits;

            // IPv4 CIDR
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong !== false && $subnetLong !== false) {
                $mask = -1 << (32 - $bits);
                if (($ipLong & $mask) === ($subnetLong & $mask)) {
                    return true;
                }

                continue;
            }

            // IPv6 CIDR
            $ipBin = @inet_pton($ip);
            $subnetBin = @inet_pton($subnet);

            if ($ipBin !== false && $subnetBin !== false && strlen($ipBin) === 16 && strlen($subnetBin) === 16) {
                $ipHex = bin2hex($ipBin);
                $subnetHex = bin2hex($subnetBin);
                // Compare the first $bits bits
                $fullBytes = intdiv($bits, 4);
                if (substr($ipHex, 0, $fullBytes) === substr($subnetHex, 0, $fullBytes)) {
                    $remainingBits = $bits % 4;
                    if ($remainingBits === 0) {
                        return true;
                    }
                    $ipNibble = intval($ipHex[$fullBytes], 16);
                    $subnetNibble = intval($subnetHex[$fullBytes], 16);
                    $mask = (0xF << (4 - $remainingBits)) & 0xF;
                    if (($ipNibble & $mask) === ($subnetNibble & $mask)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
