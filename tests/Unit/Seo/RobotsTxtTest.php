<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Modules\Seo\Domain\Services\RobotsTxt;
use PHPUnit\Framework\TestCase;

/**
 * `robots.txt` e dynamic (P5-05): production = crawl-pazir + sitemap؛ har
 * mohit e digar (local/staging) KOL e site ro bas mibande — staging-i ke
 * index beshe mohtava ye tekrari ye site e asli mishe.
 */
final class RobotsTxtTest extends TestCase
{
    public function test_an_indexable_site_disallows_private_paths_and_points_to_the_sitemap(): void
    {
        $txt = RobotsTxt::render(true, ['/dashboard', '/login'], 'https://example.test/sitemap.xml');

        self::assertSame(
            "User-agent: *\nDisallow: /dashboard\nDisallow: /login\n\nSitemap: https://example.test/sitemap.xml\n",
            $txt,
        );
    }

    public function test_a_non_indexable_environment_blocks_everything_and_has_no_sitemap(): void
    {
        $txt = RobotsTxt::render(false, ['/dashboard'], 'https://example.test/sitemap.xml');

        self::assertSame("User-agent: *\nDisallow: /\n", $txt);
    }
}
