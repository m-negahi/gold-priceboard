<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\UpstreamCallDto;
use App\Modules\Pricing\Domain\Ports\UpstreamLogPort;

/**
 * Fake ye `UpstreamLogPort` — hamun gharardad e adapter e Redis:
 * jadid tarin aval، push hich vaght throw nemikone.
 */
final class InMemoryUpstreamCallLog implements UpstreamLogPort
{
    /** @var array<int, UpstreamCallDto> jadid tarin aval */
    public array $calls = [];

    public function push(UpstreamCallDto $call): void
    {
        array_unshift($this->calls, $call);
    }

    public function recent(int $limit = 100): array
    {
        return array_slice($this->calls, 0, max(0, $limit));
    }
}
