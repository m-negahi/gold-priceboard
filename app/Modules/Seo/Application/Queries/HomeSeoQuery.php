<?php

declare(strict_types=1);

namespace App\Modules\Seo\Application\Queries;

use App\Modules\Pricing\Application\Dto\SymbolOfferDto;
use App\Modules\Pricing\Application\Queries\BoardOfferQuery;
use App\Modules\Seo\Application\Dto\SeoMetaDto;
use App\Modules\Seo\Application\Support\PublicUrl;
use App\Modules\Seo\Domain\Services\JsonLd;

/**
 * SEO e safhe ye asli (tablo ye moghayese — P5-05).
 *
 * JSON-LD: `WebSite` + yek `Product`/`AggregateOffer` baraye har symbol
 * (kamtarin/bishtarin beyn e manba' ha) + `FAQPage`. FAQ e ru safhe va
 * FAQ e JSON-LD AZ YEK MANBA' (`seo.faq`) miyan — Google FAQPage-i ke ru
 * safhe dide nashe ro «spam e structured data» hesab mikone.
 */
final readonly class HomeSeoQuery
{
    /**
     * @param  list<array{question: string, answer: string}>  $faq
     */
    public function __construct(
        private SeoResolver $resolver,
        private BoardOfferQuery $offers,
        private PublicUrl $urls,
        private string $siteName,
        private string $title,
        private string $description,
        private array $faq,
    ) {}

    /**
     * @return array{seo: SeoMetaDto, faq: list<array{question: string, answer: string}>}
     */
    public function present(): array
    {
        $url = $this->urls->route('home');

        $seo = $this->resolver->resolve('page', 'home', new SeoMetaDto(
            title: $this->title.' — '.$this->siteName,
            description: $this->description,
            canonical: $url,
            siteName: $this->siteName,
        ));

        $nodes = [JsonLd::website($this->siteName, $url, $seo->description)];

        foreach ($this->offers->offers() as $offer) {
            $nodes[] = $this->product($offer, $url);
        }

        $nodes[] = JsonLd::faqPage($this->faq);

        return ['seo' => $seo->withJsonLd($nodes), 'faq' => $this->faq];
    }

    /** @return array<string, mixed> */
    private function product(SymbolOfferDto $offer, string $url): array
    {
        $instrument = $offer->instrument;

        return JsonLd::product(
            name: $instrument->label(),
            sku: $instrument->value,
            url: $url.'#'.$instrument->value,
            currency: $instrument->quote()->value,
            lowPrice: $offer->low->decimal(),
            highPrice: $offer->high->decimal(),
            offerCount: $offer->count,
            category: $instrument->groupLabel(),
        );
    }
}
