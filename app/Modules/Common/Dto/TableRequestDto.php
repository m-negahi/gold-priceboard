<?php

declare(strict_types=1);

namespace App\Modules\Common\Dto;

use Illuminate\Http\Request;

/**
 * Har chizi ke yek datatable az query string mikhad, yek bar tamiz-shode: search-e trim-shode,
 * sort (ke Query ba whitelist-esh check mikone), direction-e faghat asc/desc, safhe va andaze-ye
 * safhe-ye clamp-shode, va filter-haye scalar. Ragham-e farsi ghablan tavassot-e middleware
 * ASCII shodan (ADR-0017), pas inja faghat cast mikonim.
 *
 * ⚠️ `extends Dto` HAZF shod (2026-10-06): `Dto` e in proje non-readonly ast
 * va PHP ejaze nemide class e readonly azash ers bebare — avvalin autoload
 * FATAL midad. In class hich chiz az `Dto` masraf nemikard (na `from`، na
 * `setId`)، pas mostaghel shod va immutability esh hefz shod.
 */
final readonly class TableRequestDto
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public string $search = '',
        public ?string $sort = null,
        public string $direction = 'asc',
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public array $filters = [],
    ) {}

    public static function fromRequest(Request $request, int $defaultPerPage = self::DEFAULT_PER_PAGE, int $maxPerPage = self::MAX_PER_PAGE): self
    {
        $sort = trim((string) $request->input('sort', ''));
        $direction = strtolower(trim((string) $request->input('direction', 'asc')));
        $filters = $request->input('filters');

        return new self(
            search: trim((string) $request->input('q', '')),
            sort: $sort === '' ? null : $sort,
            direction: $direction === 'desc' ? 'desc' : 'asc',
            page: max(1, self::int($request->input('page'), 1)),
            perPage: min($maxPerPage, max(1, self::int($request->input('per_page'), $defaultPerPage))),
            filters: is_array($filters) ? self::scalarFilters($filters) : [],
        );
    }

    /** Adad-e na-mo'tabar (harf, khali) be fallback bar migarde; yek URL-e kharab nabayad 500 bede. */
    private static function int(mixed $value, int $fallback): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $fallback;
    }

    /**
     * Faghat `key => string` negah dashte mishe: filter-e khali yani "hame" va hazf mishe,
     * array-e too-dar-too ma'ni nadare va rad mishe.
     *
     * @param  array<array-key, mixed>  $filters
     * @return array<string, string>
     */
    private static function scalarFilters(array $filters): array
    {
        $clean = [];

        foreach ($filters as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
