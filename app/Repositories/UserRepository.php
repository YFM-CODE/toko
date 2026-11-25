<?php

namespace App\Repositories;

use App\Filters\UserFilter;
use App\Interfaces\UserInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserInterface
{
    public function __construct(
        private UserFilter $userFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->userFilter->apply(User::query(), $filters);

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function find(int $id): ?User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);

        return $user;
    }

    public function delete(int $id): bool
    {
        $user = User::findOrFail($id);

        return (bool) $user->delete();
    }
}
