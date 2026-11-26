<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaymentGatewayHelper
{
    /**
     * Daftar Kode NNS (National Numbering System) Standar QRIS Bank Indonesia (8 digit awal PAN / Tag 26-51 / EMVCo CPM)
     * untuk mendeteksi secara otomatis apakah QR Code yang di-scan kasir berasal dari Bank BRI, BCA, Mandiri, BNI, BSI, DANA, GoPay, OVO, atau ShopeePay.
     *
     * @var array<string, string>
     */
    private const INDONESIAN_QR_NNS_MAP = [
        '93600002' => 'QR BANK BRI (BRIMO)',
        '93600008' => 'QR BANK MANDIRI (LIVIN)',
        '93600009' => 'QR BANK BNI',
        '93600011' => 'QR BANK DANAMON',
        '93600013' => 'QR BANK PERMATA',
        '93600014' => 'QR BANK BCA',
        '93600016' => 'QR BANK MAYBANK',
        '93600022' => 'QR BANK CIMB NIAGA',
        '93600110' => 'QR BANK BJB',
        '93600114' => 'QR BANK JATIM',
        '93600200' => 'QR BANK BTN',
        '93600451' => 'QR BANK BSI (SYARIAH)',
        '93600503' => 'QR BANK NOBU',
        '93600898' => 'QR SHOPEEPAY',
        '93600911' => 'QR LINKAJA',
        '93600912' => 'QR OVO',
        '93600914' => 'QR GOPAY',
        '93600915' => 'QR DANA',
        '93600918' => 'QR SHOPEEPAY',
    ];

    /**
     * Informasi konfigurasi Payment Gateway Duitku (Sandbox / Production) & Tujuan Dana Toko.
     *
     * @return array{
     *     duitku_configured: bool,
     *     sandbox: bool,
     *     merchant_code: string,
     *     merchant_name: string,
     *     merchant_nmid: string,
     *     qris_image_url: string,
     *     mode_label: string,
     *     settlement_destination: string
     * }
     */
    public static function getGatewayConfigInfo(): array
    {
        $merchantCode = trim((string) config('services.duitku.merchant_code', env('DUITKU_MERCHANT_CODE', '')));
        $apiKey = trim((string) config('services.duitku.api_key', env('DUITKU_API_KEY', '')));
        $isSandbox = (bool) config('services.duitku.sandbox', filter_var(env('DUITKU_SANDBOX', true), FILTER_VALIDATE_BOOLEAN));

        $isConfigured = $merchantCode !== '' && $apiKey !== '';

        $merchantName = trim((string) env('TOKO_MERCHANT_NAME', 'YAZID, ELEKTRONIK'));
        $merchantNmid = trim((string) env('TOKO_MERCHANT_NMID', 'ID1026647000219'));
        $qrisImage = trim((string) env('TOKO_QRIS_IMAGE', '/images/qris-toko.jpg'));
        $settlementBank = trim((string) env('TOKO_SETTLEMENT_ACCOUNT', "DUITKU ({$merchantCode}) • {$merchantName}"));

        if ($isConfigured) {
            $modeLabel = $isSandbox ? "DUITKU SANDBOX ({$merchantCode})" : "DUITKU PRODUCTION ({$merchantCode})";
        } elseif ($qrisImage !== '') {
            $modeLabel = "QRIS MERCHANT RESMI ({$merchantName})";
        } else {
            $modeLabel = 'SIMULASI LOKAL';
        }

        return [
            'duitku_configured' => $isConfigured,
            'sandbox' => $isSandbox,
            'merchant_code' => $merchantCode,
            'merchant_name' => $merchantName,
            'merchant_nmid' => $merchantNmid,
            'qris_image_url' => $qrisImage,
            'mode_label' => $modeLabel,
            'settlement_destination' => $settlementBank,
        ];
    }

    /**
     * Membuat Invoice / QRIS Dinamis via Duitku V2 Inquiry API
     * (Sandbox: https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry | Production: https://passport.duitku.com/webapi/api/merchant/v2/inquiry).
     *
     * @return array<string, mixed>
     */
    public static function createDynamicQris(
        string $kodeTransaksi,
        float $amount,
        string $paymentMethod = '',
        ?string $customerEmail = null,
        ?string $customerName = null
    ): array {
        $config = self::getGatewayConfigInfo();
        $merchantCode = $config['merchant_code'];
        $apiKey = trim((string) config('services.duitku.api_key', env('DUITKU_API_KEY', '')));
        $isSandbox = $config['sandbox'];
        $method = trim($paymentMethod) !== '' ? strtoupper(trim($paymentMethod)) : (string) config('services.duitku.default_method', 'BR');

        // Gunakan 100% nominal tagihan asli tanpa pernah menaikkan harga belanja pembeli
        $paymentAmount = max(1, (int) round($amount));
        $merchantOrderId = $kodeTransaksi.'-'.now()->format('His');
        $gatewayError = null;

        if ($merchantCode !== '' && $apiKey !== '') {
            $baseUrl = $isSandbox
                ? 'https://sandbox.duitku.com/webapi/api/merchant'
                : 'https://passport.duitku.com/webapi/api/merchant';

            $signature = md5($merchantCode.$merchantOrderId.$paymentAmount.$apiKey);

            try {
                $http = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(12);

                if ($isSandbox && app()->isLocal()) {
                    $http = $http->withoutVerifying();
                }

                $response = $http->post("{$baseUrl}/v2/inquiry", [
                    'merchantCode' => $merchantCode,
                    'paymentAmount' => $paymentAmount,
                    'paymentMethod' => $method,
                    'merchantOrderId' => $merchantOrderId,
                    'productDetails' => "Pembayaran Transaksi {$kodeTransaksi} - {$config['merchant_name']}",
                    'customerVaName' => $customerName ?: 'Pelanggan Toko',
                    'email' => $customerEmail ?: 'pelanggan@tokoku.local',
                    'callbackUrl' => url('/api/payment/duitku/callback'),
                    'returnUrl' => url('/member/dashboard?tab=riwayat'),
                    'expiryPeriod' => 60,
                    'signature' => $signature,
                ]);

                $json = $response->json() ?? [];
                if ($response->successful() && ($json['statusCode'] ?? '') === '00') {
                    return [
                        'qr_id' => (string) ($json['reference'] ?? ('duitku_'.$merchantOrderId)),
                        'qr_string' => (string) ($json['qrString'] ?? ''),
                        'payment_url' => (string) ($json['paymentUrl'] ?? ''),
                        'va_number' => (string) ($json['vaNumber'] ?? ''),
                        'payment_method' => $method,
                        'reference_id' => $merchantOrderId,
                        'amount' => (float) $paymentAmount,
                        'provider' => $config['mode_label'],
                        'is_duitku_live' => true,
                        'is_sandbox' => $isSandbox,
                        'gateway_error' => null,
                        'qris_image_url' => $config['qris_image_url'],
                        'merchant_name' => $config['merchant_name'],
                        'merchant_nmid' => $config['merchant_nmid'],
                        'settlement_destination' => $config['settlement_destination'],
                    ];
                }

                $rawErr = (string) ($json['Message'] ?? $json['statusMessage'] ?? '');
                if ($paymentAmount < 10000) {
                    $gatewayError = 'Tagihan saat ini Rp '.number_format($paymentAmount, 0, ',', '.').'. Dari ketentuan server Duitku, metode Virtual Account / Payment Gateway Duitku mewajibkan minimal transaksi Rp 10.000. Untuk belanja di bawah Rp 10.000 silakan gunakan QRIS Toko (Manual) atau Saldo RFID.';
                } else {
                    $gatewayError = $rawErr !== '' ? "Duitku API: {$rawErr}" : 'Metode pembayaran ini sedang tidak tersedia di Duitku Sandbox.';
                }
            } catch (Throwable $e) {
                $gatewayError = 'Tidak dapat terhubung ke server Duitku: '.$e->getMessage();
            }
        }

        $staticQris = trim((string) env('TOKO_QRIS_STATIC', ''));
        if ($staticQris !== '') {
            $dynamicQris = self::convertStaticQrisToDynamic($staticQris, $amount);

            return [
                'qr_id' => 'qris_merchant_'.strtolower($merchantOrderId),
                'qr_string' => $dynamicQris,
                'payment_url' => '',
                'va_number' => '',
                'payment_method' => $method,
                'qris_image_url' => $config['qris_image_url'],
                'merchant_name' => $config['merchant_name'],
                'merchant_nmid' => $config['merchant_nmid'],
                'reference_id' => $merchantOrderId,
                'amount' => $amount,
                'provider' => $config['mode_label'],
                'is_duitku_live' => false,
                'is_sandbox' => $isSandbox,
                'settlement_destination' => $config['settlement_destination'],
            ];
        }

        $nominalStr = (string) ((int) round($amount));
        $emvQris = '00020101021226580014ID.CO.BRI.WWW011893600002010008899152045411530336054'
            .str_pad((string) strlen($nominalStr), 2, '0', STR_PAD_LEFT).$nominalStr
            .'5802ID5918TOKO RITEL MANDIRI6008PONOROGO62180114'.$kodeTransaksi.'6304BRI1';

        return [
            'qr_id' => 'qris_local_'.strtolower($merchantOrderId),
            'qr_string' => $emvQris,
            'payment_url' => '',
            'va_number' => '',
            'payment_method' => $method,
            'qris_image_url' => $config['qris_image_url'],
            'merchant_name' => $config['merchant_name'],
            'merchant_nmid' => $config['merchant_nmid'],
            'reference_id' => $merchantOrderId,
            'amount' => $amount,
            'provider' => $config['mode_label'],
            'is_duitku_live' => false,
            'is_sandbox' => $isSandbox,
            'gateway_error' => $gatewayError,
            'settlement_destination' => $config['settlement_destination'],
        ];
    }

    /**
     * Mengecek status pembayaran transaksi langsung ke API Duitku (`transactionStatus`).
     *
     * @return array{ok: bool, is_paid: bool, status_code: string, status_message: string, reference: string}
     */
    public static function checkDuitkuTransactionStatus(string $merchantOrderId): array
    {
        $config = self::getGatewayConfigInfo();
        $merchantCode = $config['merchant_code'];
        $apiKey = trim((string) config('services.duitku.api_key', env('DUITKU_API_KEY', '')));

        if ($merchantCode === '' || $apiKey === '') {
            return [
                'ok' => false,
                'is_paid' => false,
                'status_code' => '99',
                'status_message' => 'Kredensial Duitku belum dikonfigurasi.',
                'reference' => '',
            ];
        }

        $baseUrl = $config['sandbox']
            ? 'https://sandbox.duitku.com/webapi/api/merchant'
            : 'https://passport.duitku.com/webapi/api/merchant';

        $signature = md5($merchantCode.$merchantOrderId.$apiKey);

        try {
            $http = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10);

            if ($config['sandbox'] && app()->isLocal()) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post("{$baseUrl}/transactionStatus", [
                'merchantCode' => $merchantCode,
                'merchantOrderId' => $merchantOrderId,
                'signature' => $signature,
            ]);

            $json = $response->json() ?? [];
            $statusCode = (string) ($json['statusCode'] ?? '01');

            return [
                'ok' => $response->successful(),
                'is_paid' => $statusCode === '00',
                'status_code' => $statusCode,
                'status_message' => (string) ($json['statusMessage'] ?? 'PENDING'),
                'reference' => (string) ($json['reference'] ?? ''),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'is_paid' => false,
                'status_code' => '99',
                'status_message' => $e->getMessage(),
                'reference' => '',
            ];
        }
    }

    /**
     * Memverifikasi signature Callback / Webhook dari server Duitku.
     */
    public static function verifyDuitkuCallbackSignature(
        string $merchantCode,
        string $amount,
        string $merchantOrderId,
        string $signature
    ): bool {
        $apiKey = trim((string) config('services.duitku.api_key', env('DUITKU_API_KEY', '')));
        if ($apiKey === '' || $merchantCode === '' || $merchantOrderId === '' || $signature === '') {
            return false;
        }

        $expected = md5($merchantCode.$amount.$merchantOrderId.$apiKey);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    /**
     * Mengubah QRIS Statis (Tag 01=11) milik toko menjadi QRIS Dinamis EMVCo (Tag 01=12 + Tag 54 Nominal + Tag 63 CRC16-CCITT).
     */
    public static function convertStaticQrisToDynamic(string $staticQris, float $amount): string
    {
        $clean = preg_replace('/6304[A-Fa-f0-9]{4}$/', '', trim($staticQris)) ?? trim($staticQris);
        $clean = str_replace('000201010211', '000201010212', $clean);

        $nominalStr = (string) ((int) round($amount));
        $tag54 = '54'.str_pad((string) strlen($nominalStr), 2, '0', STR_PAD_LEFT).$nominalStr;

        if (str_contains($clean, '5802ID')) {
            $clean = str_replace('5802ID', $tag54.'5802ID', $clean);
        } else {
            $clean .= $tag54;
        }

        $payloadForCrc = $clean.'6304';

        return $payloadForCrc.self::crc16Ccitt($payloadForCrc);
    }

    private static function crc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Memvalidasi & memproses pembayaran Tunai maupun Non-Tunai (CPM Scan Barcode/QR HP Pembeli seperti QR BRI, BCA, DANA, GoPay,
     * maupun QRIS MPM Xendit).
     *
     * @return array{
     *     metode_pembayaran: string,
     *     payment_channel: string,
     *     payment_token: string|null,
     *     payment_ref: string,
     *     nominal_bayar: float,
     *     kembalian: float
     * }
     */
    public static function authorizePayment(
        string $metodePembayaran,
        float $totalAkhir,
        float $nominalBayarInput = 0,
        ?string $rawToken = null,
        ?string $requestedChannel = null
    ): array {
        $metode = strtoupper(trim($metodePembayaran ?: 'TUNAI'));

        if ($metode === 'TUNAI') {
            $bayar = $nominalBayarInput > 0 ? $nominalBayarInput : $totalAkhir;
            if ($bayar < $totalAkhir) {
                throw new Exception('Nominal uang tunai kurang dari total belanja.');
            }

            return [
                'metode_pembayaran' => 'TUNAI',
                'payment_channel' => 'TUNAI (CASH)',
                'payment_token' => null,
                'payment_ref' => 'CASH-'.now()->format('YmdHis'),
                'nominal_bayar' => $bayar,
                'kembalian' => max(0.0, $bayar - $totalAkhir),
            ];
        }

        if ($metode === 'SCAN_CPM') {
            $token = trim((string) $rawToken);
            if (mb_strlen($token) < 6) {
                throw new Exception('Kode Barcode / QR Pembayaran tidak valid atau terlalu pendek (minimal 6 karakter).');
            }

            $detectedChannel = self::detectCpmProvider($token, $requestedChannel);
            $maskedToken = self::maskToken($token);
            $refPrefix = self::extractPrefixCode($detectedChannel);

            return [
                'metode_pembayaran' => 'SCAN_CPM',
                'payment_channel' => $detectedChannel,
                'payment_token' => $maskedToken,
                'payment_ref' => $refPrefix.'-'.strtoupper(substr(md5($token.microtime(true)), 0, 8)),
                'nominal_bayar' => $totalAkhir,
                'kembalian' => 0.0,
            ];
        }

        if ($metode === 'QRIS_MPM') {
            $detectedFromQr = ! empty($rawToken) ? self::detectCpmProvider($rawToken, $requestedChannel) : null;
            $channel = (! empty($requestedChannel) && strtoupper($requestedChannel) !== 'AUTO')
                ? strtoupper(trim($requestedChannel))
                : ($detectedFromQr ?: 'QRIS DUITKU');

            $token = ! empty($rawToken) ? self::maskToken($rawToken) : 'QRIS-MPM-VERIFIED';
            $refPrefix = self::extractPrefixCode($channel);

            return [
                'metode_pembayaran' => 'QRIS_MPM',
                'payment_channel' => $channel,
                'payment_token' => $token,
                'payment_ref' => $refPrefix.'-'.now()->format('His').'-'.strtoupper(substr(uniqid(), -4)),
                'nominal_bayar' => $totalAkhir,
                'kembalian' => 0.0,
            ];
        }

        if ($metode === 'SALDO_MEMBER') {
            return [
                'metode_pembayaran' => 'SALDO_MEMBER',
                'payment_channel' => 'SALDO KARTU MEMBER RFID',
                'payment_token' => ! empty($rawToken) ? self::maskToken($rawToken) : 'RFID-MEMBER-BALANCE',
                'payment_ref' => 'MBR-'.now()->format('YmdHis'),
                'nominal_bayar' => $totalAkhir,
                'kembalian' => 0.0,
            ];
        }

        throw new Exception("Metode pembayaran '{$metode}' tidak dikenali.");
    }

    /**
     * Mendeteksi secara otomatis Nama Bank / E-Wallet dari QR Code / Barcode yang di-scan kasir:
     * - Mendukung payload EMVCo QRIS MPM (`000201...`)
     * - Mendukung payload EMVCo QRIS CPM (`hQVDUFY...` - Base64 dari TLV 0x85 "CPV01" yang berisi PAN NNS Bank Indonesia)
     * - Mendukung kode NNS 8-digit Bank Indonesia (`93600002` = BRI, `93600014` = BCA, `93600008` = Mandiri, `93600009` = BNI, dll.)
     * - Mendukung domain bank (`ID.CO.BRI`, `BRIMO`, `BCA`, `MANDIRI`, `BNI`, `BSI`, `GOPAY`, `DANA`, `OVO`, `SHOPEE`)
     */
    public static function detectCpmProvider(string $token, ?string $fallbackChannel = null): string
    {
        $raw = trim($token);
        $upper = strtoupper($raw);

        // 1. Jika payload adalah Base64 EMVCo QRIS CPM (diawali "hQVDUFY" / "hQVD"), decode Base64 untuk membaca NNS / PAN Bank di dalamnya
        $decodedHexAndAscii = $upper;
        if (str_starts_with($upper, 'HQVD') || preg_match('/^[A-Za-z0-9+\/=]{16,}$/', $raw)) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && $decoded !== '') {
                $decodedHexAndAscii .= ' '.strtoupper($decoded).' '.strtoupper(bin2hex($decoded));
            }
        }

        // 2. Cek Kode NNS Resmi Bank Indonesia (93600xxx) di dalam payload QR atau hasil decode CPM
        foreach (self::INDONESIAN_QR_NNS_MAP as $nnsCode => $bankLabel) {
            if (str_contains($decodedHexAndAscii, $nnsCode)) {
                return $bankLabel;
            }
        }

        // 3. Cek Domain / Kata Kunci Bank BRI & BRImo
        if (
            str_contains($decodedHexAndAscii, 'BRI') ||
            str_contains($decodedHexAndAscii, 'BRIMO') ||
            str_contains($decodedHexAndAscii, 'ID.CO.BRI') ||
            preg_match('/^002\d{6,}$/', $upper)
        ) {
            return 'QR BANK BRI (BRIMO)';
        }

        // 4. Cek Bank BCA
        if (
            str_contains($decodedHexAndAscii, 'BCA') ||
            str_contains($decodedHexAndAscii, 'myBCA') ||
            preg_match('/^(014|880)\d{5,}$/', $upper)
        ) {
            return 'QR BANK BCA';
        }

        // 5. Cek Bank Mandiri / Livin
        if (
            str_contains($decodedHexAndAscii, 'MANDIRI') ||
            str_contains($decodedHexAndAscii, 'LIVIN') ||
            preg_match('/^008\d{6,}$/', $upper)
        ) {
            return 'QR BANK MANDIRI (LIVIN)';
        }

        // 6. Cek Bank BNI
        if (
            str_contains($decodedHexAndAscii, 'BNI') ||
            str_contains($decodedHexAndAscii, 'WONDROUS') ||
            preg_match('/^009\d{6,}$/', $upper)
        ) {
            return 'QR BANK BNI';
        }

        // 7. Cek Bank BSI
        if (
            str_contains($decodedHexAndAscii, 'BSI') ||
            str_contains($decodedHexAndAscii, 'SYARIAH') ||
            preg_match('/^451\d{6,}$/', $upper)
        ) {
            return 'QR BANK BSI';
        }

        // 8. Cek GoPay
        if (
            str_contains($decodedHexAndAscii, 'GOPAY') ||
            str_contains($decodedHexAndAscii, 'GOJEK') ||
            str_starts_with($upper, 'GP-') ||
            preg_match('/^2810\d+$/', $upper)
        ) {
            return 'QR GOPAY';
        }

        // 9. Cek DANA
        if (
            str_contains($decodedHexAndAscii, 'DANA') ||
            str_starts_with($upper, 'DN-') ||
            preg_match('/^2811\d+$/', $upper)
        ) {
            return 'QR DANA';
        }

        // 10. Cek OVO
        if (
            str_contains($decodedHexAndAscii, 'OVO') ||
            str_starts_with($upper, 'OV-') ||
            preg_match('/^2812\d+$/', $upper)
        ) {
            return 'QR OVO';
        }

        // 11. Cek ShopeePay
        if (
            str_contains($decodedHexAndAscii, 'SPAY') ||
            str_contains($decodedHexAndAscii, 'SHOPEE') ||
            str_contains($decodedHexAndAscii, 'AIRPAY') ||
            preg_match('/^2813\d+$/', $upper)
        ) {
            return 'QR SHOPEEPAY';
        }

        // 12. Cek LinkAja
        if (str_contains($decodedHexAndAscii, 'LINKAJA') || str_contains($decodedHexAndAscii, 'FASPAY')) {
            return 'QR LINKAJA';
        }

        // 13. Cek Kartu RFID Member
        if (str_starts_with($upper, 'RFID-') || str_starts_with($upper, 'MBR-')) {
            return 'SALDO MEMBER RFID';
        }

        // 14. Cek Standar EMVCo QRIS Umum jika tidak ada NNS spesifik
        if (str_starts_with($upper, 'HQVDUFY') || str_starts_with($upper, '000201') || str_starts_with($upper, 'QRIS')) {
            if (! empty($fallbackChannel) && strtoupper($fallbackChannel) !== 'AUTO') {
                return strtoupper(trim($fallbackChannel));
            }

            return 'QRIS NASIONAL (CPM)';
        }

        if (! empty($fallbackChannel) && strtoupper($fallbackChannel) !== 'AUTO') {
            return strtoupper(trim($fallbackChannel));
        }

        return 'QR PAYMENT GATEWAY';
    }

    private static function extractPrefixCode(string $channelName): string
    {
        $upper = strtoupper($channelName);
        if (str_contains($upper, 'BRI')) {
            return 'BRI';
        }
        if (str_contains($upper, 'BCA')) {
            return 'BCA';
        }
        if (str_contains($upper, 'MANDIRI')) {
            return 'MDR';
        }
        if (str_contains($upper, 'BNI')) {
            return 'BNI';
        }
        if (str_contains($upper, 'BSI')) {
            return 'BSI';
        }
        if (str_contains($upper, 'GOPAY')) {
            return 'GOPAY';
        }
        if (str_contains($upper, 'DANA')) {
            return 'DANA';
        }
        if (str_contains($upper, 'SHOPEE')) {
            return 'SPAY';
        }
        if (str_contains($upper, 'OVO')) {
            return 'OVO';
        }

        return 'QRIS';
    }

    private static function maskToken(string $token): string
    {
        $clean = trim($token);
        $len = mb_strlen($clean);
        if ($len <= 8) {
            return $clean;
        }

        return mb_substr($clean, 0, 4).'****'.mb_substr($clean, -4);
    }
}
