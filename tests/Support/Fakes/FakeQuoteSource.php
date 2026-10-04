<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Modules\Pricing\Application\Dto\RawQuoteDto;
use App\Modules\Pricing\Domain\Ports\QuoteSourcePort;
use RuntimeException;

/** Source e ghabl e barname-rizi — mitune gheymat bede ya amdan beterke. */
final class FakeQuoteSource implements QuoteSourcePort
{
    private int $fetchCount = 0;

    /** @param array<int, RawQuoteDto> $quotes */
    public function __construct(
        private readonly string $key = 'fake',
        private array $quotes = [],
        private ?string $throwMessage = null,
        private readonly ?string $payloadHash = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function fetch(): array
    {
        $this->fetchCount++;

        if ($this->throwMessage !== null) {
            throw new RuntimeException($this->throwMessage);
        }

        return $this->quotes;
    }

    public function lastPayloadHash(): ?string
    {
        return $this->payloadHash;
    }

    /** @param array<int, RawQuoteDto> $quotes */
    public function returns(array $quotes): self
    {
        $this->quotes = $quotes;
        $this->throwMessage = null;

        return $this;
    }

    public function fails(string $message = 'upstream javab nadad'): self
    {
        $this->throwMessage = $message;

        return $this;
    }

    public function fetchCount(): int
    {
        return $this->fetchCount;
    }
}
