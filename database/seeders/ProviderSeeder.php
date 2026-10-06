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
             * 🔴 Jahani ha (ons/mes): tu feed e tgju faghat RUZANE be-rooz
             * mishan (`ts` = nime-shab e rooz e ghabl). Default e 180s
             * radeshun mikone.
             */
            Instrument::GoldOunce->value => 90_000,
            Instrument::SilverOunce->value => 90_000,
            Instrument::CopperPound->value => 90_000,
            /*
             * 🔴 GOLD/SEKKE/NOGHRE ye IRR — override ejbari (2026-10-06،
             * khaste ye sahebe proje: «tgju ham hame symbol ha ro biar»).
             *
             * In ha tu sa'at e bazaar ZENDE-and (`ts` har daghighe) VALI
             * default e 900s (sekke/tala) / 1800s (noghre) ya'ni baraye
             * bazaar e baste (shab/jom'e/ta'til) ke tgju timestamp e rooz e
             * ghabl ro mide، HAR TICK rad mishan va az board gom mishan —
             * dar hali ke estjt (ke timestamp NADARE) hamun gheymat ha ro
             * neshun mide. Hamin asymmetry bud ke karbar did «faghat estjt
             * hame symbol ha ro migire».
             *
             * 4 rooz (345,600s): close e char-shanbe ta avval e shanbe (do
             * rooz ta'til + yek ta'til e miyane) salem mimune؛ board ba
             * badge e «کهنه» akharin gheymat ro negah midare (bazaar e baste
             * = akharin nerkh، na gap — bebin §1.8 PLAN). Hafte-i yek bar
             * provider zende shod، gheymat e taze jaygozin mishe.
             */
            Instrument::Gold18Gram->value => 345_600,
            Instrument::Gold24Gram->value => 345_600,
            Instrument::GoldMeltedGram->value => 345_600,
            Instrument::GoldMesghal->value => 345_600,
            Instrument::CoinEmami->value => 345_600,
            Instrument::CoinBahar->value => 345_600,
            Instrument::CoinHalf->value => 345_600,
            Instrument::CoinQuarter->value => 345_600,
            Instrument::CoinGerami->value => 345_600,
            Instrument::Silver999Gram->value => 345_600,
            Instrument::Silver925Gram->value => 345_600,
            /*
             * Dollar: zende tu sa'at e bazaar e arz، shab/ta'til yakh.
             * Andaze-giri (2026-10-06): 124,005s bi-update. 2 rooz: gap e
             * akhare-hafte pushesh mishe، dollar e yek-hafte-i «taze» ja
             * zade nemishe.
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
