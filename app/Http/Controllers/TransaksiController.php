<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Handlers\TransaksiHandler;
use App\Helpers\DirectThermalPrinterHelper;
use App\Helpers\PaginateHelper;
use App\Helpers\PaymentGatewayHelper;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Interfaces\KategoriInterface;
use App\Interfaces\PelangganInterface;
use App\Interfaces\ProdukInterface;
use App\Interfaces\TransaksiInterface;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TransaksiController extends Controller
{
    public function __construct(
        private TransaksiInterface $transaksiInterface,
        private PelangganInterface $pelangganInterface,
        private ProdukInterface $produkInterface,
        private KategoriInterface $kategoriInterface,
        private TransaksiHandler $transaksiHandler
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'tanggal',
            'date_from',
            'date_to',
            'id_pelanggan',
            'id_user',
            'status',
            'sort_by',
            'sort_direction',
            'per_page',
        ]);

        $isKasir = $user?->role === UserRole::Kasir;
        if ($isKasir) {
            if (($filters['status'] ?? '') === 'pending') {
                unset($filters['id_user']);
            } else {
                $filters['kasir_or_pending_id'] = $user->id_user;
                unset($filters['id_user']);
            }
        }

        $transaksi = $this->transaksiInterface->getAll($filters);
        $kasirList = $isKasir
            ? collect([['id_user' => $user->id_user, 'nama_lengkap' => $user->nama_lengkap, 'username' => $user->username]])
            : User::where('role', UserRole::Kasir->value)
                ->orderBy('nama_lengkap')
                ->get(['id_user', 'nama_lengkap', 'username']);

        return Inertia::render('Transaksi/Index', [
            'transaksi' => $transaksi,
            'pagination' => PaginateHelper::getPaginate($transaksi),
            'kasirList' => $kasirList,
            'isKasirScoped' => $isKasir,
            'filters' => $filters,
        ]);
    }

    public function pos(): Response
    {
        $prefix = 'TRX-'.now()->format('Ym').'-';
        $last = Transaksi::where('kode_transaksi', 'like', $prefix.'%')
            ->orderByDesc('id_transaksi')
            ->value('kode_transaksi');
        $nextNumber = $last ? ((int) substr((string) $last, -4) + 1) : 1;
        $nextKode = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        // Pastikan Pelanggan Umum (Non-Member) selalu tersedia untuk pembeli walk-in
        $walkIn = Pelanggan::firstOrCreate(
            ['nama_pelanggan' => 'Pelanggan Umum (Non-Member)'],
            ['kota' => 'Umum', 'no_telepon' => '-']
        );

        $pelangganList = Pelanggan::query()
            ->orderByRaw('CASE WHEN id_pelanggan = ? THEN 0 ELSE 1 END', [$walkIn->id_pelanggan])
            ->orderBy('nama_pelanggan')
            ->get();

        return Inertia::render('Pos/Index', [
            'nextKodeTransaksi' => $nextKode,
            'pelangganList' => $pelangganList,
            'produkList' => Produk::with('kategori')->orderBy('nama_produk')->get(),
            'kategoriList' => $this->kategoriInterface->getAllWithoutPagination(),
            'gatewayConfig' => PaymentGatewayHelper::getGatewayConfigInfo(),
        ]);
    }

    public function create(): Response
    {
        return $this->pos();
    }

    public function show(int $id): Response
    {
        $transaksi = $this->transaksiInterface->find($id);

        if ($transaksi->status !== 'selesai') {
            abort(403, 'Transaksi belum dibayar lunas atau sudah dibatalkan, sehingga struk belum dapat dicetak.');
        }

        return Inertia::render('Transaksi/Show', [
            'transaksi' => $transaksi,
        ]);
    }

    /**
     * Konfirmasi pelunasan pesanan Member yang berstatus 'pending' oleh Kasir / Admin.
     */
    public function confirmPayment(ConfirmPaymentRequest $request, int $id): RedirectResponse|JsonResponse
    {
        try {
            $transaksi = $this->transaksiHandler->konfirmasiPembayaran($id, $request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Pembayaran transaksi {$transaksi->kode_transaksi} berhasil dikonfirmasi LUNAS. Struk siap dicetak.",
                    'data' => $transaksi,
                ]);
            }

            return redirect()->back()
                ->with('success', "Pembayaran transaksi {$transaksi->kode_transaksi} berhasil dikonfirmasi LUNAS. Struk kini dapat dicetak.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['transaksi' => 'Gagal konfirmasi pembayaran: '.$e->getMessage()]);
        }
    }

    public function store(CheckoutRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $transaksi = $this->transaksiHandler->prosesCheckout($request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Transaksi {$transaksi->kode_transaksi} berhasil diproses.",
                    'data' => $transaksi,
                ], 201);
            }

            return redirect()->route('transaksi.index')
                ->with('success', "Transaksi {$transaksi->kode_transaksi} berhasil diproses.")
                ->with('receipt', $transaksi);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['checkout' => 'Gagal memproses transaksi: '.$e->getMessage()]);
        }
    }

    public function void(int $id): RedirectResponse
    {
        try {
            $transaksi = $this->transaksiHandler->voidTransaksi($id);

            return redirect()->back()
                ->with('success', "Transaksi {$transaksi->kode_transaksi} berhasil dibatalkan (void) dan stok telah dikembalikan.");
        } catch (Throwable $e) {
            return redirect()->back()
                ->withErrors(['transaksi' => 'Gagal membatalkan transaksi: '.$e->getMessage()]);
        }
    }

    /**
     * Cetak struk langsung (1-Click Silent Direct Print) ke printer thermal EPSON TM-T82X Receipt tanpa dialog browser.
     */
    public function printDirect(Request $request, int $id): JsonResponse
    {
        try {
            $transaksi = $this->transaksiInterface->find($id);

            if ($transaksi->status !== 'selesai') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Transaksi belum dibayar lunas (status: '.strtoupper((string) $transaksi->status).'). Struk tidak dapat dicetak sebelum pembayaran lunas.',
                ], 422);
            }

            $tunai = (float) ($request->input('tunai') ?? $transaksi->total_belanja);
            $kembalian = (float) ($request->input('kembalian') ?? max(0, $tunai - $transaksi->total_belanja));
            $paperSize = (string) ($request->input('paper_size') ?? '80mm');
            $cols = $request->input('cols') ? (int) $request->input('cols') : null;
            $leftSpace = $request->input('left_space') !== null ? (int) $request->input('left_space') : 0;

            $printerName = DirectThermalPrinterHelper::printTransaksi(
                $transaksi,
                $tunai,
                $kembalian,
                $paperSize,
                $cols,
                $leftSpace
            );

            return response()->json([
                'ok' => true,
                'message' => "Struk {$transaksi->kode_transaksi} berhasil dicetak langsung ke {$printerName}.",
                'printer' => $printerName,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal cetak langsung: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Endpoint untuk membuat QRIS / Virtual Account Dinamis via Duitku V2 Inquiry API (Sandbox / Production).
     */
    public function createQris(Request $request): JsonResponse
    {
        $kodeTransaksi = (string) ($request->input('kode_transaksi') ?: ('TRX-'.now()->format('Ym').'-0001'));
        $amount = max(1.0, (float) ($request->input('amount') ?: 0));
        $paymentMethod = (string) ($request->input('payment_method') ?: '');

        $user = $request->user();
        $qrisData = PaymentGatewayHelper::createDynamicQris(
            $kodeTransaksi,
            $amount,
            $paymentMethod,
            $user?->email,
            $user?->nama_lengkap
        );

        return response()->json([
            'ok' => true,
            'data' => $qrisData,
        ]);
    }

    /**
     * Mengecek status transaksi secara real-time (Auto-Check Polling):
     * 1. Mengecek database lokal terlebih dahulu (untuk QRIS GoPay Merchant yang diverifikasi Kasir / Webhook).
     * 2. Mengecek langsung ke API Duitku (`transactionStatus`) dan otomatis melunasi jika statusCode === '00'.
     */
    public function checkDuitkuStatus(Request $request): JsonResponse
    {
        $merchantOrderId = trim((string) $request->input('merchant_order_id', ''));
        $idTransaksi = (int) $request->input('id_transaksi', 0);
        $checkLocalOnly = (bool) $request->boolean('local_only', false);

        $trx = $idTransaksi > 0 ? Transaksi::with(['pelanggan', 'kasir', 'detail.produk'])->find($idTransaksi) : null;

        // 1. Jika di database lokal sudah berstatus 'selesai' (misal diverifikasi Kasir atau via Webhook), langsung kembalikan LUNAS
        if ($trx && $trx->status === 'selesai') {
            return response()->json([
                'ok' => true,
                'is_paid' => true,
                'status_code' => '00',
                'message' => "Pembayaran {$trx->kode_transaksi} telah terverifikasi LUNAS!",
                'data' => $trx,
            ]);
        }

        if ($checkLocalOnly) {
            return response()->json([
                'ok' => true,
                'is_paid' => false,
                'status_code' => '01',
                'message' => 'Menunggu verifikasi pembayaran...',
            ]);
        }

        if ($merchantOrderId === '' && $trx) {
            $merchantOrderId = $trx->kode_transaksi;
        }

        if ($merchantOrderId === '') {
            return response()->json([
                'ok' => false,
                'is_paid' => false,
                'message' => 'Kode transaksi / merchantOrderId wajib diisi.',
            ], 422);
        }

        $status = PaymentGatewayHelper::checkDuitkuTransactionStatus($merchantOrderId);

        if ($status['is_paid'] && $trx && $trx->status === 'pending') {
            $transaksi = $this->transaksiHandler->konfirmasiPembayaran($trx->id_transaksi, [
                'payment_ref' => $status['reference'] ?: ('DUITKU-'.$merchantOrderId),
                'nominal_bayar' => $trx->total_belanja,
            ]);

            return response()->json([
                'ok' => true,
                'is_paid' => true,
                'status_code' => '00',
                'message' => "Pembayaran Duitku terverifikasi LUNAS ({$transaksi->payment_ref}).",
                'data' => $transaksi,
            ]);
        }

        return response()->json([
            'ok' => $status['ok'],
            'is_paid' => $status['is_paid'],
            'status_code' => $status['status_code'],
            'message' => $status['status_message'],
        ]);
    }

    /**
     * Webhook Callback Resmi dari Server Duitku (`callbackUrl`).
     */
    public function duitkuCallback(Request $request): JsonResponse
    {
        $merchantCode = (string) $request->input('merchantCode', '');
        $amount = (string) $request->input('amount', '');
        $merchantOrderId = (string) $request->input('merchantOrderId', '');
        $signature = (string) $request->input('signature', '');
        $resultCode = (string) $request->input('resultCode', '');
        $reference = (string) $request->input('reference', '');

        if (! PaymentGatewayHelper::verifyDuitkuCallbackSignature($merchantCode, $amount, $merchantOrderId, $signature)) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 403);
        }

        if ($resultCode === '00') {
            $kodeBase = explode('-', $merchantOrderId);
            $kodeTransaksi = count($kodeBase) >= 3 ? implode('-', array_slice($kodeBase, 0, 3)) : $merchantOrderId;

            $transaksi = Transaksi::where('kode_transaksi', $merchantOrderId)
                ->orWhere('kode_transaksi', $kodeTransaksi)
                ->first();

            if ($transaksi && $transaksi->status === 'pending') {
                $this->transaksiHandler->konfirmasiPembayaran($transaksi->id_transaksi, [
                    'payment_ref' => $reference ?: ('DUITKU-CB-'.$merchantOrderId),
                    'nominal_bayar' => (float) $amount,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
