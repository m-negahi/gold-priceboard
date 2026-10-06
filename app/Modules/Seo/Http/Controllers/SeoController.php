<?php

declare(strict_types=1);

namespace App\Modules\Seo\Http\Controllers;

use App\Modules\Seo\Application\Queries\SitemapQuery;
use App\Modules\Seo\Domain\Services\RobotsTxt;
use Illuminate\Http\Response;

/**
 * `robots.txt` + sitemap (P5-05) — nazok: Query/Domain + view e XML.
 */
final readonly class SeoController
{
    public function __construct(
        private SitemapQuery $sitemap,
    ) {}

    public function robots(): Response
    {
        $body = RobotsTxt::render(
            (bool) config('seo.indexable', false),
            array_values((array) config('seo.disallow', [])),
            route('sitemap.index'),
        );

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemapIndex(): Response
    {
        return $this->xml(view('seo.sitemap-index', ['sitemaps' => $this->sitemap->index()])->render());
    }

    public function sitemapPages(): Response
    {
        return $this->xml(view('seo.sitemap-urlset', ['urls' => $this->sitemap->pages()])->render());
    }

    private function xml(string $body): Response
    {
        // Declaration INJA، na tu Blade: tag e `<?xml` tu template ba tag e
        // PHP eshtebah gerefte mishe (syntax error e compile). 🔴 Tu comment
        // e `//` alamat e bastan e PHP nanevis — comment ro mibande.
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$body;

        return new Response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
