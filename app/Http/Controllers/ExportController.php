<?php

namespace App\Http\Controllers;

use App\Exports\AuditLogExport;
use App\Exports\LaporanPenjualanExport;
use App\Exports\PelangganExport;
use App\Exports\ProdukExport;
use App\Helpers\ExportHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExportController extends Controller
{
    public function export(Request $request, string $modul, string $format): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'date_from', 'date_to', 'status', 'id_kategori', 'id_user', 'kota', 'nama_tabel']);

        if ($user?->role?->value === 'kasir') {
            $filters['id_user'] = $user->id_user;
        }

        if ($modul === 'audit-log' && ! in_array($user?->role?->value, ['admin', 'owner'], true)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'Hanya Admin dan Owner yang dapat mengekspor Audit Log.');
        }

        $exporter = match ($modul) {
            'produk' => new ProdukExport($filters),
            'pelanggan' => new PelangganExport($filters),
            'audit-log' => new AuditLogExport($filters),
            'laporan-detail' => new LaporanPenjualanExport('detail', $filters),
            'laporan-pelanggan' => new LaporanPenjualanExport('pelanggan', $filters),
            default => new LaporanPenjualanExport('view', $filters),
        };

        $filename = $modul.'_'.now()->format('Ymd_His');

        if (strtolower($format) === 'pdf') {
            return ExportHelper::downloadPdf($exporter, $filename);
        }

        return ExportHelper::downloadExcel($exporter, $filename);
    }
}
