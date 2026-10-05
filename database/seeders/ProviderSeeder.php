<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use Illuminate\Database\Seeder;

/**
 * Provider haye marhale ye avval.
 *
 * ⚠️ `is_active = false` shoru' mishan. Ravesh e kar (skill e
 * `add-price-provider`): avval `pricing:tick` ro dasti bezan، `TickMonitor`
 * ro check kon، va faghat vaghti `last_rejected_count` sefr bud roshan kon.
 */
final class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $this->provider([
            'key' => 'tgju',
            'driver' => 'tgju',
            'name' => 'TGJU',
            'name_fa' => 'تی‌جی‌جی‌یو',
            'website' => 'https://www.tgju.org',
            'kind' => 'reference',
            'sort' => 10,
            'timeout' => 15,
            // Gheymat haye IRR e in feed RIAL-and (bar khalaf e estjt).
            'upstream_unit' => 'rial',
        ], [
            /*
             * 🔴 In chahar symbol tu feed e tgju faghat RUZANE be-rooz
             * mishan (`ts` = nime-shab e rooz e ghabl)، na zende.
             *
             * `Instrument::freshnessSeconds()` baraye jahani 180s mide —
             * bedun e in override، har tick radeshun mikone va chahar radif
             * az tablo gom mishan bedun e hich error.
             *
             * (Tu `TgjuQuoteSourceTest::test_global_metals_are_only_updated_
             * daily_in_this_feed` sabt shode.)
             */
            Instrument::GoldOunce->value => 90_000,
            Instrument::SilverOunce->value => 90_000,
            Instrument::CopperPound->value => 90_000,
            Instrument::CoinGerami->value => 90_000,
        ]);

        $this->provider([
            'key' => 'estjt',
            'driver' => 'estjt',
            'name' => 'Esfahan Gold Union',
            'name_fa' => 'اتحادیه طلا و جواهر اصفهان',
            'website' => 'https://www.estjt.ir',
            'kind' => 'reference',
            'sort' => 20,
            // ~7-9 saniye javab mide — andaze-giri shode.
            'timeout' => 20,
            // ⚠️ Faghat DEFAULT. «انس طلا» ye hamin feed DOLLAR ast —
            // un estesna tu map e khod e Source ast.
            'upstream_unit' => 'toman',
        ], [
            // estjt timestamp NEMIDE، pas `upstreamAt` null ast va check e
            // tazegi aslan ejra nemishe. In meghdar ha faghat baraye zaman-i
            // ast ke ruzi timestamp ezafe konan.
            Instrument::GoldOunce->value => 90_000,
        ]);

        $this->provider([
            'key' => 'mock',
            'driver' => 'mock',
            'name' => 'Mock',
            'name_fa' => 'آزمایشی',
            'kind' => 'reference',
            'sort' => 90,
            'in_comparison' => false,
            'on_board' => false,
            'in_chart' => false,
        ], []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $freshnessOverrides
     */
    private function provider(array $attributes, array $freshnessOverrides): void
    {
        $provider = Provider::query()->updateOrCreate(
            ['key' => $attributes['key']],
            array_merge(['is_active' => false], $attributes),
        );

        // Spread e wildcard: sefr — admin ba'dan tu panel tanzim mikone.
        ProviderSpread::query()->updateOrCreate(
            ['provider_id' => $provider->id, 'symbol' => ProviderSpread::WILDCARD],
            ['buy_mode' => 'percent', 'buy_value' => 0, 'sell_mode' => 'percent', 'sell_value' => 0],
        );

        foreach ($freshnessOverrides as $symbol => $seconds) {
            ProviderSpread::query()->updateOrCreate(
                ['provider_id' => $provider->id, 'symbol' => $symbol],
                [
                    'buy_mode' => 'percent', 'buy_value' => 0,
                    'sell_mode' => 'percent', 'sell_value' => 0,
                    'freshness_seconds' => $seconds,
                ],
            );
        }
    }
}
