<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Services;

/**
 * Zanjire ye olaviat e provider baraye YEK symbol (P5-07) — mahz، bedun e IO.
 *
 * Yek manba' e haghighat baraye DO masraf: feed e mo'asser (API-i ke
 * mifrushim) va «پر کردن بر اساس اولویت» e gheymat e dasti. Age har kodum
 * mantegh e khodesh ro dasht، dir ya zud az ham fasele migereftan.
 *
 * ── Gharardad ───────────────────────────────────────────────────────
 * 1. Bedun e olaviat e sefareshi = tartib e GLOBAL (`providers.sort`) —
 *    feed bedun e tanzim e admin hich taghiri nemikone.
 * 2. Sefareshi aval، ba'd BAGHI ye provider haye fa'al be tartib e global.
 *    Provider e jadid hich vaght SAKET az feed e yek symbol hazf nemishe.
 * 3. Kelid e gheyr-fa'al/nashenas tu sefareshi nadide gerefte mishe.
 * 4. `manual` hamishe AKHAR — fallback e amdi-st، na yek manba' e reghabati.
 */
final class PriorityChain
{
    public const MANUAL = 'manual';

    /**
     * @param  list<string>  $globalOrder  provider haye FA'AL be tartib e `sort`
     * @param  list<string>  $custom  olaviat e sefareshi e in symbol (shayad khali)
     * @return list<string>
     */
    public static function order(array $globalOrder, array $custom): array
    {
        $active = array_flip($globalOrder);
        $chain = [];

        foreach ([...$custom, ...$globalOrder] as $key) {
            if ($key === self::MANUAL || ! isset($active[$key]) || in_array($key, $chain, true)) {
                continue;
            }

            $chain[] = $key;
        }

        if (isset($active[self::MANUAL])) {
            $chain[] = self::MANUAL;
        }

        return $chain;
    }

    /**
     * Mogheiat e har provider tu zanjire — baraye `usort`.
     *
     * @param  list<string>  $globalOrder
     * @param  list<string>  $custom
     * @return array<string, int>
     */
    public static function rank(array $globalOrder, array $custom): array
    {
        return array_flip(self::order($globalOrder, $custom));
    }
}
