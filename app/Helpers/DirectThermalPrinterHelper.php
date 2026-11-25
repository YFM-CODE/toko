<?php

namespace App\Helpers;

use App\Models\Transaksi;
use RuntimeException;

class DirectThermalPrinterHelper
{
    /**
     * Cetak struk transaksi langsung (Silent Direct Print) ke printer thermal Windows (mis. EPSON TM-T82X Receipt)
     * menggunakan protokol RAW ESC/POS + Auto-Cut tanpa dialog browser dan tanpa sisa kertas panjang.
     */
    public static function printTransaksi(
        Transaksi $transaksi,
        float $tunai = 0,
        float $kembalian = 0,
        string $paperSize = '80mm',
        ?int $customCols = null,
        int $leftSpace = 0
    ): string {
        $printerName = self::resolvePrinterName();
        $defaultCols = $paperSize === '58mm' ? 30 : 38;
        $cols = $customCols ? max(26, min(44, $customCols)) : $defaultCols;
        $safeLeftSpace = max(0, min(6, $leftSpace));

        $raw = self::buildEscPosPayload($transaksi, $tunai, $kembalian, $cols, $safeLeftSpace);

        self::sendRawToWindowsSpooler($printerName, $raw, 'Struk_'.$transaksi->kode_transaksi);

        return $printerName;
    }

    public static function resolvePrinterName(): string
    {
        $configured = env('THERMAL_PRINTER_NAME');
        if (! empty($configured)) {
            return (string) $configured;
        }

        return 'EPSON TM-T82X Receipt';
    }

    private static function buildEscPosPayload(
        Transaksi $transaksi,
        float $tunai,
        float $kembalian,
        int $cols,
        int $leftSpace = 0
    ): string {
        $esc = "\x1b";
        $gs = "\x1d";

        $init = $esc.'@';
        $alignCenter = $esc.'a'."\x01";
        $alignLeft = $esc.'a'."\x00";
        $boldOn = $esc.'E'."\x01";
        $boldOff = $esc.'E'."\x00";
        $doubleHeightOn = $gs.'!'."\x01";
        $normalSize = $gs.'!'."\x00";
        $doubleStrikeOn = $esc.'G'."\x01";
        $cutPaper = $gs.'V'."\x42"."\x00";

        // Set Left Margin hardware printer thermal jika leftSpace > 0 (GS L nL nH, 1 karakter ≈ 12 dots)
        $leftMarginDots = $leftSpace * 12;
        $setLeftMargin = $gs.'L'.chr($leftMarginDots & 0xFF).chr(($leftMarginDots >> 8) & 0xFF);

        $divider = str_repeat('-', $cols)."\n";
        $doubleDivider = str_repeat('=', $cols)."\n";

        $bayar = $tunai > 0 ? $tunai : (float) ($transaksi->nominal_bayar ?: $transaksi->total_belanja);
        $kembali = $tunai > 0 ? $kembalian : (float) ($transaksi->kembalian ?: max(0, $bayar - (float) $transaksi->total_belanja));
        $kasirName = $transaksi->kasir?->nama_lengkap ?? 'Kasir';
        $pelangganName = $transaksi->pelanggan?->nama_pelanggan ?? 'Pelanggan Umum';
        $metodeBayar = (string) ($transaksi->metode_pembayaran ?: 'TUNAI');
        $channelBayar = (string) ($transaksi->payment_channel ?: 'CASH');
        $refBayar = (string) ($transaksi->payment_ref ?: '');
        $labelPembayaran = self::shortPaymentLabel($metodeBayar, $channelBayar);

        $buf = $init.$doubleStrikeOn.$setLeftMargin;

        // 1. Header Toko + Logo Raster Monokrom (Sama dengan ReceiptModal.vue)
        $buf .= $alignCenter;
        $buf .= self::buildMonoLogoRaster();
        $buf .= "\n";
        $buf .= $boldOn.$doubleHeightOn."TOKO RITEL MANDIRI\n".$normalSize.$boldOff;
        $buf .= self::centerWrapped('Jl. Raya Ponorogo No. 10 - Telp: (0352) 481234', $cols);
        $buf .= "STRUK PENJUALAN RESMI\n";
        $buf .= $divider;

        // 2. Metadata Transaksi (Sama dengan ReceiptModal.vue)
        $buf .= $alignLeft;
        $buf .= self::formatRow('NO. STRUK:', (string) $transaksi->kode_transaksi, $cols);
        $buf .= self::formatRow('TANGGAL:', (string) $transaksi->tanggal, $cols);
        $buf .= self::formatRow('KASIR:', $kasirName, $cols);
        $buf .= self::formatRow('PELANGGAN:', $pelangganName, $cols);
        $buf .= $boldOn.self::formatRow('PEMBAYARAN:', $labelPembayaran, $cols).$boldOff;
        if (! empty($refBayar)) {
            $buf .= self::formatRow('NO. REF:', $refBayar, $cols);
        }
        $buf .= $divider;

        // 3. Rincian Item (Nama Produk di kiri, Subtotal di kanan + baris Qty x Harga di bawahnya)
        foreach ($transaksi->detail as $item) {
            $namaProduk = $item->produk?->nama_produk ?? ('Produk #'.$item->id_produk);
            $subItem = self::rupiah((float) $item->subtotal_item);
            $buf .= $boldOn.self::formatRow($namaProduk, $subItem, $cols).$boldOff;
            $buf .= $item->jumlah.' x '.self::rupiah((float) $item->harga)."\n";
        }

        $buf .= $divider;

        // 4. Ringkasan Total (Sama persis dengan ReceiptModal.vue)
        $buf .= self::formatRow('SUBTOTAL:', self::rupiah((float) $transaksi->subtotal), $cols);
        $buf .= self::formatRow('DISKON:', '- '.self::rupiah((float) $transaksi->diskon), $cols);
        $buf .= $doubleDivider;
        $buf .= $boldOn.self::formatRow('GRAND TOTAL:', self::rupiah((float) $transaksi->total_belanja), $cols).$boldOff;
        $buf .= $boldOn.self::formatRow('PEMBAYARAN:', $labelPembayaran, $cols).$boldOff;
        $labelBayar = $metodeBayar === 'TUNAI' ? 'TUNAI DITERIMA:' : 'NOMINAL BAYAR:';
        $buf .= self::formatRow($labelBayar, self::rupiah($bayar), $cols);
        $buf .= $boldOn.self::formatRow('KEMBALIAN:', self::rupiah($kembali), $cols).$boldOff;
        if ((int) $transaksi->poin_didapat > 0 || (int) $transaksi->poin_ditukar > 0) {
            $sisaPoin = (int) ($transaksi->pelanggan?->poin ?? 0);
            $poinText = '+'.(int) $transaksi->poin_didapat.' Poin';
            if ((int) $transaksi->poin_ditukar > 0) {
                $poinText .= ' (-'.(int) $transaksi->poin_ditukar.')';
            }
            $poinText .= ' • Total: '.$sisaPoin;
            $buf .= self::formatRow('POIN MEMBER:', $poinText, $cols);
        }
        $buf .= $divider;

        // 5. Native ESC/POS QR Code (GS ( k) + Footer (Sama dengan ReceiptModal.vue)
        $buf .= $alignCenter;
        $buf .= self::buildNativeQrCode((string) $transaksi->kode_transaksi);
        $buf .= "\n".(string) $transaksi->kode_transaksi."\n";
        $buf .= $boldOn."TERIMA KASIH ATAS KUNJUNGAN ANDA\n".$boldOff;
        $buf .= self::centerWrapped('Barang yang sudah dibeli tidak dapat ditukar/dikembalikan', $cols);

        // 6. Feed & Auto-Cut tepat di akhir struk (tanpa sisa kertas panjang)
        $buf .= "\n\n";
        $buf .= $cutPaper;

        return $buf;
    }

