<?php

declare(strict_types=1);

namespace App\Modules\Common\Dto;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Natije-ye yek safhe az datatable: paginator be alave-ye sort-i ke vaghean e'mal shod — chon
 * request momkene sort nadade bashe va client bayad bedoone rooye kodum sotoon neshaste.
 */
final readonly class TableResultDto extends Dto
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     */
    public function __construct(
        public LengthAwarePaginator $page,
        public string $sort,
        public string $direction,
    ) {}
}
