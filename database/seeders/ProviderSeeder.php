<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Pricing\Application\Actions\ProviderAction;
use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Dto\ProviderInputDto;
use App\Modules\Pricing\Application\Dto\SpreadInputDto;
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
 *
 * ── 🔴 Chera az Action rad mishe va na `Provider::updateOrCreate()` ──
 * Seeder ghablan mostaghim minevesht، va natije in bud ke mitunest config-i
 * besaze ke `ProviderAction` un ro rad mikone. Daghighan hamin ettefagh
 * oftad: vaghti `ProviderAction` neveshte shod، do ta «invariant» esh config
 * e VAGHEI ye hamin seeder ro rad kardan (`timeout = 20` baraye estjt va
 * `freshness_seconds = 90_000` baraye symbol haye jahani). Un do invariant
 * eshtebah budan va eslah shodan — vali faghat chun test un ha ro kenar e
 * ham gozasht.
 *
 * Alan seeder HAMUN darvaze ro rad mishe ke panel: config e ghabl e ghabul
 * bedun e hich test e ezafi, mohal ast.
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
            /*
             * 🔴 Dollar (`price_dollar_rl`): tu sa'at e bazaar e arz zende
             * update mishe (fixture: ts e hamun lahze)، vali shab/ta'til
             * YAKH mizane — andaze-giri ye vaghei (2026-10-06): 124,005s
             * (~1.4 rooz) bedun e update. Default e 180s e `Instrument`
             * un ro tu bootstrap ba'd az har ta'tili kamel hazf mikard.
             * 2 rooz: gap e akhare-hafte ro pushesh mide vali dollar e
             * yek-hafte-i ro «taze» ja nemizane.
             */
            Instrument::UsdIrr->value => 172_800,
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
        $providers = new ProviderAction;

        /** @var Provider|null $existing */
        $existing = Provider::withTrashed()->where('key', $attributes['key'])->first();

        if ($existing === null) {
            $provider = $providers->create(ProviderInputDto::fromArray(
                array_merge(['is_active' => false], $attributes),
            ));
        } else {
            // 🔴 `is_active` e MOJUD hefz mishe. Bedun e in، yek `db:seed` ye
            // ma'muli har provider-i ke admin roshan karde ro KHAMOOSH mikone
            // — ya'ni site bedun e hich khata az gheymat mioftad.
            $provider = $providers->update($existing, ProviderInputDto::fromArray(
                array_merge($attributes, ['is_active' => $existing->is_active]),
            ));
        }

        $this->seedSpreads($provider, $freshnessOverrides);
    }

    /**
     * Faghat radif haye NABUDE sakhte mishan.
     *
     * 🔴 Radif haye mojud dast NEMIKHORAN. Seeder spread e wildcard ro ba
     * SEFR minevise؛ agar re-seed un ro bazneveshte kone، spread haye vaghei
     * ke admin tanzim karde pak mishan — va chun `ProviderSpreadAction` un
     * ro yek taghir e vaghei mibine، yek revision ham minevise va cache ro
     * batel mikone. Ya'ni `db:seed` tabdil mishe be «hame ye nerkh ha sefr
     * shodan» bedun e hich ekhtar.
     *
     * @param  array<string, int>  $freshnessOverrides
     */
    private function seedSpreads(Provider $provider, array $freshnessOverrides): void
    {
        $existing = ProviderSpread::query()
            ->where('provider_id', $provider->id)
            ->pluck('symbol')
            ->all();

        $rows = [];

        // Spread e wildcard: sefr — admin ba'dan tu panel tanzim mikone.
        if (! in_array(ProviderSpread::WILDCARD, $existing, true)) {
            $rows[] = new SpreadInputDto(symbol: ProviderSpread::WILDCARD);
        }

        foreach ($freshnessOverrides as $symbol => $seconds) {
            if (in_array($symbol, $existing, true)) {
                continue;
            }

            $rows[] = new SpreadInputDto(symbol: $symbol, freshnessSeconds: $seconds);
        }

        if ($rows === []) {
            return;
        }

        app(ProviderSpreadAction::class)->sync($provider, $rows, changedBy: 'seeder');
    }
}
