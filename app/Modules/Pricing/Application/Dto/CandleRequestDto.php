<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Interval;
use App\Modules\Pricing\Domain\Enums\Side;

/**
 * Darkhast e yek series e candle.
 *
 * ── 🔴 Chera `from`/`to` quantize mishan ─────────────────────────────
 * Agar `to` e har karbar `now()` e khodesh bashe، har request yek kelid e
 * cache e YEKTA misaze va hit rate daghighan SEFR mishe. Ba quantize kardan
 * ru marz e interval، hame ye karbar haye hamun chart ru yek kelid mioftan.
 *
 * Hame ye meydan ha typed-and (enum ya int) — hich reshte ye dast-saz be
 * Flux nemirese.
 */
final readonly class CandleRequestDto
{
    /** Saghf e server-side. KLineCharts bishtar az in ro ba fayede render nemikone. */
    public const MAX_LIMIT = 5_000;

    private function __construct(
        public string $providerKey,
        public Instrument $instrument,
        public Side $side,
        public Interval $interval,
        public int $from,
        public int $to,
        public int $limit,
    ) {}

    public static function make(
        string $providerKey,
        Instrument $instrument,
        Interval $interval,
        int $from,
        int $to,
        Side $side = Side::Last,
        int $limit = self::MAX_LIMIT,
    ): self {
        $window = $interval->seconds();

        // `from` be paeen va `to` be bala quantize mishan ta bazeh hamishe
        // kamel bashe va kelid e cache beyn e karbar ha moshtarak.
        $from = intdiv($from, $window) * $window;
        $to = (int) (ceil($to / $window) * $window);

        if ($to <= $from) {
            $to = $from + $window;
        }

        return new self(
            $providerKey,
            $instrument,
            $side,
            $interval,
            $from,
            $to,
            max(1, min($limit, self::MAX_LIMIT)),
        );
    }

    /**
     * Kelid e cache.
     *
     * ⚠️ `spreadVersion` bayad hatman inja bashe: bedun esh ba'd az taghir e
     * spread، chart ta enghaza ye TTL gheymat e ghadim ro neshun mide dar hali
     * ke tablo jadid ro neshun mide — va karbar un ro bug gozaresh mikone.
     */
    public function cacheKey(int $spreadVersion): string
    {
        return sprintf(
            'pb:candles:%s:%s:%s:%s:%d:%d:s%d',
            $this->providerKey,
            $this->instrument->value,
            $this->side->value,
            $this->interval->value,
            $this->from,
            $this->to,
            $spreadVersion,
        );
    }

    public function lastGoodCacheKey(int $spreadVersion): string
    {
        return $this->cacheKey($spreadVersion).':last';
    }
}
