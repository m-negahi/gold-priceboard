<?php

declare(strict_types=1);

namespace App\Modules\Common\Queries;

use App\Modules\Common\Dto\TableRequestDto;
use App\Modules\Common\Dto\TableResultDto;
use App\Modules\Common\Exceptions\InvalidFilterValue;
use App\Modules\Common\Exceptions\UnknownSortColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Query-i ke manba'-e yek datatable-e (`x-panel.datatable`): search rooye sotoon-haye e'lam-shode,
 * sort faghat az whitelist, filter faghat az list-e e'lam-shode, va safhe-bandi.
 *
 * Sort ya meghdar-e filter-e na-ashna exception-e domain mide (`UnknownSortColumn`,
 * `InvalidFilterValue`), na fallback-e bi-seda: yek URL-e dast-kari-shode nabayad be ORDER BY rooye
 * sotoon-e delkhah tabdil beshe, va yek typo tu client bayad dide beshe. HTTP un ha ro yek ja
 * (bootstrap/app.php) be 422 ba shekl-e validation tabdil mikone — Query az HTTP khabar nadare.
 * Key-e filter-e na-ashna vali nadide gerefte mishe, ta URL-e kohne ba'd az hazf-e yek filter nashkane.
 *
 * @template TModel of Model
 *
 * @extends BaseQuery<TModel>
 */
abstract readonly class TableQuery extends BaseQuery
{
    /**
     * Sotoon-hayi ke search-e matn roshoon mishe (ILIKE, har kodum ya un yeki).
     *
     * @return list<string>
     */
    abstract protected function searchable(): array;

    /**
     * Sotoon-hayi ke ejaze-ye ORDER BY daran.
     *
     * @return list<string>
     */
    abstract protected function sortable(): array;

    /**
     * Filter-haye mojaz: key => list-e meghdar-haye mojaz, ya null vaghti har matni ghabool-e.
     *
     * @return array<string, list<string>|null>
     */
    protected function filterable(): array
    {
        return [];
    }

    /** @return array{0: string, 1: string} sotoon va jahat-e default */
    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }

    /**
     * E'mal-e yek filter-e mo'tabar. Default `where key = value`-e; Query-i ke filter-esh ma'ni-ye
     * dige-i dare (masalan boolean) in ro override mikone.
     *
     * @param  Builder<TModel>  $builder
     */
    protected function applyFilter(Builder $builder, string $key, string $value): void
    {
        $builder->where($key, $value);
    }

    /**
     * Builder-e paye-ye jadval — ja-ye `with()`, join ya scope-haye sabet. Default hamun `query()`.
     *
     * @return Builder<TModel>
     */
    protected function tableQuery(): Builder
    {
        return $this->query();
    }

    public function table(TableRequestDto $request): TableResultDto
    {
        [$builder, $sort, $direction] = $this->prepare($request);

        $page = $builder->paginate($request->perPage, ['*'], 'page', $request->page);

        return new TableResultDto($page, $sort, $direction);
    }

    /**
     * Builder-e amade-ye jadval bedoone safhe-bandi: search, filter va sort e'mal shode. `table()`
     * roosh paginate mikone; yek export-e CSV hamin ro stream mikone ta "hamun chizi ke mibini"
     * export beshe, na yek list-e joda ba ghavanin-e digar.
     *
     * @return array{0: Builder<TModel>, 1: string, 2: string}
     */
    protected function prepare(TableRequestDto $request): array
    {
        [$sort, $direction] = $this->resolveSort($request);
        $builder = $this->tableQuery();

        if ($request->search !== '') {
            $this->applySearch($builder, $request->search);
        }

        foreach ($this->resolveFilters($request) as $key => $value) {
            $this->applyFilter($builder, $key, $value);
        }

        // Tie-break rooye key ta safhe ha bi-sabat nashan vaghti chand radif meghdar-e barabar daran.
        $builder->orderBy($sort, $direction)->orderBy($builder->getModel()->getKeyName());

        return [$builder, $sort, $direction];
    }

    /**
     * @param  Builder<TModel>  $builder
     */
    private function applySearch(Builder $builder, string $search): void
    {
        // `%`, `_` va `\` tu matn-e karbar harf-and, na wildcard.
        $needle = '%'.addcslashes($search, '%_\\').'%';

        $builder->where(function (Builder $query) use ($needle): void {
            foreach ($this->searchable() as $column) {
                $query->orWhere($column, 'ilike', $needle);
            }
        });
    }

    /** @return array{0: string, 1: string} */
    private function resolveSort(TableRequestDto $request): array
    {
        if ($request->sort === null) {
            return $this->defaultSort();
        }

        if (! in_array($request->sort, $this->sortable(), true)) {
            throw new UnknownSortColumn($request->sort);
        }

        return [$request->sort, $request->direction];
    }

    /** @return array<string, string> */
    private function resolveFilters(TableRequestDto $request): array
    {
        $allowed = $this->filterable();
        $filters = [];

        foreach ($request->filters as $key => $value) {
            if (! array_key_exists($key, $allowed)) {
                continue;
            }

            if ($allowed[$key] !== null && ! in_array($value, $allowed[$key], true)) {
                throw new InvalidFilterValue($key, $value);
            }

            $filters[$key] = $value;
        }

        return $filters;
    }
}
