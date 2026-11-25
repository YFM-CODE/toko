<?php

namespace App\Exports;

use App\Filters\LogFilter;
use App\Models\LogAktivitas;
use Illuminate\Support\Collection;

class AuditLogExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters = []
    ) {}

    public function title(): string
    {
        return 'LAPORAN AUDIT LOG AKTIVITAS & TRIGGER DATABASE (TBL_LOG)';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'ID Log',
            'Waktu Eksekusi',
            'Nama Tabel',
            'Aktivitas',
            'ID Referensi',
            'Data Lama (OLD)',
            'Data Baru (NEW)',
            'Aktor / User',
        ];
    }

    public function collection(): Collection
    {
        $filter = app(LogFilter::class);

        return $filter->apply(LogAktivitas::with('user'), $this->filters)->limit(500)->get();
    }

    /**
     * @param  LogAktivitas  $row
     * @return list<mixed>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id_log,
            (string) $row->waktu,
            $row->nama_tabel,
            $row->aktivitas,
            $row->id_referensi ?? '-',
            $row->data_lama ?? '-',
            $row->data_baru ?? '-',
            $row->user?->nama_lengkap ?? 'DB Trigger',
        ];
    }
}
