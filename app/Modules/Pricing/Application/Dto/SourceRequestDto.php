<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Tosif e request e yek Source — bedun e ejra.
 *
 * ── 🔴 Chera vojud dare ──────────────────────────────────────────────
 * estjt ~7 saniye javab mide, tgju ~0.5 saniye. Ba tick e 15 saniye-i,
 * fetch e poshte-sar-e-ham ya'ni nesf e budget sarf e yek provider mishe —
 * va ba provider e SEVOM, tick az 15 saniye rad mikone.
 *
 * Ba jodā kardan e «chetor beporsim» (in Dto) az «chetor bekhunim»
 * (`parse()`), `FetchStage` mitune hame ro ba `Http::pool` HAMZAMAN bezane.
 * Ba'd e un, kond tarin provider tool e tick ro moshakhas mikone, na jam' e
 * hamashun.
 */
final readonly class SourceRequestDto
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, scalar>  $body  field haye form (faghat POST)
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public array $body = [],
        public int $timeout = 10,
        /**
         * Majbur kardan e nasakhe ye HTTP.
         *
         * estjt ba negotiation e default **timeout** mikhore va faghat ba
         * `1.1` javab mide.
         */
        public ?string $httpVersion = null,
        /**
         * Cookie jar e PER-REQUEST (hich halat e moshtarak — Octane-safe).
         *
         * melligold posht e challenge e ArvanCloud ast: pasokh e avval 307 be
         * HAMUN URL + `Set-Cookie`؛ bedun e jar، redirect be halghe mioftad
         * va hich vaght 200 nemigirim.
         */
        public bool $cookies = false,
    ) {}

    public function isPost(): bool
    {
        return strtoupper($this->method) === 'POST';
    }
}
