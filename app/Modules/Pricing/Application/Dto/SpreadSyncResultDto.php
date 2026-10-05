<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Dto;

/**
 * Natije ye `ProviderSpreadAction::sync()`.
 *
 * `cacheInvalidated` amdan jodast: bump e `spreadVersion` ru Redis ast va
 * Postgres nist، pas NEMITUNE tu hamun transaction bashe. Agar Redis ghat'
 * bashe، spread ha zakhire mishan vali cache e chart ta enghaza ye TTL
 * javab e ghadim mide. Panel bayad betune hamin ro be admin bege — «zakhire
 * shod vali hanuz cache e nemudar be-rooz nashode» hezar bar behtar az
 * sakut ast.
 */
final class SpreadSyncResultDto
{
    /**
     * @param  array<int, string>  $created
     * @param  array<int, string>  $updated
     * @param  array<int, string>  $unchanged
     * @param  array<int, string>  $removed
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $unchanged = [],
        public array $removed = [],
        public int $revisions = 0,
        public ?int $spreadVersion = null,
        public bool $cacheInvalidated = false,
    ) {}

    /** Aya chizi VAGHE'AN avaz shod? */
    public function changed(): bool
    {
        return $this->created !== [] || $this->updated !== [] || $this->removed !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'removed' => $this->removed,
            'revisions' => $this->revisions,
            'spread_version' => $this->spreadVersion,
            'cache_invalidated' => $this->cacheInvalidated,
        ];
    }
}
