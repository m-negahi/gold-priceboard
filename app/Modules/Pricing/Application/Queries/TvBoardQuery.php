<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\QuoteCurrency;
use App\Modules\Pricing\Domain\ValueObjects\Price;

/**
 * Board e TV (P5-03) — yek gheymat baraye har symbol، font e bozorg.
 *
 * ── Chera gheymat e MO'ASSER va na miangin ─────────────────────────
 * Maghaze-i ke TV ro be divar mizane YEK adad mikhad ke ba feed e API
 * (`EffectivePriceQuery` — zanjire ye olaviat e per-symbol e P5-07) yeki
 * bashe. Miangin adadi-e ke hich manba'-i e'lam nakarde.
 *
 * ── Chera ETag az MOHTAVA ──────────────────────────────────────────
 * `boardVersion` faghat ba tick avaz mishe؛ taghir e olaviat (P5-07) bedun
 * e tick adad e TV ro avaz mikone. ETag e version-i yani TV ta tick e ba'di
 * adad e ghadimi neshun mide. Hash e radif ha arzun ast (≈15 symbol).
 */
final readonly class TvBoardQuery
{
    public function __construct(
        private EffectivePriceQuery $effective,
    ) {}

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   degraded: bool, alarm: bool, updatedAt: string|null, etag: string,
     * }
     */
    public function present(): array
    {
        $feed = $this->effective->feed();
        $rows = [];
        $updatedAt = null;

        foreach ($feed['symbols'] as $symbol) {
            $instrument = Instrument::tryFrom((string) $symbol['symbol']);

            if ($instrument === null) {
                continue;
            }

            $quote = QuoteCurrency::from((string) $symbol['quote']);
            $price = new Price((int) $symbol['sell'], $quote);
            $change = (float) $symbol['changePercent'];

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'metal' => $instrument->metal()?->value,
                'groupLabel' => $instrument->groupLabel(),
                'icon' => $instrument->icon(),
                'unitLabel' => $instrument->unit()->label(),
                'quoteLabel' => $quote->displayLabel(),
                // `value` (minor e kham) faghat baraye tashkhis e flash tu JS
                // (bala/paeen)؛ namayesh hamishe `display` (bcmath، bedun e float).
                'value' => $price->minor,
                'display' => $price->display(),
                'change' => number_format(abs($change), 2),
                'direction' => (string) $symbol['direction'],
                'isStale' => (bool) $symbol['isStale'],
            ];

            $quotedAt = (string) $symbol['quotedAt'];

            if ($updatedAt === null || $quotedAt > $updatedAt) {
                $updatedAt = $quotedAt;
            }
        }

        return [
            'rows' => $rows,
            'degraded' => $feed['degraded'],
            'alarm' => $feed['alarm'],
            'updatedAt' => $updatedAt,
            'etag' => '"tv-'.substr(sha1((string) json_encode([$rows, $feed['degraded'], $feed['alarm']])), 0, 16).'"',
        ];
    }
}
