<?php

namespace App\Http\Controllers;

use App\Helpers\PaginateHelper;
use App\Interfaces\LogInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function __construct(
        private LogInterface $logInterface
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'nama_tabel', 'date_from', 'date_to', 'sort_by', 'sort_direction', 'per_page']);
        $logs = $this->logInterface->getAll($filters);

        return Inertia::render('AuditLog/Index', [
            'logs' => $logs,
            'pagination' => PaginateHelper::getPaginate($logs),
            'filters' => $filters,
        ]);
    }
}
