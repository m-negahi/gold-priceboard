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
            'sort' => 20,
            'timeout' => 15,
            // Gheymat haye IRR e in feed RIAL-and (bar khalaf e estjt).
            'upstream_unit' => 'rial',
        ], [
            /*
             * 🔴 Jahani ha (ons/noghre/mes): tu feed e tgju faghat RUZANE
             * be-rooz mishan (`ts` = nime-shab e rooz e ghabl) va default e
             * 180s radeshun mikone. 90,000 (25h) KAFI NABUD — andaze-giri
             * (2026-10-07): mes 122,895s (34h) lag dasht va hich vaght
             * ghabul nemishod. Bazaar e JAHANI jom'e-ta-doshanbe baste-ast
             * (ta ~3 rooz bedun e update) → 259,200 (3 rooz)، hamun
             * estedlal e dollar/akhar-e-hafte. Badge e «کهنه» sen ro neshun
             * mide (PLAN §1.8: bazaar e baste = akharin nerkh، na gap).
             */
            Instrument::GoldOunce->value => 259_200,
            Instrument::SilverOunce->value => 259_200,
            Instrument::CopperPound->value => 259_200,
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
            // 🔴 Olaviat e 1 e hame ye symbol ha (khaste ye sahebe proje،
            // 2026-10-06): اتحادیه aval، ba'd tgju.
            'key' => 'estjt',
            'driver' => 'estjt',
            'name' => 'Tehran Gold Union',
            'name_fa' => 'اتحادیه طلا و جواهر تهران',
            'website' => 'https://www.estjt.ir',
            'kind' => 'reference',
            'sort' => 10,
            // ~7-9 saniye javab mide — andaze-giri shode.
            'timeout' => 20,
            // ⚠️ Faghat DEFAULT. «انس طلا» ye hamin feed DOLLAR ast —
            // un estesna tu map e khod e Source ast.
            'upstream_unit' => 'toman',
        ], [
            // estjt timestamp NEMIDE، pas `upstreamAt` null ast va check e
            // tazegi aslan ejra nemishe. In meghdar ha faghat baraye zaman-i
            // ast ke ruzi timestamp ezafe konan.
            Instrument::GoldOunce->value => 259_200,
        ]);

        $this->provider([
            'key' => 'nerkh',
            'driver' => 'nerkh',
            'name' => 'Nerkh',
            'name_fa' => 'نرخ',
            'website' => 'https://nerkh.io',
            'kind' => 'reference',
            'sort' => 30,
            // ~0.3s javab mide (andaze-giri 2026-10-06)؛ 10 ba fasele ye zyad.
            'timeout' => 10,
            // Hame ye gheymat haye IRR e in feed TOMAN-and. `OUNCE` va
            // `OUNCE_SILVER` DOLLAR-and — estesna tu map e khod e Source.
            'upstream_unit' => 'toman',
        ], [
            /*
             * nerkh baraye HAR radif timestamp e Jalali mide ke tu bazaar e
             * baste (shab/jom'e/ta'til) yakh mizane — daghighan hamun
             * asymmetry e tgju. Hamun override ha: 4 rooz baraye tala/sekke،
             * 2 rooz dollar، ~1 rooz jahani ha. Dalil e kamel bala (tgju).
             */
            Instrument::GoldOunce->value => 259_200,
            Instrument::SilverOunce->value => 259_200,
            // Noghre ye 999/925 e MOHASEBATI (az ons × dollar e hamin feed) —
            // hamun 4 rooz e radif haye IRR e noghre ye tgju.
            Instrument::Silver999Gram->value => 345_600,
            Instrument::Silver925Gram->value => 345_600,
            Instrument::Gold18Gram->value => 345_600,
            Instrument::Gold24Gram->value => 345_600,
            Instrument::GoldMesghal->value => 345_600,
            Instrument::CoinEmami->value => 345_600,
            Instrument::CoinBahar->value => 345_600,
            Instrument::CoinHalf->value => 345_600,
            Instrument::CoinQuarter->value => 345_600,
            Instrument::CoinGerami->value => 345_600,
            Instrument::UsdIrr->value => 172_800,
        ]);

        /*
         * ── Platform ha (API e khod e site، na az nerkh) ───────────────────
         * `kind = platform`: kharid/foroosh eshun vaghei-e va nabayad marker
         * e «محاسبه‌شده» begiran. Faghat 18 ayar midan. Barresi ye API ha tu
         * docblock e har Source. (Provider e hazf-shode hich radi inja
         * nadare — radif e DB esh soft-delete mishe ta tarikhche ye Influx
         * yatim nashe.)
         */
        $this->provider([
            'key' => 'melligold',
            'driver' => 'melligold',
            'name' => 'MelliGold',
            'name_fa' => 'طلای ملی',
            'website' => 'https://melligold.com',
            'kind' => 'platform',
            'sort' => 50,
            'timeout' => 10,
            // toman/g؛ jofte (`price_buy` = ask، `price_sell` = bid — fe'lan barabar).
            // 🔴 Platform e ABSHODE ast — `XAU18` be `xau-melted-g-irr` map mishe.
            'upstream_unit' => 'toman',
        ], [
            // Timestamp e epoch mide؛ shab/ta'til yakh mizane.
            Instrument::GoldMeltedGram->value => 345_600,
        ]);

        $this->provider([
            'key' => 'wallgold',
            'driver' => 'wallgold',
            'name' => 'WallGold',
            'name_fa' => 'وال‌گلد',
            'website' => 'https://wallgold.ir',
            'kind' => 'platform',
            'sort' => 40,
            'timeout' => 10,
            // toman/g؛ jofte (`SELL` = ask، `BUY` = bid). «18 ayar» esh
            // ABSHODE ye moamelati-e (mesl e hame ye platform ha). API key
            // az env (`WALLGOLD_API_KEY`) — hich secret inja.
            'upstream_unit' => 'toman',
        ], []);

        $this->provider([
            'key' => 'tlyn',
            'driver' => 'tlyn',
            'name' => 'Talayin',
            'name_fa' => 'طلایین',
            'website' => 'https://tlyn.ir',
            'kind' => 'platform',
            'sort' => 70,
            'timeout' => 10,
            // HEZAR toman (×1000 tu Source) → toman → rial؛ `XAU` dollar.
            // Jofte vaghei: `sell` = ask، `buy` = bid.
            'upstream_unit' => 'toman',
        ], [
            // Timestamp e UTC e daghigh mide؛ shab/ta'til yakh mizane — hamun
            // override haye tgju.
            Instrument::GoldOunce->value => 259_200,
            // 🔴 `GOLD18` e tlyn dar vaghe' ABSHODE ast (platform e abshode).
            Instrument::GoldMeltedGram->value => 345_600,
            Instrument::GoldMesghal->value => 345_600,
            Instrument::CoinEmami->value => 345_600,
            Instrument::CoinHalf->value => 345_600,
            Instrument::CoinQuarter->value => 345_600,
        ]);

        $this->provider([
            'key' => 'ecogold',
            'driver' => 'ecogold',
            'name' => 'EcoGold',
            'name_fa' => 'اکوگلد',
            'website' => 'https://ecogold.ir',
            'kind' => 'platform',
            'sort' => 60,
            'timeout' => 10,
            // Toman (`-IRT`) ba ashar e sefr؛ jofte vaghei (`buy_price` =
            // ask، `sell_price` = bid). «18 ayar» esh ABSHODE ast + noghre 999.
            'upstream_unit' => 'toman',
        ], [
            // `created_at` e har radif timestamp e vaghei ast؛ shab/ta'til
            // yakh mizane — hamun override haye 4 rooz e tgju.
            Instrument::GoldMeltedGram->value => 345_600,
            Instrument::CoinEmami->value => 345_600,
            Instrument::CoinHalf->value => 345_600,
            Instrument::CoinQuarter->value => 345_600,
            Instrument::Silver999Gram->value => 345_600,
        ]);

        /*
         * 🔴 Own API — gheymat e KHOD e ma (rename e «دستی (پشتیبان)»، darkhast
         * e sahebe proje 2026-10-07). Hadaf: har gheymati ke IN driver dare be
         * customer haye API dade mishe — backup e khodkar az zanjire ye olaviat
         * por esh mikone (P1-32) va admin mitune per-symbol override/فقط-دستی
         * kone. `sort = 80` ya'ni AKHARIN olaviat e zanjire (provider haye
         * vaghei avval). Timestamp nemide pas hargez stale rad nemishe —
         * fallback e akhar e feed ham hast. `key` ghofl ast (tag e Influx)
         * va `manual` mimune؛ faghat esm avaz shod.
         */
        $this->provider([
            'key' => 'manual',
            'driver' => 'manual',
            'name' => 'Own API',
            'name_fa' => 'API اختصاصی',
            'kind' => 'reference',
            'sort' => 80,
        ], []);

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
                array_merge($attributes, ['is_active' => $existing->is_active, 'is_trust_anchor' => $existing->is_trust_anchor]),
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
