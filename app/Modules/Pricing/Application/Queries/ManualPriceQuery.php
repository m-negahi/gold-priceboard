<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Common\Helpers\Jalali;
use App\Modules\Pricing\Application\Dto\BoardRowDto;
use App\Modules\Pricing\Domain\Enums\Direction;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Domain\Ports\ClockPort;
use App\Modules\Pricing\Domain\Services\SpreadPricer;
use App\Modules\Pricing\Domain\ValueObjects\Price;
use App\Modules\Pricing\Domain\ValueObjects\SpreadPair;
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
                // Display unit (rial/dollar) baraye namayesh tu form.
                'display' => $row !== null ? bcdiv((string) $row->minor, (string) ($divisor * (10 ** $scale)), max($scale, $divisor === 10 ? 1 : 0)) : null,
                'sourceHint' => $row?->source_hint,
                'updatedBy' => $row?->updated_by,
                'updatedAt' => $row?->updated_at?->toIso8601String(),
                'updatedDisplay' => $row?->updated_at !== null ? Jalali::format($row->updated_at, 'Y/m/d H:i:s') : '—',
                'unitLabel' => $instrument->quote()->displayLabel(),
                'ageSeconds' => $age,
                'isAlarm' => $age !== null && $age > $this->alarmAfterSeconds,
            ];
        }

        return $rows;
    }

    /** @param array<string, array<string, SpreadPair>> $spreadMap
     * @return array<string, BoardRowDto>
     */
    public function effectiveRows(array $spreadMap): array
    {
        $rows = [];
        $now = $this->clock->now();

        foreach (ManualPrice::query()->get() as $stored) {
            $instrument = Instrument::tryFrom($stored->symbol);

            if ($instrument === null || $stored->minor <= 0 || $stored->quote_currency !== $instrument->quote()->value) {
                continue;
            }

            $price = new Price($stored->minor, $instrument->quote());
            $pair = SpreadPricer::applyPair($price, SpreadQuery::resolve($spreadMap, 'manual', $instrument));
            $age = $stored->updated_at !== null ? max(0, $now->timestamp - $stored->updated_at->timestamp) : PHP_INT_MAX;
            $rows[$stored->symbol] = new BoardRowDto(
                'manual', $instrument, Side::Last, $price, $pair['buy'], $pair['sell'],
                null, null, null, 0.0, Direction::Flat, $age, $age > $this->alarmAfterSeconds,
                true, ($stored->updated_at ?? $now)->toIso8601String(),
            );
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
