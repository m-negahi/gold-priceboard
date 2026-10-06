<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;

/**
 * Khundan e gheymat haye dasti + ALARM (Phase 4/5).
 *
 * Alarm: gheymat e dasti-i ke az `alarm_after_seconds` ghadimi tar bashe
 * «bayat» hesab mishe — ya'ni admin bayad be-rooz esh kone. In JODA az
 * staleness e pipeline ast (manual hargez tu tick rad nemishe)؛ inja faghat
 * be admin mige «in gheymat e dasti ghadimi shode».
 */
final readonly class ManualPriceQuery
{
    /** Pish-farz: 24 saat. */
    private const DEFAULT_ALARM_AFTER = 86_400;

    public function __construct(
        private ClockPort $clock,
        private int $alarmAfterSeconds = self::DEFAULT_ALARM_AFTER,
    ) {}

    /**
     * Yek radif be ezaye HAR instrument (hatta un hayi ke hanuz dasti set
     * nashodan — ta admin betune por kone).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $existing = ManualPrice::query()->get()->keyBy('symbol');
        $now = $this->clock->now();
        $rows = [];

        foreach (Instrument::cases() as $instrument) {
            $row = $existing->get($instrument->value);

            $divisor = $instrument->quote()->displayDivisor();
            $scale = $instrument->quote()->scale();
            $age = $row?->updated_at !== null
                ? max(0, $now->getTimestamp() - $row->updated_at->getTimestamp())
                : null;

            $rows[] = [
                'symbol' => $instrument->value,
                'label' => $instrument->label(),
                'quote' => $instrument->quote()->value,
                'precision' => $instrument->quote()->displayPrecision(),
                'hasPrice' => $row !== null,
                // Display unit (toman/dollar) baraye namayesh tu form.
                'display' => $row !== null ? $row->minor / ($divisor * (10 ** $scale)) : null,
                'sourceHint' => $row?->source_hint,
                'updatedBy' => $row?->updated_by,
                'updatedAt' => $row?->updated_at?->toIso8601String(),
                'ageSeconds' => $age,
                'isAlarm' => $age !== null && $age > $this->alarmAfterSeconds,
            ];
        }

        return $rows;
    }

    /** Chand gheymat e dasti ALARM daran (ghadimi). */
    public function alarmCount(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach (ManualPrice::query()->get(['updated_at']) as $row) {
            if ($row->updated_at !== null
                && $now->getTimestamp() - $row->updated_at->getTimestamp() > $this->alarmAfterSeconds) {
                $count++;
            }
        }

        return $count;
    }
}
