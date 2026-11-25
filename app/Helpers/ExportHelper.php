<?php

namespace App\Helpers;

use Illuminate\Http\Response;

class ExportHelper
{
    /**
     * Proteksi Spreadsheet Formula Injection sesuai standar /yz-1 (menetralkan sel yang diawali =, +, -, @, \t, \r).
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        $str = trim((string) $value);

        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            if (! is_numeric($str)) {
                $str = "'".$str;
            }
        }

        return htmlspecialchars($str, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Mesin pengunduh Excel (.xls) terpusat dengan proteksi Formula Injection.
     *
     * @param  object{title: callable(): string, headings: callable(): array<int, string>, collection: callable(): iterable<mixed>, map: callable(mixed): array<int, mixed>}  $export
     */
    public static function downloadExcel(object $export, string $filename): Response
    {
        $title = htmlspecialchars((string) $export->title(), ENT_QUOTES, 'UTF-8');
        $headings = $export->headings();
        $rows = $export->collection();

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>';
        $html .= '<table border="1" style="border-collapse:collapse; font-family:Consolas, monospace; font-size:12px;">';
        $html .= '<tr><th colspan="'.count($headings).'" style="background:#0b3c6f; color:#ffffff; padding:8px; font-size:14px;">'.$title.'</th></tr>';
        $html .= '<tr>';

        foreach ($headings as $heading) {
            $html .= '<th style="background:#d0d7de; color:#0f172a; padding:6px; font-weight:bold;">'.self::sanitizeCell($heading).'</th>';
        }

        $html .= '</tr>';

        foreach ($rows as $row) {
            $mapped = $export->map($row);
            $html .= '<tr>';
            foreach ($mapped as $cell) {
                $html .= '<td style="padding:5px;">'.self::sanitizeCell($cell).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename).'.xls';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Mesin pengunduh dokumen Laporan PDF (.pdf A4 Landscape) langsung ke folder Downloads
     * tanpa membuka dialog window.print() / printer thermal.
     *
     * @param  object{title: callable(): string, headings: callable(): array<int, string>, collection: callable(): iterable<mixed>, map: callable(mixed): array<int, mixed>}  $export
     */
    public static function downloadPdf(object $export, string $filename): Response
    {
        $title = (string) $export->title();
        $headings = array_map(
            fn ($h): string => self::sanitizeCell($h),
            $export->headings()
        );

        $rows = [];
        foreach ($export->collection() as $row) {
            $rows[] = array_map(
                fn ($cell): string => self::sanitizeCell($cell),
                $export->map($row)
            );
        }

        $printedAt = now()->format('d/m/Y H:i:s');
        $pdfBinary = SimplePdfBuilder::buildTableReport($title, $headings, $rows, $printedAt);
        $safeFilename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename).'.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safeFilename.'"',
            'Content-Length' => (string) strlen($pdfBinary),
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }
}
