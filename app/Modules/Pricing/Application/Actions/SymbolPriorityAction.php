<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Services\PriorityChain;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolProviderPriority;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Neveshtan e olaviat e per-symbol (P5-07).
 *
 * `set($instrument, [])` = bargasht be tartib e global.
 */
final readonly class SymbolPriorityAction
{
    /**
     * Zanjire ye kamel e symbol ro JAYGOZIN mikone.
     *
     * 🔴 Validate GHABL az delete: yek kelid e ghalat nabayad zanjire ye
     * ghabli ro pak kone va symbol ro bi-sar-o-seda be global bargardune.
     *
     * @param  list<string>  $providerKeys  be tartib (aval = olaviat e balatar)
     *
     * @throws InvalidArgumentException kelid e nashenas ya `manual`
     */
    public function set(Instrument $instrument, array $providerKeys): void
    {
        $keys = array_values(array_unique($providerKeys));

        if (in_array(PriorityChain::MANUAL, $keys, true)) {
            throw new InvalidArgumentException('منبع دستی همیشه آخر است و رتبه‌بندی نمی‌شود.');
        }

        /** @var array<string, int> $ids key -> id */
        $ids = Provider::query()->whereIn('key', $keys)->pluck('id', 'key')->map(static fn ($id): int => (int) $id)->all();

        $unknown = array_diff($keys, array_keys($ids));

        if ($unknown !== []) {
            throw new InvalidArgumentException('منبع ناشناخته: '.implode('، ', $unknown));
        }

        DB::transaction(function () use ($instrument, $keys, $ids): void {
            SymbolProviderPriority::query()->where('symbol', $instrument->value)->delete();

            foreach ($keys as $position => $key) {
                SymbolProviderPriority::query()->create([
                    'symbol' => $instrument->value,
                    'provider_id' => $ids[$key],
                    'position' => $position,
                ]);
            }
        });
    }
}
