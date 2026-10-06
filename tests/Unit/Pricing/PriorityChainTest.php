<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Domain\Services\PriorityChain;
use PHPUnit\Framework\TestCase;

/**
 * Zanjire ye olaviat e provider baraye YEK symbol (P5-07) — mantegh e mahz.
 *
 * Gharardad:
 *  - bedun e olaviat e sefareshi = tartib e GLOBAL (`sort`) — feed e
 *    forookhte-shode bedun e tanzim e admin avaz nemishe.
 *  - sefareshi aval، ba'd baghi ye provider haye fa'al be tartib e global —
 *    provider e jadid hich vaght SAKET az feed e yek symbol hazf nemishe.
 *  - `manual` hamishe AKHAR (fallback e amdi).
 */
final class PriorityChainTest extends TestCase
{
    public function test_without_a_custom_chain_the_global_order_is_kept(): void
    {
        self::assertSame(['tgju', 'estjt', 'manual'], PriorityChain::order(['tgju', 'estjt', 'manual'], []));
    }

    public function test_a_custom_chain_comes_first_then_the_rest_in_global_order(): void
    {
        self::assertSame(
            ['estjt', 'tgju', 'wallgold', 'manual'],
            PriorityChain::order(['tgju', 'wallgold', 'estjt', 'manual'], ['estjt', 'tgju']),
        );
    }

    public function test_inactive_or_unknown_keys_in_the_custom_chain_are_ignored(): void
    {
        // `milli` khamoosh/hazf shode — nabayad tu zanjire bemune.
        self::assertSame(['estjt', 'tgju'], PriorityChain::order(['tgju', 'estjt'], ['milli', 'estjt']));
    }

    public function test_manual_is_always_last_even_if_listed_first(): void
    {
        self::assertSame(['tgju', 'estjt', 'manual'], PriorityChain::order(['manual', 'tgju', 'estjt'], ['manual', 'tgju']));
    }

    public function test_duplicates_are_collapsed(): void
    {
        self::assertSame(['estjt', 'tgju'], PriorityChain::order(['tgju', 'estjt'], ['estjt', 'estjt']));
    }

    public function test_rank_gives_a_comparable_position(): void
    {
        $rank = PriorityChain::rank(['tgju', 'estjt', 'manual'], ['estjt']);

        self::assertSame(['estjt' => 0, 'tgju' => 1, 'manual' => 2], $rank);
    }
}
