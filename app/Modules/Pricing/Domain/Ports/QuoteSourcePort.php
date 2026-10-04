<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\SourceResultDto;

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
 * 5. Zamime kardan e `upstreamAt` (parse-shode az vaght e mahalli be UTC)
 *
 * ⚠️ Source khodesh radif e bayat ro rad NEMIKONE. Tazegi yek POLICY ast ke
 * be (provider, instrument) vabaste ast va tu DB tanzim mishe — masalan
 * `ons` e tgju faghat ruzane be-rooz mishe dar hali ke `geram18` har daghighe.
 * Un tasmim tu stage e `Validate` gerefte mishe ke ham `Instrument` ro dare
 * ham config e provider ro.
 *
 * Har chiz e digar (enrich، sanity، spread، zakhire) kar e pipeline ast.
 */
interface QuoteSourcePort
{
    /** Kelid e yekta — hamun `providers.key` tu DB. */
    public function key(): string;

    /**
     * Gheymat ha + meta ye hamun fetch, tu yek Dto.
     *
     * ⚠️ Yek Dto bar migardune (na faghat array) ta Source majbur nashe
     * chizi mesle `lastPayloadHash` ro be onvan e HALAT negah dare —
     * halat tu yek singleton e Swoole beyn e request ha nasht mikone.
     *
     * @throws \RuntimeException agar upstream javab nade ya payload kharab bashe.
     *                           Pipeline khodesh migire — Source nabayad saket
     *                           array e khali bargardune، chun un ba «bazaar
     *                           baste ast» eshtebah gerefte mishe.
     */
    public function fetch(): SourceResultDto;
}
