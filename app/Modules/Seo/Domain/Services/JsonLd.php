<?php

declare(strict_types=1);

namespace App\Modules\Seo\Domain\Services;

/**
 * Sazande ye node haye schema.org baraye JSON-LD (P5-05) — MAHZ.
 *
 * Har method yek node (bedun e `@context`) mide؛ `encode()` hame ro tu
 * yek `@graph` mizare — yek `<script>` baraye kol e safhe.
 *
 * Gheymat ha REShTE-and (`Price::decimal()`): schema.org adad e dahdahi
 * mikhad va float raghame akhar e rial ro gom mikone.
 */
final class JsonLd
{
    /** @return array<string, mixed> */
    public static function website(string $name, string $url, string $description): array
    {
        return [
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'inLanguage' => 'fa-IR',
        ];
    }

    /**
     * Yek symbol = yek `Product` ba `AggregateOffer`: kamtarin/bishtarin
     * gheymat beyn e manba' ha — daghighan hamun «moghayese» ye tablo.
     *
     * @return array<string, mixed>
     */
    public static function product(
        string $name,
        string $sku,
        string $url,
        string $currency,
        string $lowPrice,
        string $highPrice,
        int $offerCount,
        ?string $category = null,
    ): array {
        $node = [
            '@type' => 'Product',
            'name' => $name,
            'sku' => $sku,
            'url' => $url,
        ];

        if ($category !== null) {
            $node['category'] = $category;
        }

        $node['offers'] = [
            '@type' => 'AggregateOffer',
            'priceCurrency' => $currency,
            'lowPrice' => $lowPrice,
            'highPrice' => $highPrice,
            'offerCount' => $offerCount,
        ];

        return $node;
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqPage(array $items): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $items),
        ];
    }

    /**
     * Matn e amade baraye `<script type="application/ld+json">`.
     *
     * 🔴 `JSON_HEX_TAG`: `<`/`>` -> `<`/`>` — vagarna yek
     * `</script>` tu title/description e override-shode tag ro mibande va
     * HTML e delkhah tazrigh mishe. Blade inja escape NEMIKONE (`{!! !!}`
     * lazem ast chon `&quot;` JSON ro kharab mikone)، pas amniat INJA-st.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function encode(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
