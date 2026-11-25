<?php

namespace App\Http\Controllers\Pengguna;

use App\Handlers\PelangganHandler;
use App\Handlers\TransaksiHandler;
use App\Helpers\PaymentGatewayHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Interfaces\KategoriInterface;
use App\Interfaces\ProdukInterface;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private PelangganHandler $pelangganHandler,
        private TransaksiHandler $transaksiHandler,
        private ProdukInterface $produkInterface,
        private KategoriInterface $kategoriInterface
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $pelanggan = $this->resolveMemberPelanggan($user);
        $tierAktif = $this->pelangganHandler->syncMemberTier($pelanggan);

        $selesaiQuery = Transaksi::with(['detail.produk', 'kasir'])
            ->where('id_pelanggan', $pelanggan->id_pelanggan)
            ->where('status', 'selesai');

        $totalTransaksi = (clone $selesaiQuery)->count();
        $totalBelanja = (float) (clone $selesaiQuery)->sum('total_belanja');
        $totalHematDiskon = (float) (clone $selesaiQuery)->sum('diskon');

        // Tampilkan seluruh pesanan member (termasuk yang masih 'pending' / belum dibayar)
        $riwayatTransaksi = Transaksi::with(['detail.produk', 'kasir'])
            ->where('id_pelanggan', $pelanggan->id_pelanggan)
            ->orderByDesc('id_transaksi')
            ->limit(25)
            ->get();

        $produkKatalog = Produk::with('kategori')
            ->where('stok', '>', 0)
            ->orderBy('nama_produk')
            ->get();

        $kategoriList = $this->kategoriInterface->getAllWithoutPagination();

        return Inertia::render('Member/Dashboard', [
            'pelanggan' => $pelanggan->fresh(),
            'stats' => [
                'total_transaksi' => $totalTransaksi,
                'total_belanja' => $totalBelanja,
                'total_hemat' => $totalHematDiskon,
                'tier' => $tierAktif,
                'diskon_tier_persen' => $pelanggan->diskon_tier_persen,
                'poin' => (int) $pelanggan->poin,
                'nilai_rupiah_poin' => (int) $pelanggan->poin * 100,
                'saldo' => (float) $pelanggan->saldo,
                'next_tier' => $totalBelanja < 500000 ? 'Silver' : ($totalBelanja < 1000000 ? 'Gold' : 'MAX (Gold)'),
                'kurang_ke_next_tier' => $totalBelanja < 500000
                    ? max(0, 500000 - $totalBelanja)
                    : ($totalBelanja < 1000000 ? max(0, 1000000 - $totalBelanja) : 0),
            ],
            'riwayatTransaksi' => $riwayatTransaksi,
            'produkKatalog' => $produkKatalog,
            'kategoriList' => $kategoriList,
            'gatewayConfig' => PaymentGatewayHelper::getGatewayConfigInfo(),
        ]);
    }

    /**
     * Proses Belanja Mandiri (Self-Checkout) oleh Pelanggan / Member terdaftar.
     * Diskon Tier Member, poin loyalitas, dan pengurangan stok diproses secara atomik.
     */
    public function checkout(CheckoutRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $user = Auth::user();
            $pelanggan = $this->resolveMemberPelanggan($user);
            $this->pelangganHandler->syncMemberTier($pelanggan);
            $pelanggan->refresh();

            $payload = $request->validated();
            $payload['id_pelanggan'] = $pelanggan->id_pelanggan;
            $payload['persen_diskon'] = (float) $pelanggan->diskon_tier_persen;
            $payload['metode_pembayaran'] = $payload['metode_pembayaran'] ?? 'QRIS_MPM';

            // Jika bayar menggunakan QRIS atau Tunai di Kasir, status awal WAJIB 'pending' (Menunggu Pembayaran)
            // sehingga belum dianggap sukses membeli dan BELUM BISA cetak struk sebelum lunas!
            // Hanya pembayaran SALDO_MEMBER (saldo langsung terpotong) yang langsung 'selesai'.
            $payload['is_pending'] = $payload['metode_pembayaran'] !== 'SALDO_MEMBER';

            $transaksi = $this->transaksiHandler->prosesCheckout($payload);
            $isPaid = $transaksi->status === 'selesai';
            $msg = $isPaid
                ? "Pembayaran Saldo RFID berhasil! Transaksi {$transaksi->kode_transaksi} LUNAS dan struk siap dicetak."
                : "Pesanan {$transaksi->kode_transaksi} dibuat dengan status MENUNGGU PEMBAYARAN. Selesaikan pembayaran terlebih dahulu agar pembelian sukses dan struk dapat dicetak.";

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $msg,
                    'is_paid' => $isPaid,
                    'data' => $transaksi,
                ], 201);
            }

            return redirect()->route('member.dashboard')
                ->with('success', $msg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['checkout' => 'Gagal memproses pesanan: '.$e->getMessage()]);
        }
    }

    /**
     * Upload Bukti Pembayaran QRIS oleh Member.
     * Status transaksi TETAP 'pending' (Menunggu Verifikasi Kasir) sampai Kasir memverifikasi lunas.
     */
    public function uploadBukti(ConfirmPaymentRequest $request, int $id): RedirectResponse|JsonResponse
    {
        try {
            $user = Auth::user();
            $pelanggan = $this->resolveMemberPelanggan($user);
            $transaksi = Transaksi::where('id_transaksi', $id)
                ->where('id_pelanggan', $pelanggan->id_pelanggan)
                ->firstOrFail();

            if ($transaksi->status !== 'pending') {
                throw new \Exception('Pesanan ini sudah diproses dan tidak memerlukan upload bukti lagi.');
            }

            if (! $request->hasFile('bukti_pembayaran')) {
                throw new \Exception('Silakan pilih file foto/screenshot bukti transfer terlebih dahulu.');
            }

            $file = $request->file('bukti_pembayaran');
            $directory = public_path('uploads/bukti_pembayaran');
            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $filename = Str::slug($transaksi->kode_transaksi).'-'.time().'.'.$extension;
            $file->move($directory, $filename);

            $validated = $request->validated();
            $transaksi->update([
                'bukti_pembayaran' => $filename,
                'payment_ref' => ! empty($validated['payment_ref']) ? trim((string) $validated['payment_ref']) : 'MENUNGGU-VERIFIKASI-KASIR',
            ]);

            $msg = "Bukti pembayaran untuk {$transaksi->kode_transaksi} berhasil dikirim! Menunggu verifikasi Kasir sebelum struk dapat dicetak.";

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $msg,
                    'is_paid' => false,
                    'data' => $transaksi->fresh(['pelanggan', 'kasir', 'detail.produk']),
                ]);
            }

            return redirect()->route('member.dashboard')->with('success', $msg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }

    private function resolveMemberPelanggan(User $user): Pelanggan
    {
        $pelanggan = Pelanggan::where('id_user', $user->id_user)
            ->orWhere(function ($q) use ($user) {
                if (! empty($user->rfid_uid)) {
                    $q->where('rfid_uid', $user->rfid_uid);
                }
            })
            ->first();

        if (! $pelanggan) {
            return Pelanggan::create([
                'id_user' => $user->id_user,
                'rfid_uid' => $user->rfid_uid,
                'nama_pelanggan' => $user->nama_lengkap,
                'kota' => 'Ponorogo',
                'no_telepon' => '-',
                'tier' => 'Reguler',
                'poin' => 10,
                'saldo' => 0.00,
            ]);
        }

        if (! $pelanggan->id_user) {
            $pelanggan->update(['id_user' => $user->id_user]);
        }

        return $pelanggan;
    }
}
