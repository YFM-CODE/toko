<?php

namespace App\Exports;

use App\Handlers\LaporanAnalisisHandler;
use App\Models\User;
use Illuminate\Support\Collection;

class LaporanPenjualanExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private string $mode = 'view',
        private array $filters = []
    ) {}

    public function title(): string
    {
        $suffix = '';
        if (! empty($this->filters['id_user'])) {
            $namaKasir = User::where('id_user', (int) $this->filters['id_user'])->value('nama_lengkap');
            if ($namaKasir) {
                $suffix = ' - KASIR: '.strtoupper($namaKasir);
            }
        }

        return match ($this->mode) {
            'detail' => 'LAPORAN RINCIAN TRANSAKSI PENJUALAN (MULTIPLE JOIN 5 TABEL)'.$suffix,
            'pelanggan' => 'LAPORAN KLASIFIKASI BELANJA PELANGGAN (VIEW & STORED FUNCTION)',
            default => 'LAPORAN REKAP PENJUALAN TOKO (VIEW_LAPORAN_PENJUALAN)'.$suffix,
        };
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return match ($this->mode) {
            'detail' => [
                'Kode Struk',
                'Tanggal',
                'Pelanggan',
                'Kategori',
                'Kode PLU',
                'Nama Produk',
                'Qty',
                'Harga Satuan (Rp)',
                'Subtotal Item (Rp)',
            ],
            'pelanggan' => [
                'ID Pelanggan',
                'Nama Pelanggan',
                'Kota',
                'Total Transaksi',
                'Total Belanja (Rp)',
                'Kategori Member',
            ],
            default => [
                'Kode Struk',
                'Tanggal',
                'Pelanggan',
                'Kota',
                'Kasir',
                'Subtotal (Rp)',
                'Diskon (Rp)',
                'Total Belanja (Rp)',
                'Status',
            ],
        };
    }

    public function collection(): Collection
    {
        $handler = app(LaporanAnalisisHandler::class);

        if ($this->mode === 'detail') {
            return $handler->getLaporanDetailMultipleJoin($this->filters);
        }

        $viewData = $handler->getLaporanView($this->filters);

        if ($this->mode === 'pelanggan') {
            return collect($viewData['total_belanja_pelanggan'] ?? []);
        }

        return collect($viewData['laporan_penjualan'] ?? []);
    }

    /**
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        if ($this->mode === 'detail') {
            return [
                $row->kode_transaksi,
                $row->tanggal,
                $row->nama_pelanggan,
                $row->nama_kategori,
                $row->kode_produk,
                $row->nama_produk,
                $row->jumlah,
                number_format((float) $row->harga_satuan, 0, ',', '.'),
                number_format((float) $row->subtotal_item, 0, ',', '.'),
            ];
        }

        if ($this->mode === 'pelanggan') {
            return [
                $row->id_pelanggan,
                $row->nama_pelanggan,
                $row->kota,
                $row->total_transaksi,
                number_format((float) $row->total_belanja, 0, ',', '.'),
                $row->kategori_pelanggan,
            ];
        }

        return [
            $row->kode_transaksi,
            $row->tanggal,
            $row->nama_pelanggan,
            $row->kota,
            $row->kasir,
            number_format((float) $row->subtotal, 0, ',', '.'),
            number_format((float) $row->diskon, 0, ',', '.'),
            number_format((float) $row->total_belanja, 0, ',', '.'),
            strtoupper((string) $row->status),
        ];
    }
}
