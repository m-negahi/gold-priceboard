<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\ProviderSpreadAction;
use App\Modules\Pricing\Application\Dto\SpreadInputDto;
use App\Modules\Pricing\Application\Queries\SpreadQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\SpreadMode;
use App\Modules\Pricing\Domain\Exceptions\InvalidSpreadException;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpread;
use App\Modules\Pricing\Infrastructure\Persistence\Models\ProviderSpreadRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\ArrayHotCache;
use Tests\Support\Fakes\FrozenClock;
use Tests\TestCase;

final class ProviderSpreadActionTest extends TestCase
{
    use RefreshDatabase;

    private ArrayHotCache $cache;

    private FrozenClock $clock;

    private ProviderSpreadAction $action;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayHotCache;
        $this->clock = new FrozenClock('2026-10-05T14:00:00Z');
        $this->action = new ProviderSpreadAction($this->cache, $this->clock);

        $this->provider = Provider::create([
            'key' => 'tgju',
            'driver' => 'tgju',
            'kind' => 'reference',
            'name' => 'TGJU',
            'name_fa' => 'طلا و جواهر',
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function row(string $symbol, array $overrides = []): SpreadInputDto
    {
        return SpreadInputDto::fromArray(array_merge([
            'symbol' => $symbol,
            'buy_mode' => 'percent', 'buy_value' => -0.5,
            'sell_mode' => 'percent', 'sell_value' => 0.5,
        ], $overrides));
    }

    // ── Masir e asli ────────────────────────────────────────────────

    public function test_sync_creates_rows_writes_revisions_and_bumps_the_version(): void
    {
        $before = $this->cache->spreadVersion();

        $result = $this->action->sync($this->provider, [
            $this->row(ProviderSpread::WILDCARD),
            $this->row(Instrument::Gold18Gram->value, ['buy_value' => -1.0, 'sell_value' => 1.5]),
        ], changedBy: 'admin:7');

        $this->assertSame(['*', 'xau-18k-g-irr'], $result->created);
        $this->assertSame([], $result->updated);
        $this->assertSame(2, $result->revisions);

        $this->assertSame(2, ProviderSpread::query()->count());
        $this->assertSame(2, ProviderSpreadRevision::query()->count());

        // Har se chiz ba ham: radif، revision، bump.
        $this->assertTrue($result->cacheInvalidated);
        $this->assertSame($before + 1, $result->spreadVersion);
        $this->assertSame($before + 1, $this->cache->spreadVersion());

        $revision = ProviderSpreadRevision::query()->where('symbol', 'xau-18k-g-irr')->firstOrFail();
        $this->assertSame('admin:7', $revision->changed_by);
        $this->assertFalse($revision->is_removal);
        $this->assertSame(-1.0, $revision->buy_value);
        $this->assertSame($this->clock->now()->timestamp, $revision->effective_from->timestamp);
    }

    public function test_sync_updates_an_existing_row_and_records_the_new_values(): void
    {
        $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value)]);

        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 0.8]),
        ], changedBy: 'admin:1');

        $this->assertSame(['xau-18k-g-irr'], $result->updated);
        $this->assertSame([], $result->created);

        // Yek radif e `provider_spreads`، DO revision — daftarche append-only
        // ast.
        $this->assertSame(1, ProviderSpread::query()->count());
        $this->assertSame(2, ProviderSpreadRevision::query()->count());

        $latest = ProviderSpreadRevision::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame(0.8, $latest->sell_value);
    }

    // ── 🔴 Save ye bi-taghir ────────────────────────────────────────

    public function test_an_unchanged_save_writes_no_revision_and_does_not_bump(): void
    {
        $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value)]);

        $version = $this->cache->spreadVersion();

        // Hamun meghdar ha dobare submit mishan — hamun chizi ke vaghti admin
        // yek symbol ro avaz mikone baraye 14 symbol e DIGE ettefagh mioftad
        // (form yek radif baraye har `Instrument::cases()` mide).
        $result = $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value)]);

        $this->assertSame(['xau-18k-g-irr'], $result->unchanged);
        $this->assertFalse($result->changed());
        $this->assertFalse($result->cacheInvalidated);

        // Bedun e in moghayese: revision haye ja'li (daftarche ye audit ro
        // bi-estefade mikone) + bump e bi-dalil (kol e cache e chart ro batel
        // mikone).
        $this->assertSame(1, ProviderSpreadRevision::query()->count());
        $this->assertSame($version, $this->cache->spreadVersion());
    }

    public function test_a_mixed_save_only_touches_what_actually_changed(): void
    {
        $this->action->sync($this->provider, [
            $this->row(ProviderSpread::WILDCARD),
            $this->row(Instrument::Gold18Gram->value),
            $this->row(Instrument::Gold24Gram->value),
        ]);

        $result = $this->action->sync($this->provider, [
            $this->row(ProviderSpread::WILDCARD),
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 2.0]),
            $this->row(Instrument::Gold24Gram->value),
            $this->row(Instrument::Silver999Gram->value),
        ]);

        $this->assertSame(['xag-999-g-irr'], $result->created);
        $this->assertSame(['xau-18k-g-irr'], $result->updated);
        $this->assertSame(['*', 'xau-24k-g-irr'], $result->unchanged);
        $this->assertSame(2, $result->revisions);
    }

    /**
     * 🔴 `decimal(12,4)` e DB meghdar ro gerd mikone. Bedun e `round()` tu
     * `comparable()`، har save ye ba'di un radif ro «avaz shode» mibine.
     */
    public function test_rounding_to_the_column_scale_does_not_fake_a_change(): void
    {
        $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 0.50001]),
        ]);

        $version = $this->cache->spreadVersion();

        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 0.50001]),
        ]);

        $this->assertSame(['xau-18k-g-irr'], $result->unchanged);
        $this->assertSame($version, $this->cache->spreadVersion());
    }

    public function test_toggling_is_enabled_counts_as_a_change(): void
    {
        $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value)]);

        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['is_enabled' => false]),
        ]);

        // `is_enabled=false` radif ro az `SpreadQuery::map()` birun mindaze —
        // ya'ni gheymat e namayesh dade shode AVAZ mishe. Pas taghir ast.
        $this->assertSame(['xau-18k-g-irr'], $result->updated);
        $this->assertTrue($result->cacheInvalidated);
    }

    public function test_a_freshness_override_counts_as_a_change(): void
    {
        $this->action->sync($this->provider, [$this->row(Instrument::GoldOunce->value)]);

        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::GoldOunce->value, ['freshness_seconds' => 90_000]),
        ]);

        $this->assertSame(['xau-oz-usd'], $result->updated);
    }

    // ── Hazf ────────────────────────────────────────────────────────

    public function test_remove_deletes_the_row_and_records_a_removal_revision(): void
    {
        $this->action->sync($this->provider, [
            $this->row(ProviderSpread::WILDCARD),
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 1.25]),
        ]);

        $version = $this->cache->spreadVersion();

        $result = $this->action->remove($this->provider, Instrument::Gold18Gram->value, 'admin:9');

        $this->assertSame(['xau-18k-g-irr'], $result->removed);
        $this->assertTrue($result->cacheInvalidated);
        $this->assertSame($version + 1, $this->cache->spreadVersion());

        // Radif raft، vali daftarche HAZF ro sabt karde — ba hamun meghdar
        // hayi ke ghabl az hazf dar jarian bud.
        $this->assertNull(
            ProviderSpread::query()
                ->where('provider_id', $this->provider->id)
                ->where('symbol', 'xau-18k-g-irr')
                ->first(),
        );

        $revision = ProviderSpreadRevision::query()->orderByDesc('id')->firstOrFail();
        $this->assertTrue($revision->is_removal);
        $this->assertSame('xau-18k-g-irr', $revision->symbol);
        $this->assertSame(1.25, $revision->sell_value);
        $this->assertSame('admin:9', $revision->changed_by);
    }

    public function test_removing_a_symbol_falls_back_to_the_wildcard(): void
    {
        $this->action->sync($this->provider, [
            $this->row(ProviderSpread::WILDCARD, ['sell_value' => 0.2]),
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 3.0]),
        ]);

        $query = $this->app->make(SpreadQuery::class);

        $this->assertSame(
            3.0,
            SpreadQuery::resolve($query->map(), 'tgju', Instrument::Gold18Gram)->sell->value,
        );

        $this->action->remove($this->provider, Instrument::Gold18Gram->value);

        // Hazf e override yek taghir e VAGHEI tu gheymat e namayeshi ast —
        // dalil e inke bayad revision + bump dashte bashe.
        $this->assertSame(
            0.2,
            SpreadQuery::resolve($query->map(), 'tgju', Instrument::Gold18Gram)->sell->value,
        );
    }

    public function test_removing_a_row_that_does_not_exist_is_a_silent_no_op(): void
    {
        $version = $this->cache->spreadVersion();

        $result = $this->action->remove($this->provider, Instrument::Gold18Gram->value);

        $this->assertSame([], $result->removed);
        $this->assertFalse($result->cacheInvalidated);
        $this->assertSame($version, $this->cache->spreadVersion());
        $this->assertSame(0, ProviderSpreadRevision::query()->count());
    }

    public function test_sync_never_deletes_rows_that_were_not_submitted(): void
    {
        $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value),
            $this->row(Instrument::Gold24Gram->value),
        ]);

        // Submit e nime-kare — faghat yek radif.
        $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value, ['sell_value' => 9.0])]);

        // Yek bug e form ya yek submit e nime-kare nabayad hame ye override
        // ha ro bebare. Hazf FAGHAT ba `remove()` e sarih.
        $this->assertSame(2, ProviderSpread::query()->count());
    }

    // ── Validation ──────────────────────────────────────────────────

    public function test_an_unknown_symbol_is_rejected(): void
    {
        // 🔴 Bad tarin halat: radif zakhire mishe va HICHVAGHT resolve
        // nemishe. Admin fekr mikone spread set shode، tablo gheymat e kham
        // mide — bedun e hich khata.
        $this->expectException(InvalidSpreadException::class);
        $this->expectExceptionMessageMatches('/شناخته‌شده نیست/');

        $this->action->sync($this->provider, [$this->row('xau-18k-gram-irr')]);
    }

    public function test_a_duplicate_symbol_in_one_submit_is_rejected(): void
    {
        $this->expectExceptionMessageMatches('/بیش از یک بار/');

        $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 1.0]),
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 2.0]),
        ]);
    }

    public function test_a_percent_spread_of_minus_one_hundred_or_worse_is_rejected(): void
    {
        foreach ([-100.0, -150.0, 100.0, 250.0] as $value) {
            try {
                $this->action->sync($this->provider, [
                    $this->row(Instrument::Gold18Gram->value, ['buy_value' => $value]),
                ]);
                $this->fail("darsad e {$value} bayad rad mishod.");
            } catch (InvalidSpreadException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_large_amount_spread_is_allowed_because_the_unit_is_currency_dependent(): void
    {
        // `amount` tu vahed e ASLI ye arz ast: `500000` rial manteghi-e،
        // hamun adad baraye USD nist. Baraye wildcard aslan nemidunim kodum
        // arz ast، pas yek band e adadi inja dorugh misaze.
        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, [
                'sell_mode' => 'amount',
                'sell_value' => 500_000,
            ]),
        ]);

        $this->assertSame(['xau-18k-g-irr'], $result->created);

        $row = ProviderSpread::query()->firstOrFail();
        $this->assertSame(SpreadMode::Amount, $row->sell_mode);
        $this->assertSame(500_000.0, $row->sell_value);
    }

    /**
     * 🔴 In test aval ba saghf e 86,400 neveshte shod va BE-DOROSTI shekast:
     * config e vaghei ye seeder baraye symbol haye jahani ye tgju 90,000
     * saniye ast (un ha faghat RUZANE be-rooz mishan). Saghf e yek rooz،
     * tanzim e khod e projeh ro rad mikard.
     */
    public function test_a_daily_updating_symbol_may_exceed_twenty_four_hours(): void
    {
        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::GoldOunce->value, ['freshness_seconds' => 90_000]),
        ]);

        $this->assertSame(['xau-oz-usd'], $result->created);
        $this->assertSame(90_000, ProviderSpread::query()->firstOrFail()->freshness_seconds);
    }

    public function test_an_out_of_range_freshness_override_is_rejected(): void
    {
        foreach ([0, 700_000] as $seconds) {
            try {
                $this->action->sync($this->provider, [
                    $this->row(Instrument::Gold18Gram->value, ['freshness_seconds' => $seconds]),
                ]);
                $this->fail("tazegi ye {$seconds}s bayad rad mishod.");
            } catch (InvalidSpreadException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Validation GHABL az transaction ejra mishe، pas yek radif e kharab tu
     * vasat e dast، radif haye salem e ghabl esh ro ham nemizare.
     */
    public function test_nothing_is_written_when_any_row_in_the_batch_is_invalid(): void
    {
        try {
            $this->action->sync($this->provider, [
                $this->row(Instrument::Gold18Gram->value),
                $this->row('bogus-symbol'),
            ]);
        } catch (InvalidSpreadException) {
            // entezar.
        }

        $this->assertSame(0, ProviderSpread::query()->count());
        $this->assertSame(0, ProviderSpreadRevision::query()->count());
        $this->assertSame(0, $this->cache->spreadVersion());
    }

    // ── 🔴 Ghat'i ye Redis ──────────────────────────────────────────

    public function test_spreads_are_saved_even_when_the_hot_cache_is_down(): void
    {
        $this->action->sync($this->provider, [$this->row(Instrument::Gold18Gram->value)]);

        $this->cache->breakIt();

        $result = $this->action->sync($this->provider, [
            $this->row(Instrument::Gold18Gram->value, ['sell_value' => 4.0]),
        ]);

        // Commit e Postgres ettefagh oftade — spread ha ZAKHIRE shodan.
        $this->assertSame(['xau-18k-g-irr'], $result->updated);
        $this->assertSame(4.0, ProviderSpread::query()->firstOrFail()->sell_value);

        // Vali bump nashode. Panel bayad hamin ro be admin bege: tablo fori
        // durost ast (`BoardQuery` cache nemikone)، faghat cache e nemudar
        // ta enghaza ye TTL javab e ghadim mide.
        $this->assertFalse($result->cacheInvalidated);
        $this->assertNull($result->spreadVersion);
    }
}
