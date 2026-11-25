<?php

namespace App\Interfaces;

use App\Models\Transaksi;
use Illuminate\Pagination\LengthAwarePaginator;

interface TransaksiInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?Transaksi;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Transaksi;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Transaksi;

    public function delete(int $id): bool;
}
