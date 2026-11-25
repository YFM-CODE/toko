<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $hariIni = now()->toDateString();

        return Inertia::render('Kasir/Dashboard/Index', [
            'stats' => [
                'transaksi_hari_ini' => Transaksi::where('status', 'selesai')
                    ->whereDate('tanggal', $hariIni)
                    ->count(),
                'pendapatan_hari_ini' => (float) Transaksi::where('status', 'selesai')
                    ->whereDate('tanggal', $hariIni)
                    ->sum('total_belanja'),
                'total_pelanggan' => Pelanggan::count(),
                'produk_tersedia' => Produk::where('stok', '>', 0)->count(),
            ],
            'transaksi_terbaru' => Transaksi::with(['pelanggan', 'detail.produk'])
                ->whereDate('tanggal', $hariIni)
                ->latest('id_transaksi')
                ->limit(8)
                ->get(),
        ]);
    }
}
