<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class ProdukFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('nama_produk', 'like', $search)
                    ->orWhere('kode_produk', 'like', $search);
            });
        }

        if (! empty($filters['id_kategori'])) {
            $query->where('id_kategori', (int) $filters['id_kategori']);
        }

        if (isset($filters['stok_menipis']) && filter_var($filters['stok_menipis'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('stok', '<=', 5);
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_produk', 'kode_produk', 'nama_produk', 'harga', 'stok', 'created_at'],
            'id_produk'
        );
    }
}
