<?php

namespace App\Interfaces;

use App\Models\Pelanggan;
use Illuminate\Pagination\LengthAwarePaginator;

interface PelangganInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?Pelanggan;

    public function create(array $data): Pelanggan;

    public function update(int $id, array $data): Pelanggan;

    public function delete(int $id): bool;
}
