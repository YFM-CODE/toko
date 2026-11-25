<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class BaseFilter
{
    /**
     * @param  array<string, mixed>  $filters
     */
    abstract public function apply(Builder $query, array $filters): Builder;

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowedColumns
     */
    protected function applySorting(
        Builder $query,
        array $filters,
        array $allowedColumns,
        string $defaultColumn,
        string $defaultDirection = 'desc'
    ): Builder {
        $sortBy = isset($filters['sort_by']) && in_array($filters['sort_by'], $allowedColumns, true)
            ? (string) $filters['sort_by']
            : $defaultColumn;

        $direction = isset($filters['sort_direction']) && strtolower((string) $filters['sort_direction']) === 'asc'
            ? 'asc'
            : (isset($filters['sort_direction']) && strtolower((string) $filters['sort_direction']) === 'desc'
                ? 'desc'
                : $defaultDirection);

        return $query->orderBy($sortBy, $direction);
    }
}
