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

    /**
     * Akharin lista ye salem e provider ha — **BEDUN e TTL**.
     *
     * 🔴 Chera tu store e dagh va na cache e ma'muli: agar Postgres ghat'
     * bashe، tick bayad EDAME bede. Bedun e in lista، pipeline nemidune
     * kodum provider ro poll kone va kol e feed motevaghef mishe — ya'ni
     * yek ghat'i ye DB tabdil mishe be yek ghat'i ye kamel e gheymat.
     *
     * @param  array<string, array<string, mixed>>  $providers
     */
    public function putProviders(array $providers): void;

    /** @return array<string, array<string, mixed>>|null */
    public function providers(): ?array;

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
