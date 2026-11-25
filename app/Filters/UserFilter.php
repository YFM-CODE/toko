<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class UserFilter extends BaseFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('nama_lengkap', 'like', $search)
                    ->orWhere('username', 'like', $search)
                    ->orWhere('rfid_uid', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $this->applySorting(
            $query,
            $filters,
            ['id_user', 'nama_lengkap', 'username', 'rfid_uid', 'role', 'is_active', 'created_at'],
            'id_user'
        );
    }
}
