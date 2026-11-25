<?php

namespace App\Repositories;

use App\Filters\PelangganFilter;
use App\Interfaces\PelangganInterface;
use App\Models\Pelanggan;
use Illuminate\Pagination\LengthAwarePaginator;

class PelangganRepository implements PelangganInterface
{
    public function __construct(
        private PelangganFilter $pelangganFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->pelangganFilter->apply(Pelanggan::query()->withCount('transaksi'), $filters);

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function find(int $id): ?Pelanggan
    {
        return Pelanggan::with('transaksi')->findOrFail($id);
    }

    public function create(array $data): Pelanggan
    {
        return Pelanggan::create($data);
    }

    public function update(int $id, array $data): Pelanggan
    {
        $pelanggan = Pelanggan::findOrFail($id);
        $pelanggan->update($data);

        return $pelanggan;
    }

    public function delete(int $id): bool
    {
        $pelanggan = Pelanggan::findOrFail($id);

        return (bool) $pelanggan->delete();
    }
}
