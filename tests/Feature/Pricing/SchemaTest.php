<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Mohafez e schema.
 *
 * Model va Action hanuz neveshte nashodan (`P1-07`), pas inja mostaghim ba
 * query builder kar mikonim — hadaf sanjidan e KHOD e schema ast, na laye ye
 * Eloquent.
 */
final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $key = 'tgju'): int
    {
        return DB::table('providers')->insertGetId([
            'key' => $key,
            'driver' => $key,
            'name' => ucfirst($key),
            'name_fa' => 'آزمایشی',
            'options' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function snapshotRow(int $providerId, array $overrides = []): array
    {
        return array_merge([
            'provider_id' => $providerId,
            'symbol' => 'xau-18k-g-irr',
            'side' => 'last',
            'quote_currency' => 'IRR',
            'scale' => 0,
            'raw_minor' => 266_400_000,
            'quoted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    public function test_every_pricing_table_exists(): void
    {
        foreach ([
            'providers',
            'provider_spreads',
            'provider_spread_revisions',
            'price_snapshots',
            'price_changes',
            'influx_backfills',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "jadval e «{$table}» nist.");
        }
    }

    public function test_provider_key_is_unique(): void
    {
        $this->provider('tgju');

        $this->expectException(QueryException::class);
        $this->provider('tgju');
    }

    /**
     * 🔴 Kelid e upsert e kol e write path.
     * Agar in unique nabashe, har tick yek radif e jadid misaze jaye update —
     * va jadval e «akharin gheymat» tabdil be yek tarikhche ye bi-marz mishe.
     */
    public function test_snapshot_is_unique_per_provider_symbol_and_side(): void
    {
        $id = $this->provider();
        DB::table('price_snapshots')->insert($this->snapshotRow($id));

        $this->expectException(QueryException::class);
        DB::table('price_snapshots')->insert($this->snapshotRow($id));
    }

    /**
     * ...vali hamun symbol ba side e motefavet BAYAD ejaze dashte bashe:
     * `xau-melted-g-irr` vaghe'an do radif e upstream dare.
     */
    public function test_same_symbol_allows_separate_bid_and_ask_rows(): void
    {
        $id = $this->provider();

        DB::table('price_snapshots')->insert($this->snapshotRow($id, [
            'symbol' => 'xau-melted-g-irr', 'side' => 'bid', 'raw_minor' => 263_175_000,
            'has_native_sides' => true,
        ]));
        DB::table('price_snapshots')->insert($this->snapshotRow($id, [
            'symbol' => 'xau-melted-g-irr', 'side' => 'ask', 'raw_minor' => 264_921_000,
            'has_native_sides' => true,
        ]));

        $this->assertSame(2, DB::table('price_snapshots')->count());
    }

    /** Do provider mitunan hamun symbol ro dashte bashan — asl e moghayese. */
    public function test_two_providers_can_quote_the_same_symbol(): void
    {
        DB::table('price_snapshots')->insert($this->snapshotRow($this->provider('tgju')));
        DB::table('price_snapshots')->insert($this->snapshotRow($this->provider('estjt')));

        $this->assertSame(2, DB::table('price_snapshots')->count());
    }

    /**
     * Gheymat ha `bigint`-and. Yek `integer` e 32-biti ru 2,147,483,647 saghf
     * dare — va sekke ye emami alan 2,753,400,000 rial ast, ya'ni ghablan
     * serriz karde bud.
     */
    public function test_big_integer_columns_hold_real_rial_prices(): void
    {
        $id = $this->provider();

        DB::table('price_snapshots')->insert($this->snapshotRow($id, [
            'symbol' => 'xau-coin-emami-irr',
            'raw_minor' => 2_753_400_000,
            'day_high_minor' => 40_000_000_000,
        ]));

        $row = DB::table('price_snapshots')->first();

        $this->assertSame(2_753_400_000, (int) $row->raw_minor);
        $this->assertSame(40_000_000_000, (int) $row->day_high_minor);
    }

    public function test_scale_travels_with_every_row(): void
    {
        $id = $this->provider();

        DB::table('price_snapshots')->insert($this->snapshotRow($id, [
            'symbol' => 'xau-oz-usd',
            'quote_currency' => 'USD',
            'scale' => 4,
            'raw_minor' => 41_401_900,
        ]));

        $row = DB::table('price_snapshots')->where('symbol', 'xau-oz-usd')->first();

        $this->assertSame(4, (int) $row->scale);
        $this->assertSame('USD', trim((string) $row->quote_currency));
    }

    public function test_spread_is_unique_per_provider_and_symbol(): void
    {
        $id = $this->provider();

        $row = [
            'provider_id' => $id, 'symbol' => '*',
            'buy_mode' => 'percent', 'buy_value' => 0,
            'sell_mode' => 'percent', 'sell_value' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('provider_spreads')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('provider_spreads')->insert($row);
    }

    /** Meghdar e spread ELAMATDAR ast — manfi bayad zakhire she. */
    public function test_spread_values_may_be_negative(): void
    {
        DB::table('provider_spreads')->insert([
            'provider_id' => $this->provider(), 'symbol' => 'xau-18k-g-irr',
            'buy_mode' => 'percent', 'buy_value' => -1.2500,
            'sell_mode' => 'amount', 'sell_value' => 50_000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('provider_spreads')->first();

        $this->assertSame(-1.25, (float) $row->buy_value);
        $this->assertSame(50000.0, (float) $row->sell_value);
    }

    /** 4 raghame ashar — ham darsad e riz ham meghdar e riz e dollari. */
    public function test_spread_keeps_four_decimal_places(): void
    {
        DB::table('provider_spreads')->insert([
            'provider_id' => $this->provider(), 'symbol' => 'xau-oz-usd',
            'buy_mode' => 'percent', 'buy_value' => 0.0125,
            'sell_mode' => 'amount', 'sell_value' => 0.0001,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('provider_spreads')->first();

        $this->assertSame(0.0125, (float) $row->buy_value);
        $this->assertSame(0.0001, (float) $row->sell_value);
    }

    /** Hazf e provider nabayad radif e yatim ja bezare. */
    public function test_deleting_a_provider_cascades_to_its_rows(): void
    {
        $id = $this->provider();

        DB::table('price_snapshots')->insert($this->snapshotRow($id));
        DB::table('provider_spreads')->insert([
            'provider_id' => $id, 'symbol' => '*',
            'buy_mode' => 'percent', 'buy_value' => 0,
            'sell_mode' => 'percent', 'sell_value' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('providers')->where('id', $id)->delete();

        $this->assertSame(0, DB::table('price_snapshots')->count());
        $this->assertSame(0, DB::table('provider_spreads')->count());
    }

    /** `price_changes` append-only ast — chand radif baraye yek symbol tabi'i-e. */
    public function test_price_changes_accepts_many_rows_for_one_symbol(): void
    {
        $id = $this->provider();

        foreach ([266_400_000, 266_500_000, 266_300_000] as $i => $minor) {
            DB::table('price_changes')->insert([
                'provider_id' => $id,
                'symbol' => 'xau-18k-g-irr',
                'side' => 'last',
                'quote_currency' => 'IRR',
                'scale' => 0,
                'raw_minor' => $minor,
                'observed_at' => now()->addSeconds($i * 15),
            ]);
        }

        $this->assertSame(3, DB::table('price_changes')->count());
    }

    /** Provider e jadid bayad bedun e tanzim e dasti kar kone. */
    public function test_provider_defaults_are_sane(): void
    {
        $row = DB::table('providers')->where('id', $this->provider())->first();

        $this->assertTrue((bool) $row->is_active);
        $this->assertSame(15, (int) $row->poll_interval_seconds);
        $this->assertSame(10, (int) $row->timeout);
        $this->assertSame(900, (int) $row->freshness_seconds);
        $this->assertSame(0, (int) $row->consecutive_failures);
        $this->assertSame('unknown', $row->health_state);
    }
}
