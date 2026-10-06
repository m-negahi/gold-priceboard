<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Queries;

use App\Modules\Pricing\Application\Queries\BoardOfferQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Seo\Application\Dto\SeoMetaDto;
use App\Modules\Seo\Application\Support\PublicUrl;
use App\Modules\Seo\Domain\Services\JsonLd;

/**
 * SEO e safhe ye instrument (`/price/{symbol}`، P10-13).
 *
 * JSON-LD: `BreadcrumbList` + `Product`/`AggregateOffer` e hamin symbol
 * (kamtarin/bishtarin beyn e manba' ha). Override az `seo_meta` ba type e
 * `instrument` va id e symbol (hamun ide-i ke `seoable_id` reshte-ast).
 */
final readonly class InstrumentSeoQuery
{
    public function __construct(
        private SeoResolver $resolver,
        private BoardOfferQuery $offers,
        private PublicUrl $urls,
        private string $siteName,
        private string $titleTemplate,
        private string $descriptionTemplate,
    ) {}

    public function present(Instrument $instrument): SeoMetaDto
    {
        $url = $this->urls->route('instrument', ['symbol' => $instrument->value]);
        $label = $instrument->label();

        $seo = $this->resolver->resolve('instrument', $instrument->value, new SeoMetaDto(
            title: strtr($this->titleTemplate, ['{label}' => $label]).' — '.$this->siteName,
            description: strtr($this->descriptionTemplate, ['{label}' => $label, '{group}' => $instrument->groupLabel()]),
            canonical: $url,
            siteName: $this->siteName,
        ));

        $nodes = [JsonLd::breadcrumb([
            ['name' => 'خانه', 'url' => $this->urls->route('home')],
            ['name' => $instrument->groupLabel(), 'url' => $this->urls->route('home').'#'.$instrument->group()],
            ['name' => $label, 'url' => $url],
        ])];

        foreach ($this->offers->offers() as $offer) {
            if ($offer->instrument !== $instrument) {
                continue;
            }

            $nodes[] = JsonLd::product(
                name: $label,
                sku: $instrument->value,
                url: $url,
                currency: $instrument->quote()->value,
                lowPrice: $offer->low->decimal(),
                highPrice: $offer->high->decimal(),
                offerCount: $offer->count,
                category: $instrument->groupLabel(),
            );
        }

        return $seo->withJsonLd($nodes);
    }
}
