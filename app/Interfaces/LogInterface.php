<?php

namespace App\Interfaces;

use App\Models\LogAktivitas;
use Illuminate\Pagination\LengthAwarePaginator;

interface LogInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LogAktivitas;
}
