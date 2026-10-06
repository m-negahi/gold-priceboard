<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Modules\Seo\Application\Queries\HomeSeoQuery;
use App\Modules\Seo\Application\Queries\SitemapQuery;
use Tests\TestCase;

/**
 * Bind haye `SeoServiceProvider` (P5-05): config be onvan e argument tazrigh
 * mishe — bind e shekaste ya provider e register-nashode inja ghermez mishe.
 */
final class SeoBindingTest extends TestCase
{
    public function test_the_seo_config_is_merged(): void
    {
        self::assertSame('تابلو قیمت', config('seo.site_name'));
        self::assertNotEmpty(config('seo.faq'));
        self::assertContains('/dashboard', config('seo.disallow'));
    }

    public function test_queries_resolve_with_config_injected_per_resolve(): void
    {
        self::assertInstanceOf(HomeSeoQuery::class, $this->app->make(HomeSeoQuery::class));
        self::assertInstanceOf(SitemapQuery::class, $this->app->make(SitemapQuery::class));

        // `bind` (na singleton): har resolve config e LAHZE ro mibine.
        self::assertNotSame($this->app->make(SitemapQuery::class), $this->app->make(SitemapQuery::class));
    }
}
