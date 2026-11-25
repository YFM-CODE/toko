<?php

namespace App\Repositories;

use App\Filters\ProdukFilter;
use App\Interfaces\ProdukInterface;
use App\Models\Produk;
use Illuminate\Pagination\LengthAwarePaginator;

class ProdukRepository implements ProdukInterface
{
    public function __construct(
        private ProdukFilter $produkFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->produkFilter->apply(Produk::with('kategori'), $filters);

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function find(int $id): ?Produk
    {
        return Produk::with('kategori')->findOrFail($id);
    }

    public function create(array $data): Produk
    {
        return Produk::create($data);
    }

    public function update(int $id, array $data): Produk
    {
        $produk = Produk::findOrFail($id);
        $produk->update($data);

        return $produk;
    }

    public function delete(int $id): bool
    {
        $produk = Produk::findOrFail($id);

        return (bool) $produk->delete();
    }
}
