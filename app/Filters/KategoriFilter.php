<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class KategoriFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('nama_kategori', 'like', $search)
                    ->orWhere('deskripsi', 'like', $search);
            });
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_kategori', 'nama_kategori', 'created_at'],
            'id_kategori'
        );
    }
}
