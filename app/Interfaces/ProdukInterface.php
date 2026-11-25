<?php

namespace App\Interfaces;

use App\Models\Produk;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProdukInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?Produk;

    public function create(array $data): Produk;

    public function update(int $id, array $data): Produk;

    public function delete(int $id): bool;
}
