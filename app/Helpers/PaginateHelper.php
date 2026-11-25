<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginateHelper
{
    /**
     * Memformat metadata pagination dari Laravel Paginator
     * agar mudah dikonsumsi oleh komponen tabel Vue.
     */
    public static function getPaginate(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => $paginator->linkCollection()->toArray(),
        ];
    }
}