    private static function shortPaymentLabel(string $metodeBayar, string $channelBayar): string
    {
        if (strtoupper($metodeBayar) === 'TUNAI') {
            return 'TUNAI (CASH)';
        }
        $upper = strtoupper(trim($channelBayar ?: $metodeBayar));
        if (str_contains($upper, 'GOPAY MERCHANT')) {
            return 'QRIS GOPAY';
        }
        if (mb_strlen($upper) > 22) {
            return mb_substr($upper, 0, 22);
        }

        return $upper;
    }

    private static function centerWrapped(string $text, int $cols): string
    {
        $wrapped = wordwrap($text, max(16, $cols), "\n", true);

        return $wrapped."\n";
    }

    /**
     * Menghasilkan Logo Monokrom Toko (ikon kotak kasir/struk 64x64px) dalam format raster ESC/POS (GS v 0)
     * agar cetak 1-klik memiliki logo kop atas yang sama dengan tampilan browser tanpa perlu ekstensi GD.
     */
    private static function buildMonoLogoRaster(): string
    {
        $size = 64;
        $bytesPerRow = 8; // 64 / 8
        $raster = '';

        for ($y = 0; $y < $size; $y++) {
            for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = ($bx * 8) + $bit;
                    $isBlack = true;

                    // Potong 4 sudut agar terlihat membulat (radius 8px)
                    $dx = min($x, $size - 1 - $x);
                    $dy = min($y, $size - 1 - $y);
                    if ($dx + $dy < 6) {
                        $isBlack = false;
                    }

                    // Bingkai putih tipis di dalam (margin 10..53, tebal 3px)
                    $inOuterBox = ($x >= 12 && $x <= 51 && $y >= 12 && $y <= 51);
                    $inInnerCut = ($x >= 16 && $x <= 47 && $y >= 16 && $y <= 47);
                    if ($inOuterBox && ! $inInnerCut) {
                        $isBlack = false;
                    }

                    // Layar LED mesin kasir putih di atas dalam
                    if ($x >= 20 && $x <= 43 && $y >= 20 && $y <= 28) {
                        $isBlack = false;
                    }

                    // Tombol/garis struk putih di bawah layar
                    if ($x >= 20 && $x <= 32 && $y >= 33 && $y <= 36) {
                        $isBlack = false;
                    }
                    if ($x >= 20 && $x <= 32 && $y >= 40 && $y <= 43) {
                        $isBlack = false;
                    }
                    if ($x >= 36 && $x <= 43 && $y >= 33 && $y <= 43) {
                        $isBlack = false;
                    }

                    if ($isBlack) {
                        $byte |= (1 << (7 - $bit));
                    }
                }
                $raster .= chr($byte);
            }
        }

        // GS v 0 m xL xH yL yH d1...dk
        return "\x1d\x76\x30\x00".chr($bytesPerRow)."\x00".chr($size)."\x00".$raster;
    }

    private static function buildNativeQrCode(string $data): string
    {
        $gs = "\x1d";
        $len = strlen($data) + 3;
        $pL = chr($len & 0xFF);
        $pH = chr(($len >> 8) & 0xFF);

        // Select Model 2, Size 5, Error Correction M, Store Data, Print QR
        return $gs."(k\x04\x00\x31\x41\x32\x00"
            .$gs."(k\x03\x00\x31\x43\x05"
            .$gs."(k\x03\x00\x31\x45\x31"
            .$gs.'(k'.$pL.$pH."\x31\x50\x30".$data
            .$gs."(k\x03\x00\x31\x51\x30";
    }

    private static function formatRow(string $left, string $right, int $cols): string
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $spaces = $cols - $leftLen - $rightLen;

        if ($spaces < 1) {
            return mb_substr($left, 0, max(1, $cols - $rightLen - 1)).' '.$right."\n";
        }

        return $left.str_repeat(' ', $spaces).$right."\n";
    }

    private static function rupiah(float $val): string
    {
        return 'Rp '.number_format($val, 0, ',', '.');
    }

    private static function sendRawToWindowsSpooler(string $printerName, string $rawBytes, string $docName): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Direct Windows Spooler hanya tersedia pada sistem operasi Windows.');
        }

        $uniqueId = uniqid('', true);
        $tempFile = storage_path('app/thermal_'.$uniqueId.'.bin');
        $psFile = storage_path('app/thermal_'.$uniqueId.'.ps1');
        file_put_contents($tempFile, $rawBytes);

        $escapedPrinter = str_replace("'", "''", $printerName);
        $escapedFile = str_replace("'", "''", $tempFile);
        $escapedDoc = str_replace("'", "''", $docName);

        $psScript = <<<PS
