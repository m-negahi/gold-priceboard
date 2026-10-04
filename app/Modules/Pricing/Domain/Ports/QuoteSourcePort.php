<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;

/**
 * Yek manba' e gheymat e biruni (tgju، estjt، va ba'dan platform ha).
 *
 * ── 🔴 Yek Source FAGHAT motarjem ast ────────────────────────────────
 * Vorudi: HTTP. Khoruji: `RawQuoteDto[]`.
 * Hich cache، hich DB، hich enrich، hich spread، hich etela' az tick time.
 *
 * Dalil: tu projeh ye marja' (`hg_website`) driver khodesh Redis mikhund va
 * minevesht، va natije in bud ke ghabl e test bedun e Redis nabud va az
 * «motarjem» be «store» tabdil shode bud.
 *
 * ── Mas'uliat haye vaghei ye yek Source ──────────────────────────────
 * 1. Gereftan e payload (ba User-Agent / method / timeout e khodesh)
 * 2. Parse (ba policy e separator e KHODESH — bebin `NumberParser`)
 * 3. Map kardan e kelid/label e upstream be `Instrument` + `Side`
 * 4. Yeksan-sazi ye vahed (Toman -> Rial) — **per-instrument**، na per-provider
 * 5. Rad kardan e radif haye bayat ba `upstreamAt`
 *
 * Har chiz e digar (enrich، sanity، spread، zakhire) kar e pipeline ast.
 */
interface QuoteSourcePort
{
    /** Kelid e yekta — hamun `providers.key` tu DB. */
    public function key(): string;

    /**
     * @return array<int, RawQuoteDto>
     *
     * @throws \RuntimeException agar upstream javab nade ya payload kharab bashe.
     *                           Pipeline khodesh migire — Source nabayad saket
     *                           array e khali bargardune، chun un ba «bazaar
     *                           baste ast» eshtebah gerefte mishe.
     */
    public function fetch(): array;

    /**
     * Hash e payload e kham e akharin `fetch()`.
     *
     * Baraye provider hayi ke timestamp NEMIDAN (estjt): agar hash baraye
     * chand daghighe sabet bemune va bazaar baz bashe، ya'ni feed yakh zade.
     * `null` = in Source hash nemide.
     */
    public function lastPayloadHash(): ?string;
}
