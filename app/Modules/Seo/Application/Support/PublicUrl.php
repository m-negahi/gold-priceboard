<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Support;

/**
 * URL e motlagh e omumi baraye SEO (canonical، og:url، JSON-LD، sitemap،
 * `Sitemap:` e robots.txt).
 *
 * 🔴 Root az `app.url` (config) miad، NA az header e `Host` e request: `route()`
 * e pish-farz host ro az request migire va hich `TrustHosts`/`forceRootUrl`
 * nadarim — `curl -H 'Host: evil.example'` canonical va sitemap ro be domain e
 * attacker mibord va `Cache-Control: public` un ro tu CDN zakhire mikard.
 * `forceRootUrl` e global amdan estefade NASHOD: tu Octane UrlGenerator beyn e
 * request ha zende mimune va panel/Livewire/asset ham avaz mishodan.
 */
final readonly class PublicUrl
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = []): string
    {
        $path = route($name, $parameters, false);

        // Hamun shekl e `route()` e motlagh: safhe ye asli bedun e `/` e akhar.
        return $path === '/' ? $this->root : $this->root.'/'.ltrim($path, '/');
    }
}
