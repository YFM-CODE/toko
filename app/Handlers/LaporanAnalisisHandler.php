<?php

namespace App\Handlers;

use App\Interfaces\LogInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanAnalisisHandler
{
    public function __construct(
        private LogInterface $logInterface
    ) {}

    /**
     * Laporan Penjualan berbasis Database View (view_laporan_penjualan & view_total_belanja_pelanggan).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getLaporanView(array $filters = []): array
    {
        $queryPenjualan = DB::table('view_laporan_penjualan');

        if (! empty($filters['date_from'])) {
            $queryPenjualan->whereDate('tanggal', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $queryPenjualan->whereDate('tanggal', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $queryPenjualan->where('status', $filters['status']);
        }

        if (! empty($filters['id_user'])) {
            $idUser = (int) $filters['id_user'];
            $queryPenjualan->whereIn('id_transaksi', function ($q) use ($idUser): void {
                $q->select('id_transaksi')
                    ->from('tbl_transaksi')
                    ->where('id_user', $idUser);
            });
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $queryPenjualan->where(function ($q) use ($search): void {
                $q->where('kode_transaksi', 'like', $search)
                    ->orWhere('nama_pelanggan', 'like', $search)
                    ->orWhere('kasir', 'like', $search);
            });
        }

        $laporanPenjualan = $queryPenjualan
            ->orderByDesc('tanggal')
            ->orderByDesc('id_transaksi')
            ->get();

        $totalBelanjaPelanggan = DB::table('view_total_belanja_pelanggan')
            ->orderByDesc('total_belanja')
            ->get();

        return [
            'laporan_penjualan' => $laporanPenjualan,
            'total_belanja_pelanggan' => $totalBelanjaPelanggan,
        ];
    }

    /**
     * Laporan Detail Transaksi berbasis Multiple JOIN 5 Tabel sesuai dokumen perancangan.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getLaporanDetailMultipleJoin(array $filters = []): Collection
    {
        $query = DB::table('tbl_transaksi as t')
            ->join('tbl_pelanggan as p', 't.id_pelanggan', '=', 'p.id_pelanggan')
            ->join('tbl_detail_transaksi as d', 't.id_transaksi', '=', 'd.id_transaksi')
            ->join('tbl_produk as pr', 'd.id_produk', '=', 'pr.id_produk')
            ->join('tbl_kategori as k', 'pr.id_kategori', '=', 'k.id_kategori')
            ->select([
                't.id_transaksi',
                't.kode_transaksi',
                't.tanggal',
                't.status',
                'p.nama_pelanggan',
                'k.id_kategori',
                'k.nama_kategori',
                'pr.kode_produk',
                'pr.nama_produk',
                'd.jumlah',
                'd.harga as harga_satuan',
                'd.subtotal_item',
            ]);

        if (! empty($filters['date_from'])) {
            $query->whereDate('t.tanggal', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('t.tanggal', '<=', $filters['date_to']);
        }

        if (! empty($filters['id_kategori'])) {
            $query->where('k.id_kategori', (int) $filters['id_kategori']);
        }

        if (! empty($filters['id_user'])) {
            $query->where('t.id_user', (int) $filters['id_user']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('pr.nama_produk', 'like', $search)
                    ->orWhere('pr.kode_produk', 'like', $search)
                    ->orWhere('t.kode_transaksi', 'like', $search)
                    ->orWhere('p.nama_pelanggan', 'like', $search);
            });
        }

        return $query
            ->orderByDesc('t.tanggal')
            ->orderByDesc('t.id_transaksi')
            ->get();
    }

    /**
     * Rekapitulasi Setoran & Omzet Shift per Kasir (Kasir 1, Kasir 2, dst.).
     * Hanya menampilkan user dengan role 'kasir' (termasuk yang transaksinya 0).
     *
     * @param  array<string, mixed>  $filters
     */
    public function getRekapPerKasir(array $filters = []): Collection
    {
        $query = DB::table('tbl_users as u')
            ->leftJoin('tbl_transaksi as t', function ($join) use ($filters): void {
                $join->on('u.id_user', '=', 't.id_user')
                    ->where('t.status', '=', 'selesai');

                if (! empty($filters['date_from'])) {
                    $join->whereDate('t.tanggal', '>=', $filters['date_from']);
                }

                if (! empty($filters['date_to'])) {
                    $join->whereDate('t.tanggal', '<=', $filters['date_to']);
                }
            })
            ->where('u.role', 'kasir')
            ->selectRaw("
                u.id_user,
                u.nama_lengkap AS nama_kasir,
                u.username,
                COUNT(t.id_transaksi) AS total_transaksi,
                COALESCE(SUM(CASE WHEN t.metode_pembayaran = 'TUNAI' OR t.metode_pembayaran IS NULL THEN t.total_belanja ELSE 0 END), 0) AS total_tunai,
                COALESCE(SUM(CASE WHEN t.metode_pembayaran IS NOT NULL AND t.metode_pembayaran != 'TUNAI' THEN t.total_belanja ELSE 0 END), 0) AS total_non_tunai,
                COALESCE(SUM(t.diskon), 0) AS total_diskon,
                COALESCE(SUM(t.total_belanja), 0) AS total_omzet
            ");

        if (! empty($filters['id_user'])) {
            $query->where('u.id_user', (int) $filters['id_user']);
        }

        return $query
            ->groupBy('u.id_user', 'u.nama_lengkap', 'u.username')
            ->orderByDesc('total_omzet')
            ->orderBy('u.nama_lengkap')
            ->get();
    }

    /**
     * Analisis Transaksi berbasis Subquery (AVG, NOT EXISTS, Produk Terlaris, Produk Belum Terjual).
     *
     * @return array<string, mixed>
     */
    public function getAnalisisSubquery(): array
    {
        $rataRataBelanja = (float) (DB::table('tbl_transaksi')
            ->where('status', 'selesai')
            ->avg('total_belanja') ?? 0);

        $transaksiDiatasRataRata = DB::select("
            SELECT t.id_transaksi, t.kode_transaksi, t.tanggal, t.id_pelanggan, p.nama_pelanggan, t.total_belanja, t.status
            FROM tbl_transaksi t
            INNER JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
            WHERE t.status = 'selesai'
              AND t.total_belanja > (
                SELECT COALESCE(AVG(total_belanja), 0)
                FROM tbl_transaksi
                WHERE status = 'selesai'
            )
            ORDER BY t.total_belanja DESC
        ");

        $pelangganBelumTransaksi = DB::select('
            SELECT id_pelanggan, nama_pelanggan, kota, no_telepon
            FROM tbl_pelanggan p
            WHERE NOT EXISTS (
                SELECT 1
                FROM tbl_transaksi t
                WHERE t.id_pelanggan = p.id_pelanggan
            )
            ORDER BY nama_pelanggan ASC
        ');

        $limaTransaksiTerbesar = DB::select("
            SELECT t.id_transaksi, t.kode_transaksi, t.tanggal, p.nama_pelanggan, t.total_belanja
            FROM tbl_transaksi t
            INNER JOIN tbl_pelanggan p ON t.id_pelanggan = p.id_pelanggan
            WHERE t.status = 'selesai'
            ORDER BY t.total_belanja DESC
            LIMIT 5
        ");

        $produkTerlaris = DB::select("
            SELECT
                pr.id_produk,
                pr.kode_produk,
                pr.nama_produk,
                k.nama_kategori,
                pr.harga,
                pr.stok,
                COALESCE(SUM(d.jumlah), 0) AS total_terjual,
                COALESCE(SUM(d.subtotal_item), 0) AS total_omzet
            FROM tbl_produk pr
            INNER JOIN tbl_kategori k ON pr.id_kategori = k.id_kategori
            INNER JOIN tbl_detail_transaksi d ON pr.id_produk = d.id_produk
            INNER JOIN tbl_transaksi t ON d.id_transaksi = t.id_transaksi
            WHERE t.status = 'selesai'
            GROUP BY pr.id_produk, pr.kode_produk, pr.nama_produk, k.nama_kategori, pr.harga, pr.stok
            ORDER BY total_terjual DESC, total_omzet DESC
            LIMIT 10
        ");

        $produkBelumTerjual = DB::select('
            SELECT pr.id_produk, pr.kode_produk, pr.nama_produk, pr.harga, pr.stok
            FROM tbl_produk pr
            WHERE NOT EXISTS (
                SELECT 1
                FROM tbl_detail_transaksi d
                WHERE d.id_produk = pr.id_produk
            )
            ORDER BY pr.nama_produk ASC
        ');

        return [
            'rata_rata_belanja' => round($rataRataBelanja, 2),
            'diatas_rata_rata' => $transaksiDiatasRataRata,
            'belum_transaksi' => $pelangganBelumTransaksi,
            'lima_terbesar' => $limaTransaksiTerbesar,
            'produk_terlaris' => $produkTerlaris,
            'produk_belum_terjual' => $produkBelumTerjual,
        ];
    }

    /**
     * Audit Riwayat Log Aktivitas dari tbl_log.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAuditLog(array $filters = []): LengthAwarePaginator
    {
        return $this->logInterface->getAll($filters);
    }

    /**
     * Verifikasi penggunaan Index melalui EXPLAIN query pada tbl_transaksi.
     *
     * @return array<int, object>
     */
    public function getExplainTransaksi(int $idPelanggan = 1, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ?? now()->startOfMonth()->toDateString();
        $to = $dateTo ?? now()->endOfMonth()->toDateString();
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::select(
                'EXPLAIN SELECT * FROM tbl_transaksi WHERE id_pelanggan = ? AND tanggal BETWEEN ? AND ?',
                [$idPelanggan, $from, $to]
            );
        }

        return DB::select(
            'EXPLAIN QUERY PLAN SELECT * FROM tbl_transaksi WHERE id_pelanggan = ? AND tanggal BETWEEN ? AND ?',
            [$idPelanggan, $from, $to]
        );
    }
}
