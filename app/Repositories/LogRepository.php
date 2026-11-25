<?php

namespace App\Repositories;

use App\Filters\LogFilter;
use App\Interfaces\LogInterface;
use App\Models\LogAktivitas;
use Illuminate\Pagination\LengthAwarePaginator;

class LogRepository implements LogInterface
{
    public function __construct(
        private LogFilter $logFilter
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->logFilter->apply(LogAktivitas::with('user'), $filters);

        return $query->paginate((int) ($filters['per_page'] ?? $perPage));
    }

    public function create(array $data): LogAktivitas
    {
        return LogAktivitas::create($data);
    }
}
