<?php

namespace App\Exports;

use App\Filters\ProdukFilter;
use App\Models\Produk;
use Illuminate\Support\Collection;

class ProdukExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters = []
    ) {}

    public function title(): string
    {
        return 'LAPORAN DATA MASTER PRODUK & STOK (TBL_PRODUK)';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID Produk',
            'Kode PLU / Barcode',
            'Nama Produk',
            'Kategori',
            'Harga Jual (Rp)',
            'Stok Tersedia',
            'Status Stok',
        ];
    }

    public function collection(): Collection
    {
        $filter = app(ProdukFilter::class);

        return $filter->apply(Produk::with('kategori'), $this->filters)->get();
    }

    /**
     * @param  Produk  $row
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id_produk,
            $row->kode_produk,
            $row->nama_produk,
            $row->kategori?->nama_kategori ?? '-',
            number_format((float) $row->harga, 0, ',', '.'),
            $row->stok,
            $row->stok <= 5 ? 'MENIPIS' : 'AMAN',
        ];
    }
}
