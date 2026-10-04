<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Domain\Enums\Side;
use App\Modules\Pricing\Infrastructure\Sources\MockQuoteSource;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FrozenClock;

final class MockQuoteSourceTest extends TestCase
{
    /** @return array<string, RawQuoteDto> */
    private function quotes(FrozenClock $clock): array
    {
        $byKey = [];

        foreach ((new MockQuoteSource($clock))->fetch()->quotes as $quote) {
            $byKey[$quote->instrument->value.':'.$quote->side->value] = $quote;
        }

        return $byKey;
    }

    public function test_covers_every_instrument(): void
    {
        $quotes = $this->quotes(new FrozenClock);

        // 15 instrument + yeki ezafe chun abshode ham bid dare ham ask.
        self::assertCount(count(Instrument::cases()) + 1, $quotes);
    }

    /**
     * 🔴 Determinist budan asli tarin khasiat ast.
     *
     * Rah e ma'mul (akharin gheymat tu Redis + delta ye random) ya'ni Source
     * halat dare va ghabl e test bedun e Redis nist. Inja gheymat yek TABE ye
     * MAHZ az zaman ast.
     */
    public function test_same_tick_always_produces_the_same_price(): void
    {
        $first = $this->quotes(new FrozenClock('2026-10-04T12:21:30Z'));
        $second = $this->quotes(new FrozenClock('2026-10-04T12:21:30Z'));

        foreach ($first as $key => $quote) {
            self::assertSame(
                $quote->price->minor,
                $second[$key]->price->minor,
                "{$key}: hamun tick bayad hamun gheymat ro bede.",
            );
        }
    }

    /** Hame ye lahze haye dakhel e yek panjere ye 15 saniye-i yek tick-and. */
    public function test_prices_are_stable_within_a_tick_window(): void
    {
        $a = $this->quotes(new FrozenClock('2026-10-04T12:21:30Z'));
        $b = $this->quotes(new FrozenClock('2026-10-04T12:21:44Z'));

        self::assertSame(
            $a['xau-18k-g-irr:last']->price->minor,
            $b['xau-18k-g-irr:last']->price->minor,
        );
    }

    public function test_price_moves_between_ticks(): void
    {
        $a = $this->quotes(new FrozenClock('2026-10-04T12:21:30Z'));
        $b = $this->quotes(new FrozenClock('2026-10-04T12:21:45Z'));

        self::assertNotSame(
            $a['xau-18k-g-irr:last']->price->minor,
            $b['xau-18k-g-irr:last']->price->minor,
        );
    }

    /**
     * Harekat bayad kuchik bashe — vagarna `PriceSanity` tu dev har tick
     * radesh mikone va kasi nemifahme chera tablo khali-e.
     */
    public function test_movement_stays_within_a_believable_band(): void
    {
        $clock = new FrozenClock('2026-10-04T00:00:00Z');
        $seed = 266_400_000;
        $previous = null;

        for ($i = 0; $i < 240; $i++) {
            $minor = $this->quotes($clock)['xau-18k-g-irr:last']->price->minor;

            // Nabayad bishtar az ~2% az noghte ye shoru' dur beshe.
            self::assertEqualsWithDelta($seed, $minor, $seed * 0.02);

            if ($previous !== null) {
                // ...va harekat e tick-be-tick bayad kheyli kuchik bashe.
                self::assertEqualsWithDelta($previous, $minor, $previous * 0.01);
            }

            $previous = $minor;
            $clock->advance(15);
        }
    }

    /** Symbol ha nabayad ham-faz bashan — vagarna tablo masnu'i be nazar mirese. */
    public function test_symbols_do_not_move_in_lockstep(): void
    {
        $a = $this->quotes(new FrozenClock('2026-10-04T12:21:30Z'));
        $b = $this->quotes(new FrozenClock('2026-10-04T12:21:45Z'));

        $gold = $b['xau-18k-g-irr:last']->price->minor <=> $a['xau-18k-g-irr:last']->price->minor;
        $directions = [];

        foreach (array_keys($a) as $key) {
            $directions[] = $b[$key]->price->minor <=> $a[$key]->price->minor;
        }

        self::assertContains($gold, $directions);
        self::assertGreaterThan(1, count(array_unique($directions)), 'hame bayad ba ham bala-paeen naran.');
    }

    /** Mock ham bayad jofte vaghei ro taghlid kone — vagarna `is_derived` tu dev test nemishe. */
    public function test_melted_gold_has_a_real_bid_ask_pair(): void
    {
        $quotes = $this->quotes(new FrozenClock);

        self::assertArrayHasKey('xau-melted-g-irr:bid', $quotes);
        self::assertArrayHasKey('xau-melted-g-irr:ask', $quotes);

        $bid = $quotes['xau-melted-g-irr:bid']->price;
        $ask = $quotes['xau-melted-g-irr:ask']->price;

        self::assertTrue($ask->isGreaterThan($bid));
    }

    public function test_other_instruments_only_have_a_last_side(): void
    {
        foreach ($this->quotes(new FrozenClock) as $quote) {
            if ($quote->instrument->hasNativeSides()) {
                continue;
            }

            self::assertSame(Side::Last, $quote->side);
        }
    }

    public function test_quotes_use_the_right_currency_and_are_never_stale(): void
    {
        $clock = new FrozenClock;

        foreach ($this->quotes($clock) as $quote) {
            self::assertSame($quote->instrument->quote(), $quote->price->quote);
            self::assertNotNull($quote->upstreamAt);
            self::assertTrue($quote->price->isPositive());
        }
    }

    public function test_seeds_can_be_overridden(): void
    {
        $clock = new FrozenClock;
        $source = new MockQuoteSource($clock, ['xau-18k-g-irr' => 1_000_000_000]);

        $gold = array_values(array_filter(
            $source->fetch()->quotes,
            static fn (RawQuoteDto $q): bool => $q->instrument === Instrument::Gold18Gram,
        ))[0];

        self::assertEqualsWithDelta(1_000_000_000, $gold->price->minor, 20_000_000);
    }

    public function test_exposes_its_key(): void
    {
        self::assertSame('mock', (new MockQuoteSource(new FrozenClock))->key());
    }
}
