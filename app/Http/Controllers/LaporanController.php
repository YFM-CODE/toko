<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Handlers\LaporanAnalisisHandler;
use App\Interfaces\KategoriInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaporanController extends Controller
{
    public function __construct(
        private LaporanAnalisisHandler $laporanAnalisisHandler,
        private KategoriInterface $kategoriInterface
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'date_from', 'date_to', 'status', 'id_kategori', 'id_user', 'nama_tabel']);

        // Kunci laporan otomatis ke shift kasir masing-masing apabila yang login adalah role Kasir
        $isKasir = $user?->role === UserRole::Kasir;
        if ($isKasir) {
            $filters['id_user'] = $user->id_user;
        }

        $kasirList = $isKasir
            ? collect([['id_user' => $user->id_user, 'nama_lengkap' => $user->nama_lengkap, 'username' => $user->username]])
            : User::where('role', UserRole::Kasir->value)
                ->orderBy('nama_lengkap')
                ->get(['id_user', 'nama_lengkap', 'username']);

        return Inertia::render('Laporan/Index', [
            'laporanView' => $this->laporanAnalisisHandler->getLaporanView($filters),
            'laporanDetail' => $this->laporanAnalisisHandler->getLaporanDetailMultipleJoin($filters),
            'rekapPerKasir' => $this->laporanAnalisisHandler->getRekapPerKasir($filters),
            'analisisSubquery' => $this->laporanAnalisisHandler->getAnalisisSubquery(),
            'auditLog' => $this->laporanAnalisisHandler->getAuditLog($filters),
            'explainPlan' => $this->laporanAnalisisHandler->getExplainTransaksi(),
            'kategori' => $this->kategoriInterface->getAllWithoutPagination(),
            'kasirList' => $kasirList,
            'isKasirScoped' => $isKasir,
            'filters' => $filters,
        ]);
    }
}
