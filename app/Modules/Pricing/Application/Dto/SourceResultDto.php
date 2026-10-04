<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Khoruji e yek `fetch()` — gheymat ha hamrah e meta ye un fetch.
 *
 * ── Chera yek Dto va na faghat `array<RawQuoteDto>` ──────────────────
 * Avval `QuoteSourcePort` yek method e `lastPayloadHash()` dasht ke ba'd az
 * `fetch()` seda zade mishod. Un ya'ni Source bayad halat negah dare — va
 * halat tu yek class e `final readonly` e singleton zire Swoole yani nasht e
 * data beyn e request ha. Ba bargardandan e hame chiz tu yek Dto, Source
 * kamelan bi-halat mimune.
 */
final readonly class SourceResultDto
{
    /** @param array<int, RawQuoteDto> $quotes */
    public function __construct(
        public array $quotes,
        /**
         * `md5` e body e kham.
         *
         * Baraye provider hayi ke timestamp NEMIDAN (estjt): agar hash baraye
         * chand daghighe sabet bemune va bazaar baz bashe, feed yakh zade.
         */
        public ?string $payloadHash = null,
        /** Tedad e byte e payload — baraye monitoring. */
        public int $payloadBytes = 0,
        /**
         * Label/kelid hayi ke upstream dad vali ma map nakardim.
         *
         * Source khodesh log NEMIKONE — policy e log kar e laye ye
         * Application ast, va `parse()` bayad bedun e hich facade ghabl e
         * test bashe. Pipeline inha ro ba `pricing.unmapped` sabt mikone ta
         * taghir e markup e upstream tu YEK tick ma'lum beshe.
         *
         * @var array<int, string>
         */
        public array $unmappedLabels = [],
    ) {}

    public function withPayloadMeta(?string $hash, int $bytes): self
    {
        return new self($this->quotes, $hash, $bytes, $this->unmappedLabels);
    }

    public function isEmpty(): bool
    {
        return $this->quotes === [];
    }

    public function count(): int
    {
        return count($this->quotes);
    }
}
