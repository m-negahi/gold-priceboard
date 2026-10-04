<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;

/**
 * Store e DAGH — chizi ke har bazdid-konande mikhune (Redis e `redis-prices`).
 *
 * ── 🔴 Do ghanun ─────────────────────────────────────────────────────
 * 1. Kelid haye gheymat **bedun e TTL** neveshte mishan.
 * 2. Ru instance e `redis-prices` (ba `noeviction`)، na Redis e omumi.
 *
 * Redis e omumi `allkeys-lru` ast: tu feshar e memory HAR kelidi ro mindaze —
 * shamel e gheymat ha — dar hali ke khodesh ro salem gozaresh mide. Natije:
 * tablo bedun e hich error khali mishe.
 */
interface HotCachePort
{
    /**
     * Kol e tablo ba yek amaliat (na N ta GET).
     *
     * @return array<string, SnapshotDto>
     */
    public function board(): array;

    /**
     * Neveshtan e kol e tablo + bump e version، hameh tu YEK pipeline.
     *
     * @param  array<int, SnapshotDto>  $snapshots
     */
    public function putBoard(array $snapshots): void;

    /**
     * Version e tablo — ba har neveshtan yeki ziad mishe.
     * Masraf: ETag e `/api/board.json` ta poll e bi-taghir 304 bar gardune.
     */
    public function boardVersion(): int;

    public function putHealth(ProviderHealthDto $health): void;

    /** @return array<string, ProviderHealthDto> */
    public function health(): array;

    /**
     * Agar `false` bashe، caller bayad be Postgres fallback kone — va tick
     * bayad bedun e lock edame bede (lock guard e performance ast، na
     * correctness: hame ye sink ha idempotent-and).
     */
    public function isAvailable(): bool;
}
