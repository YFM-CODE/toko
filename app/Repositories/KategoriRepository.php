<?php

namespace App\Repositories;

use App\Filters\KategoriFilter;
use App\Interfaces\KategoriInterface;
use App\Models\Kategori;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class KategoriRepository implements KategoriInterface
{
    public function __construct(
        private KategoriFilter $kategoriFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->kategoriFilter->apply(Kategori::query()->withCount('produk'), $filters);

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function getAllWithoutPagination(): Collection
    {
        return Kategori::query()->orderBy('nama_kategori')->get();
    }

    public function find(int $id): ?Kategori
    {
        return Kategori::findOrFail($id);
    }

    public function create(array $data): Kategori
    {
        return Kategori::create($data);
    }

    public function update(int $id, array $data): Kategori
    {
        $kategori = Kategori::findOrFail($id);
        $kategori->update($data);

        return $kategori;
    }

    public function delete(int $id): bool
    {
        $kategori = Kategori::findOrFail($id);

        return (bool) $kategori->delete();
    }
}
