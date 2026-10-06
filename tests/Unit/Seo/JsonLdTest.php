<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use App\Modules\Seo\Domain\Services\JsonLd;
use PHPUnit\Framework\TestCase;

/**
 * JSON-LD (P5-05) — builder e MAHZ (bedun e Laravel). Shekl e schema.org +
 * 🔴 amniat e `<script>`: matn e karbar/admin nabayad betune tag ro bebande.
 */
final class JsonLdTest extends TestCase
{
    public function test_a_product_carries_an_aggregate_offer_with_exact_decimal_prices(): void
    {
        $node = JsonLd::product(
            name: 'طلای 18 عیار',
            sku: 'xau-18k-g-irr',
            url: 'https://example.test/#xau-18k-g-irr',
            currency: 'IRR',
            lowPrice: '266400000',
            highPrice: '270000000',
            offerCount: 2,
            category: 'طلا و سکه',
        );

        self::assertSame('Product', $node['@type']);
        self::assertSame('xau-18k-g-irr', $node['sku']);
        self::assertSame('طلا و سکه', $node['category']);
        self::assertSame([
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'IRR',
            'lowPrice' => '266400000',
            'highPrice' => '270000000',
            'offerCount' => 2,
        ], $node['offers']);
    }

    public function test_faq_page_maps_every_question_to_an_accepted_answer(): void
    {
        $node = JsonLd::faqPage([
            ['question' => 'س1', 'answer' => 'ج1'],
            ['question' => 'س2', 'answer' => 'ج2'],
        ]);

        self::assertSame('FAQPage', $node['@type']);
        self::assertCount(2, $node['mainEntity']);
        self::assertSame([
            '@type' => 'Question',
            'name' => 'س2',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ج2'],
        ], $node['mainEntity'][1]);
    }

    public function test_website_node_is_persian(): void
    {
        $node = JsonLd::website('تابلو قیمت', 'https://example.test/', 'توضیح');

        self::assertSame('WebSite', $node['@type']);
        self::assertSame('fa-IR', $node['inLanguage']);
        self::assertSame('https://example.test/', $node['url']);
    }

    public function test_encode_wraps_nodes_in_a_graph_with_unescaped_persian(): void
    {
        $json = JsonLd::encode([JsonLd::website('تابلو قیمت', 'https://example.test/', 'x')]);
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('WebSite', $data['@graph'][0]['@type']);
        self::assertStringContainsString('تابلو قیمت', $json);
        self::assertStringContainsString('https://example.test/', $json);
    }

    /**
     * 🔴 `</script>` tu yek title e override-shode (seo_meta) nabayad tag e
     * JSON-LD ro bebande va script e dige tazrigh kone.
     */
    public function test_encode_cannot_break_out_of_the_script_tag(): void
    {
        $json = JsonLd::encode([JsonLd::website('</script><script>alert(1)</script>', 'https://example.test/', 'a & b')]);

        self::assertStringNotContainsString('</script', $json);
        self::assertStringNotContainsString('<script', $json);
        self::assertSame('</script><script>alert(1)</script>', json_decode($json, true)['@graph'][0]['name']);
    }
}
