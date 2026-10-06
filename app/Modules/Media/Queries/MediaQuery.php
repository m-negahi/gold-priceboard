<?php

declare(strict_types=1);

namespace App\Modules\Media\Queries;

use App\Modules\Common\Queries\TableQuery;
use App\Modules\Media\Actions\UploadMediaAction;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Khundan-e ketabkhane: datatable-e `/panel/media` va list-e picker har do az `table()` miran
 * (hamun search, hamun safhe-bandi). `usages_count` ba withCount miad ta sotoon-e «استفاده» yek
 * query-e N+1 nashe.
 *
 * @extends TableQuery<Media>
 */
final readonly class MediaQuery extends TableQuery
{
    protected function model(): string
    {
        return Media::class;
    }

    /** @return Builder<Media> */
    protected function tableQuery(): Builder
    {
        return $this->query()->withCount('usages');
    }

    /** Radif ba shomaresh-e estefade — baraye javab-e upload/virayesh ke hamun shekl-e jadval ro mikhad. */
    public function withUsages(int $id): ?Media
    {
        return $this->query()->withCount('usages')->find($id);
    }

    /**
     * Chand radif ba id — baraye por kardan-e picker-haye yek form dar yek query.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Media>
     */
    public function byIds(array $ids): Collection
    {
        return $ids === [] ? new Collection : $this->query()->whereIn('id', $ids)->get();
    }

    /**
     * `updated_at`-e chand rasane ba id — ETag-e safhe-ye builder ba taghir-e alt/moshtaghat-e tasvir avaz mishe.
     *
     * @param  list<int>  $ids
     * @return array<int, string> id → ISO
     */
    public function updatedAtByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $out */
        $out = $this->query()->whereIn('id', $ids)->pluck('updated_at', 'id')->map(static fn (mixed $at): string => (string) $at)->all();

        return $out;
    }

    /** @return list<string> */
    protected function searchable(): array
    {
        return ['name', 'alt', 'title'];
    }

    /** @return list<string> */
    protected function sortable(): array
    {
        return ['name', 'size', 'created_at', 'usages_count'];
    }

    /** @return array<string, list<string>|null> */
    protected function filterable(): array
    {
        return ['mime' => array_keys(UploadMediaAction::ALLOWED)];
    }

    /** @return array{0: string, 1: string} */
    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }
}