\$ErrorActionPreference = 'Stop'
\$code = @'
using System;
using System.IO;
using System.Runtime.InteropServices;

public class RawPrinterHelper {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOCINFOW {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterW", SetLastError = true, CharSet = CharSet.Unicode, ExactSpelling = true)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterW", SetLastError = true, CharSet = CharSet.Unicode, ExactSpelling = true)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, [In] DOCINFOW di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, Int32 dwCount, out Int32 dwWritten);

    public static string SendFileToPrinter(string szPrinterName, string szFileName, string docName) {
        byte[] bytes = File.ReadAllBytes(szFileName);
        IntPtr hPrinter = IntPtr.Zero;
        DOCINFOW di = new DOCINFOW();
        di.pDocName = docName;
        di.pDataType = "RAW";

        if (!OpenPrinter(szPrinterName, out hPrinter, IntPtr.Zero)) {
            return "OpenPrinter gagal (Win32 Error: " + Marshal.GetLastWin32Error() + ")";
        }

        try {
            if (!StartDocPrinter(hPrinter, 1, di)) {
                return "StartDocPrinter gagal (Win32 Error: " + Marshal.GetLastWin32Error() + ")";
            }
            try {
                if (!StartPagePrinter(hPrinter)) {
                    return "StartPagePrinter gagal (Win32 Error: " + Marshal.GetLastWin32Error() + ")";
                }
                try {
                    IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
                    try {
                        Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
                        int dwWritten = 0;
                        if (!WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out dwWritten)) {
                            return "WritePrinter gagal (Win32 Error: " + Marshal.GetLastWin32Error() + ")";
                        }
                    } finally {
                        Marshal.FreeCoTaskMem(pUnmanagedBytes);
                    }
                } finally {
                    EndPagePrinter(hPrinter);
                }
            } finally {
                EndDocPrinter(hPrinter);
            }
        } finally {
            ClosePrinter(hPrinter);
        }

        return "OK";
    }
}
'@
Add-Type -TypeDefinition \$code
\$res = [RawPrinterHelper]::SendFileToPrinter('$escapedPrinter', '$escapedFile', '$escapedDoc')
if (\$res -ne 'OK') {
    throw "Gagal mengirim ke '$escapedPrinter': \$res"
}
PS;

        file_put_contents($psFile, $psScript);

        $psExe = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (! file_exists($psExe)) {
            $psExe = 'powershell.exe';
        }

        $cmd = '"'.$psExe.'" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "'.$psFile.'" 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        if (file_exists($psFile)) {
            @unlink($psFile);
        }

        if ($exitCode !== 0) {
            $detail = trim(implode(' ', $output));
            throw new RuntimeException('Printer Thermal tidak merespons (Exit '.$exitCode.'): '.($detail !== '' ? $detail : 'Pastikan printer menyala & nama printer sesuai di .env'));
        }
    }
}
