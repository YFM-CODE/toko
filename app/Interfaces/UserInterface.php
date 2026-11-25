<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?User;

    public function create(array $data): User;

    public function update(int $id, array $data): User;

    public function delete(int $id): bool;
}
