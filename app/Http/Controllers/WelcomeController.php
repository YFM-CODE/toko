<?php

namespace App\Http\Controllers;

use App\Interfaces\KategoriInterface;
use App\Interfaces\ProdukInterface;
use App\Models\Pelanggan;
use App\Models\Produk;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __construct(
        private ProdukInterface $produkInterface,
        private KategoriInterface $kategoriInterface
    ) {}

    public function index(): Response
    {
        $kategoriList = $this->kategoriInterface->getAllWithoutPagination()
            ->map(fn ($k): array => [
                'id_kategori' => $k->id_kategori,
                'nama_kategori' => $k->nama_kategori,
            ]);

        $produkUnggulan = Produk::with('kategori:id_kategori,nama_kategori')
            ->where('stok', '>', 0)
            ->orderByDesc('id_produk')
            ->limit(12)
            ->get()
            ->map(fn (Produk $p): array => [
                'id_produk' => $p->id_produk,
                'id_kategori' => $p->id_kategori,
                'kode_produk' => $p->kode_produk,
                'nama_produk' => $p->nama_produk,
                'harga' => (float) $p->harga,
                'stok' => (int) $p->stok,
                'gambar_url' => $p->gambar_url,
                'nama_kategori' => $p->kategori?->nama_kategori ?? 'Umum',
            ]);

        $stats = [
            'total_produk' => Produk::count(),
            'total_kategori' => $kategoriList->count(),
            'total_member' => Pelanggan::where('nama_pelanggan', 'not like', '%Umum%')->count(),
        ];

        return Inertia::render('Welcome', [
            'isAuthenticated' => Auth::check(),
            'userRole' => Auth::user()?->role?->value,
            'kategoriList' => $kategoriList,
            'produkUnggulan' => $produkUnggulan,
            'stats' => $stats,
        ]);
    }
}
