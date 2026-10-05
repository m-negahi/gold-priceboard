<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

use App\Modules\Pricing\Domain\Enums\ProviderKind;
use App\Modules\Pricing\Domain\Enums\UpstreamUnit;

/**
 * Vorudi ye `ProviderAction` — chizi ke form e panel mide.
 *
 * ⚠️ Faghat soton haye TANZIMI. Soton haye salamat (`last_*`،
 * `consecutive_failures`، `health_state`، `next_poll_at`) amdan INJA NISTAN:
 * un ha ro FAGHAT `RecordProviderHealthAction` minevise. Agar form betune
 * un ha ro set kone، yek save e sade ye admin mitune vaziat e salamat ro
 * ba'd bebare va `next_poll_at` ro ghofl kone.
 */
final class ProviderInputDto
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public string $key,
        public string $driver,
        public string $name,
        public string $nameFa,
        public ProviderKind $kind = ProviderKind::Reference,
        public ?string $website = null,
        public ?string $logoPath = null,
        public bool $isActive = true,
        public bool $inComparison = true,
        public bool $onBoard = true,
        public bool $inChart = true,
        public int $timeout = 10,
        public int $pollIntervalSeconds = 15,
        public int $freshnessSeconds = 900,
        public int $hideAfterSeconds = 600,
        public UpstreamUnit $upstreamUnit = UpstreamUnit::Rial,
        public int $sort = 0,
        public array $options = [],
    ) {}

    /**
     * Sakht az array e form.
     *
     * Hich validation inja nist — validation kar e `ProviderAction` ast ta
     * har masir (form، seeder، command) hamun ghanun ha ro begire.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: trim((string) ($data['key'] ?? '')),
            driver: trim((string) ($data['driver'] ?? '')),
            name: trim((string) ($data['name'] ?? '')),
            nameFa: trim((string) ($data['name_fa'] ?? $data['nameFa'] ?? '')),
            kind: ProviderKind::tryFrom((string) ($data['kind'] ?? 'reference'))
                ?? ProviderKind::Reference,
            website: self::nullableString($data['website'] ?? null),
            logoPath: self::nullableString($data['logo_path'] ?? $data['logoPath'] ?? null),
            isActive: (bool) ($data['is_active'] ?? $data['isActive'] ?? true),
            inComparison: (bool) ($data['in_comparison'] ?? $data['inComparison'] ?? true),
            onBoard: (bool) ($data['on_board'] ?? $data['onBoard'] ?? true),
            inChart: (bool) ($data['in_chart'] ?? $data['inChart'] ?? true),
            timeout: (int) ($data['timeout'] ?? 10),
            pollIntervalSeconds: (int) ($data['poll_interval_seconds'] ?? $data['pollIntervalSeconds'] ?? 15),
            freshnessSeconds: (int) ($data['freshness_seconds'] ?? $data['freshnessSeconds'] ?? 900),
            hideAfterSeconds: (int) ($data['hide_after_seconds'] ?? $data['hideAfterSeconds'] ?? 600),
            upstreamUnit: UpstreamUnit::tryFrom((string) ($data['upstream_unit'] ?? $data['upstreamUnit'] ?? 'rial'))
                ?? UpstreamUnit::Rial,
            sort: (int) ($data['sort'] ?? 0),
            options: (array) ($data['options'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toColumns(): array
    {
        return [
            'key' => $this->key,
            'driver' => $this->driver,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'name_fa' => $this->nameFa,
            'website' => $this->website,
            'logo_path' => $this->logoPath,
            'is_active' => $this->isActive,
            'in_comparison' => $this->inComparison,
            'on_board' => $this->onBoard,
            'in_chart' => $this->inChart,
            'timeout' => $this->timeout,
            'poll_interval_seconds' => $this->pollIntervalSeconds,
            'freshness_seconds' => $this->freshnessSeconds,
            'hide_after_seconds' => $this->hideAfterSeconds,
            'upstream_unit' => $this->upstreamUnit->value,
            'sort' => $this->sort,
            'options' => $this->options,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
