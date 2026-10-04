<?php

declare(strict_types=1);

namespace App\Modules\Common\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base query providing standard read operations.
 *
 * All concrete queries extend this and implement model(). Queries are
 * read-only: NO writes, NO side effects. Custom domain reads are added as
 * methods that always start from a fresh query() builder.
 *
 * Stateless: no mutable instance state, safe as a singleton under Swoole/Octane.
 *
 * @template TModel of Model
 */
abstract readonly class BaseQuery
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Get a fresh query builder. Always returns a new instance (stateless).
     *
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /**
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCount
     * @param  string|null  $trashed  null = active only, 'with' = include trashed, 'only' = only trashed
     * @return TModel|null
     */
    public function find(int $id, array $with = [], array $withCount = [], ?string $trashed = null): ?Model
    {
        return $this->buildQuery($with, $withCount, trashed: $trashed)->find($id);
    }

    /**
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCount
     * @return TModel
     */
    public function findOrFail(int $id, array $with = [], array $withCount = [], ?string $trashed = null): Model
    {
        return $this->buildQuery($with, $withCount, trashed: $trashed)->findOrFail($id);
    }

    /**
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCount
     * @param  array<string, string>  $orderBy  ['column' => 'asc|desc']
     */
    public function paginate(
        int $perPage = 15,
        array $with = [],
        array $withCount = [],
        array $orderBy = ['created_at' => 'desc'],
        ?string $trashed = null,
    ): LengthAwarePaginator {
        $perPage = min($perPage, 100);

        return $this->buildQuery($with, $withCount, $orderBy, $trashed)->paginate($perPage);
    }

    /**
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCount
     * @param  array<string, string>  $orderBy  ['column' => 'asc|desc']
     * @return Collection<int, TModel>
     */
    public function all(
        array $with = [],
        array $withCount = [],
        array $orderBy = ['created_at' => 'desc'],
        ?string $trashed = null,
    ): Collection {
        return $this->buildQuery($with, $withCount, $orderBy, $trashed)->get();
    }

    /**
     * Build query with common options. Returns a NEW builder every time (stateless).
     *
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCount
     * @param  array<string, string>  $orderBy
     * @return Builder<TModel>
     */
    protected function buildQuery(
        array $with = [],
        array $withCount = [],
        array $orderBy = [],
        ?string $trashed = null,
    ): Builder {
        $query = $this->query();

        if ($trashed !== null && $this->supportsSoftDeletes()) {
            $query = match ($trashed) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => $query,
            };
        }

        if (! empty($with)) {
            $query = $query->with($with);
        }

        if (! empty($withCount)) {
            $query = $query->withCount($withCount);
        }

        foreach ($orderBy as $column => $direction) {
            $query = $query->orderBy($column, $direction);
        }

        return $query;
    }

    protected function supportsSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model()));
    }
}
