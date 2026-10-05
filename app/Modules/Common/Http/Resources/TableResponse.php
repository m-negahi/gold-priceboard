<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Resources;

use App\Modules\Common\Dto\TableResultDto;
use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * Envelope-e yekdast-e har datatable: `{ data: [...], meta: { total, page, per_page, last_page,
 * sort, direction } }`. Controller faghat mige har radif chetor be array tabdil beshe.
 */
final readonly class TableResponse implements Responsable
{
    /**
     * @param  Closure(mixed): array<string, mixed>  $row
     */
    public function __construct(
        private TableResultDto $result,
        private Closure $row,
    ) {}

    public function toResponse($request): JsonResponse
    {
        $page = $this->result->page;

        return new JsonResponse([
            'data' => array_map($this->row, $page->items()),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'sort' => $this->result->sort,
                'direction' => $this->result->direction,
            ],
        ]);
    }
}
