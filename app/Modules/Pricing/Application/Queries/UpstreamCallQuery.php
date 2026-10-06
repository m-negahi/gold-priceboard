<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Queries;

use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;
use Throwable;

/**
 * Akharin call haye MA be provider ha baraye safhe ye monitor (P4-14).
 *
 * Khoruji array e view-ready ast (hamun olgu ye `TickReportQuery`) —
 * Redis ghat' bashe lista khali-e va monitor khodesh az khali budan
 * ghat'i ro mifahme.
 */
final readonly class UpstreamCallQuery
{
    public function __construct(private UpstreamLogPort $log) {}

    /** @return array<int, array<string, mixed>> jadid tarin aval */
    public function recent(int $limit = 100): array
    {
        try {
            $calls = $this->log->recent($limit);
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn ($call): array => $call->toArray(), $calls);
    }
}
