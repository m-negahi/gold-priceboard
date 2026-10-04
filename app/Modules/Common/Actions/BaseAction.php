<?php

declare(strict_types=1);

namespace App\Modules\Common\Actions;

use App\Modules\Common\Dto\Dto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Base action providing standard CRUD write operations.
 *
 * All concrete CRUD actions extend this and implement model(). Business-logic
 * actions ({Verb}{Entity}Action) do NOT extend this — they are standalone.
 *
 * Stateless: no mutable instance state, safe as a singleton under Swoole/Octane.
 *
 * @template TModel of Model
 */
abstract readonly class BaseAction
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return TModel
     */
    public function create(Dto $dto): Model
    {
        $modelClass = $this->model();

        return $modelClass::query()->create($dto->toArrayWithoutNulls());
    }

    /**
     * @return TModel
     */
    public function update(Dto $dto): Model
    {
        $id = $dto->getId();
        if ($id === null) {
            throw new InvalidArgumentException('DTO must have an ID for update');
        }

        $modelClass = $this->model();

        /** @var TModel $model */
        $model = $modelClass::withTrashed()->findOrFail($id);
        $model->fill($dto->exceptWithoutNulls(['id']));
        $model->save();

        return $model->refresh();
    }

    public function delete(int $id): bool
    {
        $modelClass = $this->model();

        return $modelClass::withTrashed()->findOrFail($id)->delete();
    }

    public function restore(int $id): bool
    {
        $modelClass = $this->model();

        return $modelClass::query()->onlyTrashed()->findOrFail($id)->restore();
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkDelete(array $ids): int
    {
        $modelClass = $this->model();
        $models = $modelClass::query()->whereIn('id', $ids)->get();

        $count = 0;
        foreach ($models as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkRestore(array $ids): int
    {
        $modelClass = $this->model();
        $models = $modelClass::query()->onlyTrashed()->whereIn('id', $ids)->get();

        $count = 0;
        foreach ($models as $model) {
            if ($model->restore()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
