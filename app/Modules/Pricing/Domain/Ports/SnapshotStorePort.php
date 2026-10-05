<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\SnapshotDto;

/**
 * Zakhire ye payedar e AKHARIN gheymat (Postgres).
 *
 * ⚠️ Inja tarikhche nist — faghat yek radif be ezaye har
 * (provider, instrument, side). Tarikhche tu Influx ast.
 *
 * Naghsh e asli: **backfill e Redis**. Agar Redis khali/down bashe، tablo az
 * inja khunde mishe va site zende mimune.
 */
interface SnapshotStorePort
{
    /**
     * Snapshot haye provider haye FA'AL، keyed ba `SnapshotDto::key()`.
     *
     * 🔴 «Fa'al» bakhshi az contract ast، na yek jozyat e adapter. Masir e
     * Redis tablo ro har tick bazneveshte mikone، pas provider e khamoosh
     * khod-be-khod mire؛ agar in method un ro bar gardund، masir e fallback
     * chizi neshun mide ke masir e asli neshun nemide.
     *
     * Pipeline in ro GHABL az har tick mikhune: ham baraye enrich (dayOpen،
     * direction) ham baraye `PriceSanity` (gheymat e ghabli + outlier e
     * moallagh).
     *
     * @return array<string, SnapshotDto>
     */
    public function all(): array;

    /**
     * Upsert e YEK-JA.
     *
     * ⚠️ Bayad yek dastur e `INSERT … ON CONFLICT` bashe، na halghe-i az
     * `updateOrCreate`. Ba ~50 radif، halghe ya'ni 50 round-trip tu har tick
     * (5,760 bar tu rooz) bi-hich fayede.
     *
     * @param  array<int, SnapshotDto>  $snapshots
     */
    public function save(array $snapshots): void;

    /**
     * Sabt e taghir tu daftarche ye append-only.
     *
     * FAGHAT snapshot hayi ke gheymateshun vaghe'an avaz shode bayad pass
     * dade shan — na hame ye tick. Tala shayad 200-2000 bar tu rooz harekat
     * kone، na 5,760 bar.
     *
     * @param  array<int, SnapshotDto>  $changed
     */
    public function appendChanges(array $changed): void;
}
