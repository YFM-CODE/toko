<?php

namespace App\Interfaces;

use App\Models\Kategori;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface KategoriInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function getAllWithoutPagination(): Collection;

    public function find(int $id): ?Kategori;

    public function create(array $data): Kategori;

    public function update(int $id, array $data): Kategori;

    public function delete(int $id): bool;
}
