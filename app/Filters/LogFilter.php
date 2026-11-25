<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class LogFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('aktivitas', 'like', $search)
                    ->orWhere('nama_tabel', 'like', $search)
                    ->orWhere('data_lama', 'like', $search)
                    ->orWhere('data_baru', 'like', $search);
            });
        }

        if (! empty($filters['nama_tabel'])) {
            $query->where('nama_tabel', $filters['nama_tabel']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('waktu', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('waktu', '<=', $filters['date_to']);
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_log', 'nama_tabel', 'aktivitas', 'waktu'],
            'waktu'
        );
    }
}
