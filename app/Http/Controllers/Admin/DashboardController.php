<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use App\Models\LogAktivitas;
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_pendapatan' => (float) Transaksi::where('status', 'selesai')->sum('total_belanja'),
                'total_transaksi' => Transaksi::where('status', 'selesai')->count(),
                'transaksi_hari_ini' => Transaksi::where('status', 'selesai')->whereDate('tanggal', $hariIni)->count(),
                'pendapatan_hari_ini' => (float) Transaksi::where('status', 'selesai')->whereDate('tanggal', $hariIni)->sum('total_belanja'),
                'total_produk' => Produk::count(),
                'produk_stok_menipis' => Produk::where('stok', '<=', 5)->count(),
                'total_kategori' => Kategori::count(),
                'total_pelanggan' => Pelanggan::count(),
            ],
            'stok_menipis' => Produk::with('kategori')
                ->where('stok', '<=', 5)
                ->orderBy('stok')
                ->limit(8)
                ->get(),
            'log_terbaru' => LogAktivitas::with('user')
                ->orderByDesc('waktu')
                ->limit(5)
                ->get(),
        ]);
    }
}
