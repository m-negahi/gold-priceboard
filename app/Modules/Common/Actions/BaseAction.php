<?php

declare(strict_types=1);

namespace App\Modules\Common\Actions;

use App\Modules\Common\Dto\Dto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Paye ye Action haye CRUD.
 *
 * Action haye CRUD in ro extend mikonan va `model()` ro implement mikonan.
 * Action haye MANTEGH e kasb-o-kar (`{Verb}{Entity}Action`) in ro extend
 * **NEMIKONAN** — un ha khodkafa-and.
 *
 * ⚠️ Bi-halat: hich state e mutable ru instance nist، pas zire
 * Swoole/Octane be onvan e singleton salem ast. Yek Builder e zakhire-shode
 * tu property beyn e request ha NASHT mikone.
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
