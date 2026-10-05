<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * IP e VAGHEI ye client + scheme، az posht e proxy/CDN.
 *
 * ── 🔴 Chera in middleware HAYATI ast ────────────────────────────────
 * Bedun e un، `$request->ip()` HAMISHE neshani e proxy ast — ya'ni HAME ye
 * karbar ha az yek IP miad. Un vaght **rate limit e IP e OTP kamelan
 * bi-ma'ni mishe**: ya hame ba ham ban mishan، ya hichkas. Hamin tor
 * allowlist e API tu Phase 4.
 *
 * ── Chera `extends Middleware` kafi NIST (Octane) ────────────────────
 * `TrustProxies` e Laravel az `SymfonyRequest::setTrustedProxies()` e STATIC
 * estefade mikone. Ba process e long-running e Octane un halat beyn e
 * request ha mimune va ghabl e etminan nist. Pas `REMOTE_ADDR` va `HTTPS`
 * ro MOSTAGHIM ru hamin request set mikonim.
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
     * Range haye dakheli/khosusi ye mored e e'temad.
     *
     * Hame ye range haye RFC 1918 + IP haye ma'mul e infrastructure (Docker،
     * Kubernetes، VPC).
     *
     * @var array<int, string>
     */
    private const TRUSTED_IPS = [
        // IPv4 loopback
        '127.0.0.0/8',

        // IPv4 khosusi (RFC 1918)
        '10.0.0.0/8',       // Docker Swarm، Kubernetes، AWS VPC
        '172.16.0.0/12',    // bridge e default e Docker (172.17-31.x.x)
        '192.168.0.0/16',   // shabake ye khunegi، Docker Compose

        // IPv4 link-local (APIPA)
        '169.254.0.0/16',

        // IPv4 CGNAT — ba'zi cloud ha azash estefade mikonan
        '100.64.0.0/10',

        // IPv6 loopback
        '::1',

        // IPv6 unique-local (RFC 4193) — mo'adel e khosusi ye IPv4
        'fc00::/7',

        // IPv6 link-local
        'fe80::/10',
    ];

    /**
     * Range haye IP e CDN — sabet tu hamin file (bedun e fetch/command).
     * Range haye CDN avaz shodan → hamin lista ro dasti be-rooz kon.
     *
     * @var array<int, string>
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
        // Hanuz seda zade mishe ta `$request->getClientIp()` e Symfony ham
        // kar kone — vali be TANHA-i ghabl e etminan nist (bebin docblock e
        // bala)، pas paeen server var ha ro mostaghim set mikonim.
        SymfonyRequest::setTrustedProxies($this->proxies(), $this->getTrustedHeaderNames());

        if ($this->isFromTrustedProxy($request)) {
            $this->overrideClientIp($request);
            $this->overrideScheme($request);
        }

        return $next($request);
    }

    private function overrideClientIp(Request $request): void
    {
        // Olaviat: header haye makhsus e CDN > header haye ma'mul e proxy.
        // Har candidate-i ke khodesh yek proxy e mored e e'temad bashe rad mishe:
        // tu zanjire ye chand-laye (CDN -> Traefik -> Nginx) laye ye dakheli
        // `X-Real-IP` ro ba neshani e **khodesh** baznevise mikone (masalan
        // `10.10.3.41`). Agar hamun ro bavar konim IP e vaghei ye client gom
        // mishe — va rate limit e IP e OTP ya allowlist e API bi-dalil kharab mishe.
        $realIp = $this->firstExternalIp([
            $request->header('AR-Real-IP'),         // CDN e Irani (ArvanCloud)
            $request->header('CF-Connecting-IP'),   // Cloudflare
            $request->header('True-Client-IP'),     // Akamai، Cloudflare Enterprise
            $request->header('X-Real-IP'),          // Nginx
        ]) ?? $this->getForwardedClientIp($request); // X-Forwarded-For (Traefik، HAProxy، …)

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

    /** @param array<int, string> $ranges */
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

                // ⚠️ Ru NIBBLE (yek raghame hex = 4 bit) moghayese mishe، na
                // byte. Esm e `fullBytes` ghalat bud va gij mikard.
                $fullNibbles = intdiv($bits, 4);

                if (substr($ipHex, 0, $fullNibbles) !== substr($subnetHex, 0, $fullNibbles)) {
                    continue;
                }

                $remainingBits = $bits % 4;

                if ($remainingBits === 0) {
                    return true;
                }

                $mask = (0xF << (4 - $remainingBits)) & 0xF;

                if ((intval($ipHex[$fullNibbles], 16) & $mask)
                    === (intval($subnetHex[$fullNibbles], 16) & $mask)) {
                    return true;
                }
            }
        }

        return false;
    }
}
