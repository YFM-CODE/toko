<?php

namespace App\Repositories;

use App\Filters\TransaksiFilter;
use App\Interfaces\TransaksiInterface;
use App\Models\Transaksi;
use Illuminate\Pagination\LengthAwarePaginator;

class TransaksiRepository implements TransaksiInterface
{
    public function __construct(
        private TransaksiFilter $transaksiFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->transaksiFilter->apply(
            Transaksi::with(['pelanggan', 'kasir', 'detail.produk']),
            $filters
        );

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function find(int $id): ?Transaksi
    {
        return Transaksi::with(['pelanggan', 'kasir', 'detail.produk.kategori'])->findOrFail($id);
    }

    public function create(array $data): Transaksi
    {
        $transaksi = Transaksi::create($data);

        return $transaksi->load(['pelanggan', 'kasir', 'detail.produk']);
    }

    public function update(int $id, array $data): Transaksi
    {
        $transaksi = Transaksi::findOrFail($id);
        $transaksi->update($data);

        return $transaksi->fresh(['pelanggan', 'kasir', 'detail.produk']);
    }

    public function delete(int $id): bool
    {
        $transaksi = Transaksi::findOrFail($id);

        return (bool) $transaksi->delete();
    }
}
