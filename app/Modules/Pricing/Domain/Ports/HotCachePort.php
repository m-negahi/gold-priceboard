<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\ProviderHealthDto;
use App\Modules\Pricing\Application\Dto\SnapshotDto;

/**
 * Store e DAGH — chizi ke har bazdid-konande mikhune (Redis e vahed, DB2).
 *
 * ── 🔴 Do ghanun ─────────────────────────────────────────────────────
 * 1. Kelid haye gheymat **bedun e TTL** neveshte mishan.
 * 2. Ru DB2 e Redis e vahed (ba `noeviction`)، joda az connection e default/cache.
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
     * 🔴 **JAYGOZIN mikone، merge NEMIKONE.** Adapter yek `SET` ru yek kelid
     * mizane، pas har chizi ke tu `$snapshots` nabashe az tablo MIRE.
     *
     * Ya'ni caller bayad majmu'e ye KAMEL ro bede — «akharin-ma'lum e hame»
     * plus «chizi ke in tick umad» — na faghat chizi ke in tick umad.
     * Vagarna shab ha (bazaar e baste، aksar e quote ha `stale`) tablo AB
     * MIRE. (`PersistStage::wholeBoard()`.)
     *
     * ⚠️ Yek `$snapshots` e KHALI hichi ro pak nemikone (bar migarde) —
     * vagarna yek tick e khali tablo ro khamoosh mikard. Radif haye kheyli
     * ghadimi ru READ makhfi mishan (`hide_after_seconds` tu `BoardQuery`).
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

    /**
     * Version e spread — ba HAR taghir e spread yeki ziad mishe.
     *
     * 🔴 In adad tu HAME ye kelid e cache e gheymat-dar va har ETag miad.
     * Bedun esh، ba'd az taghir e spread، tablo va chart ta enghaza ye tulani
     * tarin TTL do javab e MOTEFAVET midan — va karbar un ro be onvan e bug
     * gozaresh mikone. Yek `INCR` har se (tablo، chart، API) ro ba ham batel
     * mikone.
     */
    public function spreadVersion(): int;

    public function bumpSpreadVersion(): int;

    /**
     * Zarabān e nevisande — «man ALAN tablo ro neveshtam».
     *
     * 🔴 Chera lazem ast: bad tarin nou' e ghat'i e connection e `prices` un ast ke
     * SALEM be nazar mirese. Vaghti memory por she (`noeviction`)، Redis
     * write ha ro ba `OOM command not allowed` rad mikone **vali read ha ro
     * kamel javab mide** va `PING` ham salem ast. Pas `isAvailable()` `true`
     * mide، `board()` data mide، va reader HICH rahi nadare befahme ke tablo
     * YAKH zade.
     *
     * In zarabān hamun rah ast: khodesh yek WRITE ast، pas tu halat e OOM
     * be-rooz NEMISHE. Reader faghat `heartbeatAt()` ro ba `now` moghayese
     * mikone.
     *
     * Bonus: agar khod e `price-worker` morde bashe ham hamin ro mifahmim —
     * «hich kas gheymat nemineviseh» daghighan chizi-e ke karbar bayad
     * bedune.
     */
    public function putHeartbeat(int $at): void;

    /** Unix timestamp e akharin neveshtan e movafagh، ya `null`. */
    public function heartbeatAt(): ?int;

    public function putHealth(ProviderHealthDto $health): void;

    /** @return array<string, ProviderHealthDto> */
    public function health(): array;

    /**
     * Agar `false` bashe، caller bayad be Postgres fallback kone — va tick
     * bayad bedun e lock edame bede (lock guard e performance ast، na
     * correctness: hame ye sink ha idempotent-and).
     */
    public function isAvailable(): bool;

    /**
     * Sabt e yek gozaresh e tick baraye `TickMonitor` e panel (P3-09).
     *
     * 🔴 Gharardad: jadid tarin AVVAL، saghf e sabet (adapter LTRIM mizane)
     * — in yek daftarche ye monitoring e gozara-st، na audit (un
     * `price_changes`/log e `pricing` ast). Gom shodan esh zarar e dadei
     * nadare، pas adapter nabayad hich vaght throw kone... na، THROW mojaz
     * ast (caller izole mikone) vali nabayad tick ro bokoshe.
     *
     * @param  array<string, mixed>  $report  khoruji ye `TickReportDto::toArray()`
     */
    public function putTickReport(array $report): void;

    /**
     * Jadid tarin gozaresh ha — jadid tarin aval.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tickReports(int $limit = 20): array;
}
