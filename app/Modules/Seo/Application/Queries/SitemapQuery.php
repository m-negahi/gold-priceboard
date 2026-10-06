<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Queries;

use App\Modules\Pricing\Application\Queries\PublicBoardQuery;
use App\Modules\Seo\Application\Dto\SeoMetaDto;
use App\Modules\Seo\Application\Support\PublicUrl;
use Carbon\CarbonImmutable;

/**
 * Sitemap (P5-05): yek INDEX (`/sitemap.xml`) + sitemap e safhe ha.
 *
 * Index amdan az alan: safhe haye har symbol / maghale (ba'dan) sitemap e
 * khodeshun ro migiran bedun e inke URL e sabt-shode tu Search Console
 * avaz beshe.
 *
 * 🔴 Safhe-i ke `robots` esh `noindex` ast (pish-farz ya override e
 * `seo_meta`) tu sitemap NIST — sitemap-i ke URL e noindex dare signal e
 * motanaghez be crawler mide.
 */
final readonly class SitemapQuery
{
    /**
     * @param  list<array{route: string, changefreq: string, priority: string, live?: bool}>  $pages
     */
    public function __construct(
        private SeoResolver $resolver,
        private PublicBoardQuery $board,
        private PublicUrl $urls,
        private array $pages,
    ) {}

    /** URL e motlagh e index baraye khat e `Sitemap:` e robots.txt (root az `app.url`). */
    public function indexUrl(): string
    {
        return $this->urls->route('sitemap.index');
    }

    /** @return list<array{loc: string, lastmod: string|null}> */
    public function index(): array
    {
        $lastmods = array_filter(array_column($this->pages(), 'lastmod'));

        return [[
            'loc' => $this->urls->route('sitemap.pages'),
            'lastmod' => $lastmods === [] ? null : max($lastmods),
        ]];
    }

    /** @return list<array{loc: string, lastmod: string|null, changefreq: string, priority: string}> */
    public function pages(): array
    {
        $liveAt = null;
        $entries = [];

        foreach ($this->pages as $page) {
            $loc = $this->urls->route($page['route']);
            $seo = $this->resolver->resolve('page', $page['route'], new SeoMetaDto(title: '', description: '', canonical: $loc));

            if (! $seo->isIndexable()) {
                continue;
            }

            $lastmod = null;

            // Safhe ye «zende» (tablo) = akharin gheymat.
            if ($page['live'] ?? false) {
                $liveAt ??= $this->board->present()['updatedAt'];
                $lastmod = $liveAt === null ? null : CarbonImmutable::parse($liveAt)->toAtomString();
            }

            $entries[] = [
                'loc' => $seo->canonical,
                'lastmod' => $lastmod,
                'changefreq' => $page['changefreq'],
                'priority' => $page['priority'],
            ];
        }

        return $entries;
    }
}
