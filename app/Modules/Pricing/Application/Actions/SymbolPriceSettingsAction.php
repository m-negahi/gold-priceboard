<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Actions;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ManualPrice;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\SymbolPriceSetting;
use InvalidArgumentException;

final readonly class SymbolPriceSettingsAction
{
    public function set(Instrument $instrument, bool $manualOnly, bool $fallbackEnabled, string $autoSource): void
    {
        if (! in_array($autoSource, ['none', 'priority'], true)
            && ($autoSource === 'manual' || ! Provider::query()->where('key', $autoSource)->exists())) {
            throw new InvalidArgumentException('منبع به‌روزرسانی دستی معتبر نیست.');
        }

        if ($manualOnly && ! ManualPrice::query()->where('symbol', $instrument->value)->exists()) {
            throw new InvalidArgumentException('برای فعال کردن فقط دستی، ابتدا قیمت دستی را ثبت کنید.');
        }

        SymbolPriceSetting::query()->updateOrCreate(['symbol' => $instrument->value], [
            'manual_only' => $manualOnly,
            'manual_fallback_enabled' => $fallbackEnabled,
            'manual_auto_source' => $autoSource,
        ]);
    }
}
