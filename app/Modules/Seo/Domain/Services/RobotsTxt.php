<?php

declare(strict_types=1);

namespace App\Modules\Seo\Domain\Services;

/**
 * `robots.txt` e dynamic (P5-05) — MAHZ.
 *
 * `$indexable = false` (har mohit gheyr az production) -> `Disallow: /`
 * baraye KOL e site va bedun e sitemap: staging/local-i ke index beshe
 * mohtava ye tekrari ye site e asli mishe va rank ro mikhore.
 */
final class RobotsTxt
{
    /** @param  list<string>  $disallow */
    public static function render(bool $indexable, array $disallow, string $sitemapUrl): string
    {
        if (! $indexable) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = ['User-agent: *'];

        foreach ($disallow as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        return implode("\n", $lines)."\n\nSitemap: ".$sitemapUrl."\n";
    }
}
