<?php

declare(strict_types=1);

namespace App\Modules\Seo\Providers;

use App\Modules\Pricing\Application\Queries\BoardOfferQuery;
use App\Modules\Pricing\Application\Queries\PublicBoardQuery;
use App\Modules\Seo\Application\Queries\HomeSeoQuery;
use App\Modules\Seo\Application\Queries\InstrumentSeoQuery;
use App\Modules\Seo\Application\Queries\SeoResolver;
use App\Modules\Seo\Application\Queries\SitemapQuery;
use App\Modules\Seo\Application\Support\PublicUrl;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Module e SEO (P5-05). Query ha config e IMMUTABLE ro be onvan e argument
 * migiran (na `config()` dakhel e method) — `bind` (na singleton) ta har
 * request config e lahze ro bebine (test ha `config([...])` mikonan).
 * Har bind tu `SeoBindingTest` assert mishe.
 */
final class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/seo.php', 'seo');

        // Root e URL haye SEO az `app.url` — hargez az header e `Host` (PublicUrl).
        $this->app->bind(PublicUrl::class, static fn (): PublicUrl => new PublicUrl((string) config('app.url')));

        $this->app->bind(HomeSeoQuery::class, static fn (Application $app): HomeSeoQuery => new HomeSeoQuery(
            resolver: $app->make(SeoResolver::class),
            offers: $app->make(BoardOfferQuery::class),
            urls: $app->make(PublicUrl::class),
            siteName: (string) config('seo.site_name'),
            title: (string) config('seo.home.title'),
            description: (string) config('seo.home.description'),
            faq: array_values((array) config('seo.faq', [])),
        ));

        $this->app->bind(InstrumentSeoQuery::class, static fn (Application $app): InstrumentSeoQuery => new InstrumentSeoQuery(
            resolver: $app->make(SeoResolver::class),
            offers: $app->make(BoardOfferQuery::class),
            urls: $app->make(PublicUrl::class),
            siteName: (string) config('seo.site_name'),
            titleTemplate: (string) config('seo.instrument.title', 'قیمت {label} امروز'),
            descriptionTemplate: (string) config('seo.instrument.description', ''),
        ));

        $this->app->bind(SitemapQuery::class, static fn (Application $app): SitemapQuery => new SitemapQuery(
            resolver: $app->make(SeoResolver::class),
            board: $app->make(PublicBoardQuery::class),
            urls: $app->make(PublicUrl::class),
            pages: array_values((array) config('seo.pages', [])),
        ));
    }
}
