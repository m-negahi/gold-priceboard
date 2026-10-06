<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Ports;

use App\Modules\Pricing\Application\Dto\UpstreamCallDto;

/**
 * Daftarche ye call haye MA be provider haye gheymat (P4-14).
 *
 * `/dashboard/monitor/api` faghat call e MOSHTARI be ma ro neshun midad؛
 * baraye debug e khata ye provider (tgju timeout mikhore? estjt 403 mide?)
 * hich tarikh-che-i nabud. In port un surakh ro por mikone.
 *
 * ── Gharardad ────────────────────────────────────────────────────────
 * - `push` HARGEZ throw nemikone — monitoring e shekaste nabayad tick ro
 *   bokoshe. Adapter har khata ro saket ghurt mide (hamun policy e
 *   `ApiRequestLog`).
 * - `recent` ru khata ye store، array e KHALI bar migardune — monitor
 *   khodesh az khali budan ghat'i ro mifahme.
 * - Monitoring e gozara-st: gom shodan e entry ha zarar nadare.
 */
interface UpstreamLogPort
{
    public function push(UpstreamCallDto $call): void;

    /** @return array<int, UpstreamCallDto> jadid tarin aval */
    public function recent(int $limit = 100): array;

    /**
     * Yek call ba id (safhe ye jozyat — P4-20). `null` ya'ni az daftarche
     * birun rafte (trim) ya hich vaght nabude.
     */
    public function find(string $id): ?UpstreamCallDto;
}
