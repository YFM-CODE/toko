<?php

namespace App\Http\Controllers\Owner;

use App\Handlers\LaporanAnalisisHandler;
use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private LaporanAnalisisHandler $laporanAnalisisHandler
    ) {}

    public function index(): Response
    {
        return Inertia::render('Owner/Dashboard/Index', [
            'stats' => [
                'total_pendapatan' => (float) Transaksi::where('status', 'selesai')->sum('total_belanja'),
                'total_transaksi' => Transaksi::where('status', 'selesai')->count(),
                'transaksi_batal' => Transaksi::where('status', 'batal')->count(),
                'total_pelanggan' => Pelanggan::count(),
                'total_produk' => Produk::count(),
            ],
            'laporanView' => $this->laporanAnalisisHandler->getLaporanView(),
            'analisisSubquery' => $this->laporanAnalisisHandler->getAnalisisSubquery(),
            'log_terbaru' => LogAktivitas::with('user')
                ->orderByDesc('waktu')
                ->limit(10)
                ->get(),
        ]);
    }
}
