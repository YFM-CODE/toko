<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class PelangganFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('nama_pelanggan', 'like', $search)
                    ->orWhere('kota', 'like', $search)
                    ->orWhere('no_telepon', 'like', $search)
                    ->orWhere('rfid_uid', 'like', $search);
            });
        }

        if (! empty($filters['rfid_uid'])) {
            $query->where('rfid_uid', $filters['rfid_uid']);
        }

        if (! empty($filters['kota'])) {
            $query->where('kota', $filters['kota']);
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_pelanggan', 'nama_pelanggan', 'kota', 'rfid_uid', 'created_at'],
            'id_pelanggan'
        );
    }
}
