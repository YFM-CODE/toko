<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class TransaksiFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('kode_transaksi', 'like', $search)
                    ->orWhereHas('pelanggan', fn (Builder $p) => $p->where('nama_pelanggan', 'like', $search));
            });
        }

        if (! empty($filters['tanggal'])) {
            $query->whereDate('tanggal', $filters['tanggal']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('tanggal', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('tanggal', '<=', $filters['date_to']);
        }

        if (! empty($filters['id_pelanggan'])) {
            $query->where('id_pelanggan', (int) $filters['id_pelanggan']);
        }

        if (! empty($filters['kasir_or_pending_id'])) {
            $kasirId = (int) $filters['kasir_or_pending_id'];
            $query->where(function (Builder $q) use ($kasirId): void {
                $q->where('id_user', $kasirId)
                    ->orWhere('status', 'pending');
            });
        } elseif (! empty($filters['id_user'])) {
            $query->where('id_user', (int) $filters['id_user']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_transaksi', 'kode_transaksi', 'tanggal', 'total_belanja', 'status'],
            'id_transaksi'
        );
    }
}
