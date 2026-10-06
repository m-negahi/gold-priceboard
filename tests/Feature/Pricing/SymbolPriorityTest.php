<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Pricing\Application\Actions\SymbolPriorityAction;
use App\Modules\Pricing\Application\Queries\SymbolPriorityQuery;
use App\Modules\Pricing\Domain\Enums\Instrument;
use App\Modules\Pricing\Infrastructure\Persistence\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Olaviat e per-symbol (P5-07) — zakhire va khundan.
 */
final class SymbolPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $key, int $sort, bool $active = true): Provider
    {
        return Provider::query()->create([
            'key' => $key, 'driver' => $key === 'manual' ? 'manual' : 'tgju',
            'name' => $key, 'name_fa' => $key, 'is_active' => $active, 'sort' => $sort,
        ]);
    }

    private function priorityAction(): SymbolPriorityAction
    {
        return $this->app->make(SymbolPriorityAction::class);
    }

    private function priorityQuery(): SymbolPriorityQuery
    {
        return $this->app->make(SymbolPriorityQuery::class);
    }

    public function test_a_custom_chain_is_stored_in_order(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);

        $this->priorityAction()->set(Instrument::Gold18Gram, ['estjt', 'tgju']);

        self::assertSame(['xau-18k-g-irr' => ['estjt', 'tgju']], $this->priorityQuery()->customMap());
    }

    public function test_setting_again_replaces_the_previous_chain(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);

        $this->priorityAction()->set(Instrument::Gold18Gram, ['estjt', 'tgju']);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['tgju']);

        self::assertSame(['xau-18k-g-irr' => ['tgju']], $this->priorityQuery()->customMap());
    }

    public function test_an_empty_chain_resets_to_the_global_order(): void
    {
        $this->provider('tgju', 10);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['tgju']);

        $this->priorityAction()->set(Instrument::Gold18Gram, []);

        self::assertSame([], $this->priorityQuery()->customMap());
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->provider('tgju', 10);

        $this->expectException(InvalidArgumentException::class);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['tgju', 'nist']);
    }

    public function test_manual_cannot_be_ranked_it_is_always_last(): void
    {
        $this->provider('manual', 80);

        $this->expectException(InvalidArgumentException::class);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['manual']);
    }

    public function test_a_failed_set_keeps_the_previous_chain(): void
    {
        // 🔴 Atomic: validate GHABL az delete — vagarna yek kelid e ghalat
        // zanjire ye ghabli ro pak mikard va symbol bi-sar-o-seda be global
        // bar migasht.
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['estjt']);

        try {
            $this->priorityAction()->set(Instrument::Gold18Gram, ['tgju', 'nist']);
        } catch (InvalidArgumentException) {
        }

        self::assertSame(['xau-18k-g-irr' => ['estjt']], $this->priorityQuery()->customMap());
    }

    public function test_a_soft_deleted_provider_drops_out_of_the_map(): void
    {
        $tgju = $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['tgju', 'estjt']);

        $tgju->delete();

        self::assertSame(['xau-18k-g-irr' => ['estjt']], $this->priorityQuery()->customMap());
    }

    public function test_panel_rows_resolve_every_instrument_against_the_global_order(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20);
        $this->provider('manual', 80);
        $this->priorityAction()->set(Instrument::Gold18Gram, ['estjt']);

        $rows = collect($this->priorityQuery()->panelRows())->keyBy('symbol');

        self::assertCount(count(Instrument::cases()), $rows);
        self::assertTrue($rows['xau-18k-g-irr']['isCustom']);
        self::assertSame(['estjt', 'tgju', 'manual'], $rows['xau-18k-g-irr']['chain']);
        self::assertFalse($rows['xau-24k-g-irr']['isCustom']);
        self::assertSame(['tgju', 'estjt', 'manual'], $rows['xau-24k-g-irr']['chain']);
    }

    public function test_an_inactive_provider_is_not_in_the_chain(): void
    {
        $this->provider('tgju', 10);
        $this->provider('estjt', 20, active: false);

        $rows = collect($this->priorityQuery()->panelRows())->keyBy('symbol');

        self::assertSame(['tgju'], $rows['xau-18k-g-irr']['chain']);
    }
}
