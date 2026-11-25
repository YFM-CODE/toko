<?php

namespace App\Helpers;

class SimplePdfBuilder
{
    private const PAGE_WIDTH = 841.89; // A4 Landscape width (pt)

    private const PAGE_HEIGHT = 595.28; // A4 Landscape height (pt)

    private const MARGIN_X = 32.0;

    private const MARGIN_TOP = 34.0;

    private const MARGIN_BOTTOM = 34.0;

    /**
     * Menghasilkan dokumen PDF 1.4 biner asli ukuran A4 Landscape berisi tabel laporan.
     *
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public static function buildTableReport(string $title, array $headings, array $rows, string $printedAt): string
    {
        $colCount = max(1, count($headings));
        $tableWidth = self::PAGE_WIDTH - (self::MARGIN_X * 2);
        $colWidths = self::calculateColumnWidths($headings, $rows, $tableWidth, $colCount);

        $pages = [];
        $currentStream = '';
        $y = self::drawHeader($currentStream, $title, $printedAt, $tableWidth);
        $y = self::drawTableHead($currentStream, $headings, $colWidths, $y);

        $rowHeight = 18.0;
        $maxTextCharsPerCol = array_map(
            fn (float $w): int => max(4, (int) floor(($w - 8) / 4.7)),
            $colWidths
        );

        foreach ($rows as $rowIndex => $row) {
            if (($y - $rowHeight) < (self::MARGIN_BOTTOM + 22)) {
                $pages[] = $currentStream;
                $currentStream = '';
                $y = self::drawHeader($currentStream, $title, $printedAt, $tableWidth);
                $y = self::drawTableHead($currentStream, $headings, $colWidths, $y);
            }

            $isEven = ($rowIndex % 2) === 1;
            $x = self::MARGIN_X;

            if ($isEven) {
                $currentStream .= sprintf(
                    "0.96 0.97 0.98 rg %.2f %.2f %.2f %.2f re f\n",
                    self::MARGIN_X,
                    $y - $rowHeight,
                    $tableWidth,
                    $rowHeight
                );
            }

            // Border baris tipis
            $currentStream .= sprintf(
                "0.75 0.78 0.82 RG 0.5 w %.2f %.2f %.2f %.2f re S\n",
                self::MARGIN_X,
                $y - $rowHeight,
                $tableWidth,
                $rowHeight
            );

            for ($i = 0; $i < $colCount; $i++) {
                $cellVal = self::normalizeText((string) ($row[$i] ?? '-'));
                $maxChars = $maxTextCharsPerCol[$i] ?? 12;
                if (strlen($cellVal) > $maxChars) {
                    $cellVal = substr($cellVal, 0, max(1, $maxChars - 2)).'..';
                }

                $currentStream .= self::drawText(
                    $x + 4,
                    $y - 12.5,
                    $cellVal,
                    8.0,
                    false,
                    '0.06 0.09 0.16'
                );

                $x += $colWidths[$i];
                if ($i < $colCount - 1) {
                    $currentStream .= sprintf(
                        "0.82 0.85 0.88 RG 0.4 w %.2f %.2f m %.2f %.2f l S\n",
                        $x,
                        $y,
                        $x,
                        $y - $rowHeight
                    );
                }
            }

            $y -= $rowHeight;
        }

        // Ringkasan jumlah baris di bagian akhir
        $y -= 16;
        if ($y < self::MARGIN_BOTTOM + 14) {
            $pages[] = $currentStream;
            $currentStream = '';
            $y = self::PAGE_HEIGHT - self::MARGIN_TOP - 16;
        }
        $currentStream .= self::drawText(
            self::MARGIN_X,
            $y,
            'Total Data: '.count($rows).' baris  |  Dicetak dari Sistem POS DB_TOKO (Dokumen A4)',
            8.5,
            true,
            '0.07 0.16 0.30'
        );

        $pages[] = $currentStream;

        return self::compilePdfDocument($pages);
    }

    private static function drawHeader(string &$stream, string $title, string $printedAt, float $tableWidth): float
    {
        $topY = self::PAGE_HEIGHT - self::MARGIN_TOP;

        // Kop Warna Solid (#0b3c6f)
        $stream .= sprintf(
            "0.043 0.235 0.435 rg %.2f %.2f %.2f 42 re f\n",
            self::MARGIN_X,
            $topY - 42,
            $tableWidth
        );

        $stream .= self::drawText(
            self::MARGIN_X + 12,
            $topY - 17,
            'TOKO RITEL MANDIRI - YAZID ELEKTRONIK',
            12.0,
            true,
            '1 1 1'
        );

        $stream .= self::drawText(
            self::MARGIN_X + 12,
            $topY - 33,
            self::normalizeText($title).'  |  Dicetak: '.$printedAt,
            8.5,
            false,
            '1 0.835 0.310'
        );

        return $topY - 52;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<float>  $colWidths
     */
    private static function drawTableHead(string &$stream, array $headings, array $colWidths, float $y): float
    {
        $headHeight = 20.0;
        $totalWidth = array_sum($colWidths);

        $stream .= sprintf(
            "0.82 0.85 0.87 rg %.2f %.2f %.2f %.2f re f\n",
            self::MARGIN_X,
            $y - $headHeight,
            $totalWidth,
            $headHeight
        );

        $stream .= sprintf(
            "0.12 0.16 0.23 RG 0.8 w %.2f %.2f %.2f %.2f re S\n",
            self::MARGIN_X,
            $y - $headHeight,
            $totalWidth,
            $headHeight
        );

        $x = self::MARGIN_X;
        foreach ($headings as $i => $heading) {
            $maxChars = max(4, (int) floor(($colWidths[$i] - 8) / 5.0));
            $label = strtoupper(self::normalizeText((string) $heading));
            if (strlen($label) > $maxChars) {
                $label = substr($label, 0, max(1, $maxChars - 2)).'..';
            }

            $stream .= self::drawText($x + 4, $y - 13.5, $label, 8.0, true, '0.06 0.09 0.16');
            $x += $colWidths[$i];
        }

        return $y - $headHeight;
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     * @return list<float>
     */
    private static function calculateColumnWidths(array $headings, array $rows, float $tableWidth, int $colCount): array
    {
        $weights = [];
        for ($i = 0; $i < $colCount; $i++) {
            $maxLen = strlen((string) ($headings[$i] ?? ''));
            foreach (array_slice($rows, 0, 50) as $r) {
                $maxLen = max($maxLen, strlen((string) ($r[$i] ?? '')));
            }
            $weights[$i] = (float) min(32, max(8, $maxLen));
        }

        $totalWeight = max(1.0, array_sum($weights));
        $widths = [];
        foreach ($weights as $w) {
            $widths[] = round(($w / $totalWeight) * $tableWidth, 2);
        }

        return $widths;
    }

    private static function drawText(float $x, float $y, string $text, float $size, bool $bold, string $rgb): string
    {
        $font = $bold ? '/F2' : '/F1';
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return sprintf(
            "BT %s %.2f Tf %s rg %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $size,
            $rgb,
            $x,
            $y,
            $escaped
        );
    }

    private static function normalizeText(string $text): string
    {
        $replaced = str_replace(
            ['•', '–', '—', '→', '✓', '⚠', '🪪', '★', 'Rp '],
            ['|', '-', '-', '->', 'v', '!', '', '*', 'Rp '],
            $text
        );

        return preg_replace('/[^\x20-\x7E]/', '', $replaced) ?? '';
    }

    /**
     * @param  list<string>  $pageStreams
     */
    private static function compilePdfDocument(array $pageStreams): string
    {
        $objects = [];
        $totalPages = count($pageStreams);

        // 1: Catalog, 2: Pages, 3: Font Helvetica, 4: Font Helvetica-Bold
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageObjIds = [];
        $nextObjId = 5;

        foreach ($pageStreams as $idx => $streamContent) {
            $pageNum = $idx + 1;
            $streamContent .= self::drawText(
                self::PAGE_WIDTH - self::MARGIN_X - 85,
                20.0,
                "Halaman {$pageNum} / {$totalPages}",
                8.0,
                false,
                '0.35 0.40 0.48'
            );

            $contentObjId = $nextObjId++;
            $pageObjId = $nextObjId++;
            $pageObjIds[] = $pageObjId;

            $objects[$contentObjId] = '<< /Length '.strlen($streamContent)." >>\nstream\n{$streamContent}endstream";
            $objects[$pageObjId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentObjId
            );
        }

        $kidsRefs = implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageObjIds));
        $objects[2] = "<< /Type /Pages /Kids [{$kidsRefs}] /Count {$totalPages} >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
