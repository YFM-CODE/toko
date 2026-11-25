<?php

namespace App\Exports;

use App\Filters\PelangganFilter;
use App\Models\Pelanggan;
use Illuminate\Support\Collection;

class PelangganExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters = []
    ) {}

    public function title(): string
    {
        return 'LAPORAN DATA MEMBER & PELANGGAN (TBL_PELANGGAN)';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID Pelanggan',
            'UID Kartu RFID',
            'Nama Pelanggan / Member',
            'Tier Member',
            'Poin Loyalitas',
            'Saldo RFID (Rp)',
            'Kota Asal',
            'No. Telepon',
            'Jumlah Transaksi',
        ];
    }

    public function collection(): Collection
    {
        $filter = app(PelangganFilter::class);

        return $filter->apply(Pelanggan::query()->withCount('transaksi'), $this->filters)->get();
    }

    /**
     * @param  Pelanggan  $row
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id_pelanggan,
            $row->rfid_uid ?? '-',
            $row->nama_pelanggan,
            $row->tier ?? 'Reguler',
            (int) ($row->poin ?? 0),
            (float) ($row->saldo ?? 0),
            $row->kota,
            $row->no_telepon ?? '-',
            $row->transaksi_count ?? 0,
        ];
    }
}
