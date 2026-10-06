<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Ports\HotCachePort;
use Throwable;

/**
 * Akharin gozaresh haye tick baraye `TickMonitor` (P3-09).
 *
 * Manba': daftarche ye gozara ye hot cache (50 ta ye akhar). Redis ghat'
 * bashe lista khali-e — monitor khodesh neshane ye ghat'i ro az hamin
 * khali budan mifahme.
 */
final readonly class TickReportQuery
{
    public function __construct(private HotCachePort $cache) {}

    /** @return array<int, array<string, mixed>> jadid tarin aval */
    public function recent(int $limit = 20): array
    {
        try {
            return $this->cache->tickReports($limit);
        } catch (Throwable) {
            return [];
        }
    }
}
