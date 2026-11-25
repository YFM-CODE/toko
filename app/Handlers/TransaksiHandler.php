<?php

namespace App\Handlers;

use App\Helpers\PaymentGatewayHelper;
use App\Interfaces\LogInterface;
use App\Interfaces\TransaksiInterface;
use App\Models\DetailTransaksi;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransaksiHandler
{
    public function __construct(
        private TransaksiInterface $transaksiInterface,
        private LogInterface $logInterface
    ) {}

    /**
     * Proses transaksi POS (mendukung multi-item & fallback Pelanggan Umum) menggunakan TCL, Stored Function, dan Trigger.
     *
     * @param  array<string, mixed>  $data
     */
    public function prosesCheckout(array $data): Transaksi
    {
        return DB::transaction(function () use ($data): Transaksi {
            $idUser = Auth::id();
            $tanggal = $data['tanggal'] ?? now()->toDateString();
            $persenDiskon = (float) ($data['persen_diskon'] ?? 0);
            $isMysql = $this->isMysqlDriver();

            $idPelanggan = ! empty($data['id_pelanggan'])
                ? (int) $data['id_pelanggan']
                : $this->resolveDefaultWalkInPelangganId();

            $subtotalBelanja = 0.0;
            $detailItems = [];

            foreach ($data['items'] as $item) {
                $produk = Produk::lockForUpdate()->findOrFail((int) $item['id_produk']);
                $jumlah = (int) $item['jumlah'];

                if ($produk->stok < $jumlah) {
                    throw new Exception("Stok produk {$produk->nama_produk} tidak mencukupi (Sisa: {$produk->stok}).");
                }

                $subtotalItem = $this->hitungTotal($produk->harga, $jumlah, $isMysql);
                $subtotalBelanja += $subtotalItem;

                $detailItems[] = [
                    'produk' => $produk,
                    'jumlah' => $jumlah,
                    'harga' => $produk->harga,
                    'subtotal_item' => $subtotalItem,
                ];
            }

            $nominalDiskon = $this->hitungDiskon($subtotalBelanja, $persenDiskon, $isMysql);
            $pelanggan = Pelanggan::lockForUpdate()->findOrFail($idPelanggan);

            // Penukaran Poin Member (1 Poin = Rp 100 potongan ekstra)
            $poinDitukar = max(0, (int) ($data['poin_ditukar'] ?? 0));
            $potonganPoin = 0.0;
            if ($poinDitukar > 0) {
                if ($pelanggan->is_walk_in) {
                    throw new Exception('Pelanggan Umum tidak memiliki poin member.');
                }
                if ($pelanggan->poin < $poinDitukar) {
                    throw new Exception("Poin member tidak mencukupi (Tersedia: {$pelanggan->poin} poin).");
                }
                $potonganPoin = min(max(0.0, $subtotalBelanja - $nominalDiskon), $poinDitukar * 100.0);
                $nominalDiskon += $potonganPoin;
            }

            $totalAkhir = max(0.0, $subtotalBelanja - $nominalDiskon);
            $kodeTransaksi = $this->generateKodeTransaksi($tanggal);

            $isPending = ! empty($data['is_pending']);

            $paymentInfo = PaymentGatewayHelper::authorizePayment(
                (string) ($data['metode_pembayaran'] ?? 'TUNAI'),
                $totalAkhir,
                $isPending ? 0.0 : (float) ($data['nominal_bayar'] ?? $totalAkhir),
                isset($data['payment_token']) ? (string) $data['payment_token'] : null,
                isset($data['payment_channel']) ? (string) $data['payment_channel'] : null
            );

            // Jika bayar menggunakan Saldo Kartu RFID Member, validasi & potong saldo member (langsung LUNAS)
            if (
                $paymentInfo['metode_pembayaran'] === 'SALDO_MEMBER' ||
                $paymentInfo['payment_channel'] === 'SALDO MEMBER RFID'
            ) {
                if ($pelanggan->is_walk_in) {
                    throw new Exception('Pembayaran Saldo Member hanya berlaku untuk Member terdaftar.');
                }
                if ((float) $pelanggan->saldo < $totalAkhir) {
                    throw new Exception('Saldo Kartu RFID Member tidak mencukupi (Saldo: Rp '.number_format((float) $pelanggan->saldo, 0, ',', '.').').');
                }
                $pelanggan->decrement('saldo', $totalAkhir);
                $isPending = false;
            }

            $statusTransaksi = $isPending ? 'pending' : 'selesai';

            // Hitung Poin Loyalitas yang didapat Member (1 poin tiap kelipatan Rp 10.000 — HANYA diberikan jika sudah lunas/selesai)
            $poinDidapat = (! $isPending && ! $pelanggan->is_walk_in && $totalAkhir >= 10000)
                ? (int) floor($totalAkhir / 10000)
                : 0;

            $transaksi = $this->transaksiInterface->create([
                'kode_transaksi' => $kodeTransaksi,
                'tanggal' => $tanggal,
                'id_pelanggan' => $idPelanggan,
                'id_user' => $idUser,
                'subtotal' => $subtotalBelanja,
                'diskon' => $nominalDiskon,
                'total_belanja' => $totalAkhir,
                'metode_pembayaran' => $paymentInfo['metode_pembayaran'],
                'payment_channel' => $paymentInfo['payment_channel'],
                'payment_token' => $paymentInfo['payment_token'],
                'payment_ref' => $isPending ? 'WAITING-PAYMENT' : $paymentInfo['payment_ref'],
                'nominal_bayar' => $isPending ? 0.0 : $paymentInfo['nominal_bayar'],
                'kembalian' => $isPending ? 0.0 : $paymentInfo['kembalian'],
                'poin_didapat' => $poinDidapat,
                'poin_ditukar' => $poinDitukar,
                'status' => $statusTransaksi,
            ]);

            foreach ($detailItems as $detail) {
                DetailTransaksi::create([
                    'id_transaksi' => $transaksi->id_transaksi,
                    'id_produk' => $detail['produk']->id_produk,
                    'jumlah' => $detail['jumlah'],
                    'harga' => $detail['harga'],
                    'subtotal_item' => $detail['subtotal_item'],
                ]);

                if (! $isMysql) {
                    $detail['produk']->decrement('stok', $detail['jumlah']);
                }
            }

            if (! $pelanggan->is_walk_in) {
                if ($isPending) {
                    // Saat masih pending, hanya potong poin yang ditukar agar tidak dipakai ganda; poin baru belum masuk
                    if ($poinDitukar > 0) {
                        $pelanggan->update([
                            'poin' => max(0, (int) $pelanggan->poin - $poinDitukar),
                        ]);
                    }
                } else {
                    $netPoin = $poinDidapat - $poinDitukar;
                    if ($netPoin !== 0) {
                        $pelanggan->update([
                            'poin' => max(0, (int) $pelanggan->poin + $netPoin),
                        ]);
                    }

                    $this->syncTierByCumulativeSpend($pelanggan);
                }
            }

            $this->logInterface->create([
                'id_user' => $idUser,
                'nama_tabel' => 'tbl_transaksi',
                'aktivitas' => $isPending ? 'ORDER PENDING' : 'INSERT TRANSAKSI',
                'id_referensi' => $transaksi->id_transaksi,
                'data_baru' => "kode={$kodeTransaksi}, status={$statusTransaksi}, total={$totalAkhir}, poin=+{$poinDidapat}/-{$poinDitukar}",
            ]);

            return $transaksi->fresh(['pelanggan', 'kasir', 'detail.produk']);
        });
    }

    /**
     * Konfirmasi pelunasan transaksi berstatus 'pending' (Menunggu Pembayaran) menjadi 'selesai' (Lunas).
     * Setelah lunas, poin loyalitas diberikan dan struk resmi dapat dibuka/dicetak.
     *
     * @param  array<string, mixed>  $data
     */
    public function konfirmasiPembayaran(int $idTransaksi, array $data = []): Transaksi
    {
        return DB::transaction(function () use ($idTransaksi, $data): Transaksi {
            $transaksi = Transaksi::with(['pelanggan', 'detail.produk'])->lockForUpdate()->findOrFail($idTransaksi);

            if ($transaksi->status === 'selesai') {
                return $transaksi;
            }

            if ($transaksi->status === 'batal') {
                throw new Exception('Pesanan ini sudah dibatalkan dan tidak dapat dilunasi.');
            }

            $totalAkhir = (float) $transaksi->total_belanja;
            $nominalBayar = isset($data['nominal_bayar']) && (float) $data['nominal_bayar'] > 0
                ? (float) $data['nominal_bayar']
                : $totalAkhir;

            if ($nominalBayar < $totalAkhir) {
                throw new Exception('Nominal pembayaran kurang dari total tagihan (Rp '.number_format($totalAkhir, 0, ',', '.').').');
            }

            $kembalian = max(0.0, $nominalBayar - $totalAkhir);
            $paymentRef = ! empty($data['payment_ref'])
                ? trim((string) $data['payment_ref'])
                : ('PAID-'.strtoupper(substr(md5($transaksi->kode_transaksi.microtime()), 0, 8)));

            $pelanggan = $transaksi->pelanggan;
            $poinDidapat = ($pelanggan && ! $pelanggan->is_walk_in && $totalAkhir >= 10000)
                ? (int) floor($totalAkhir / 10000)
                : 0;

            $updated = $this->transaksiInterface->update($idTransaksi, [
                'id_user' => Auth::id() ?? $transaksi->id_user,
                'status' => 'selesai',
                'nominal_bayar' => $nominalBayar,
                'kembalian' => $kembalian,
                'payment_ref' => $paymentRef,
                'poin_didapat' => $poinDidapat,
            ]);

            if ($pelanggan && ! $pelanggan->is_walk_in) {
                if ($poinDidapat > 0) {
                    $pelanggan->increment('poin', $poinDidapat);
                }
                $this->syncTierByCumulativeSpend($pelanggan->fresh());
            }

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_transaksi',
                'aktivitas' => 'KONFIRMASI PEMBAYARAN',
                'id_referensi' => $transaksi->id_transaksi,
                'data_lama' => "kode={$transaksi->kode_transaksi}, status=pending",
                'data_baru' => "kode={$transaksi->kode_transaksi}, status=selesai, ref={$paymentRef}, poin=+{$poinDidapat}",
            ]);

            return $updated;
        });
    }

    private function syncTierByCumulativeSpend(Pelanggan $pelanggan): void
    {
        $totalAkumulasi = (float) Transaksi::where('id_pelanggan', $pelanggan->id_pelanggan)
            ->where('status', 'selesai')
            ->sum('total_belanja');

        if ($totalAkumulasi >= 1000000 && ! in_array($pelanggan->tier, ['Gold', 'VIP'], true)) {
            $pelanggan->update(['tier' => 'Gold']);
        } elseif ($totalAkumulasi >= 500000 && ($pelanggan->tier === 'Reguler' || empty($pelanggan->tier))) {
            $pelanggan->update(['tier' => 'Silver']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function prosesViaStoredProcedure(array $data): Transaksi
    {
        $tanggal = $data['tanggal'] ?? now()->toDateString();
        $kodeTransaksi = $data['kode_transaksi'] ?? $this->generateKodeTransaksi($tanggal);
        $idPelanggan = ! empty($data['id_pelanggan'])
            ? (int) $data['id_pelanggan']
            : $this->resolveDefaultWalkInPelangganId();

        if (! $this->isMysqlDriver()) {
            return $this->prosesCheckout([
                'id_pelanggan' => $idPelanggan,
                'tanggal' => $tanggal,
                'persen_diskon' => $data['persen_diskon'] ?? 0,
                'items' => [
                    [
                        'id_produk' => $data['id_produk'],
                        'jumlah' => $data['jumlah'],
                    ],
                ],
            ]);
        }

        DB::statement('CALL sp_tambah_transaksi(?, ?, ?, ?, ?, ?, ?, @id_transaksi_baru)', [
            $kodeTransaksi,
            $tanggal,
            $idPelanggan,
            Auth::id(),
            (int) $data['id_produk'],
            (int) $data['jumlah'],
            (float) ($data['persen_diskon'] ?? 0),
        ]);

        $result = DB::selectOne('SELECT @id_transaksi_baru AS id_transaksi');

        return $this->transaksiInterface->find((int) $result->id_transaksi);
    }

    public function voidTransaksi(int $id): Transaksi
    {
        return DB::transaction(function () use ($id): Transaksi {
            $transaksi = $this->transaksiInterface->find($id);

            if ($transaksi->status === 'batal') {
                throw new Exception('Transaksi ini sudah dibatalkan sebelumnya.');
            }

            $statusLama = $transaksi->status;

            foreach ($transaksi->detail as $detail) {
                Produk::where('id_produk', $detail->id_produk)->increment('stok', $detail->jumlah);
            }

            if ($transaksi->pelanggan && ! $transaksi->pelanggan->is_walk_in) {
                $revertPoin = (int) $transaksi->poin_ditukar - (int) $transaksi->poin_didapat;
                if ($revertPoin !== 0) {
                    $transaksi->pelanggan->update([
                        'poin' => max(0, (int) $transaksi->pelanggan->poin + $revertPoin),
                    ]);
                }

                if ($transaksi->metode_pembayaran === 'SALDO_MEMBER') {
                    $transaksi->pelanggan->increment('saldo', (float) $transaksi->total_belanja);
                }
            }

            $updated = $this->transaksiInterface->update($id, [
                'status' => 'batal',
            ]);

            $this->logInterface->create([
                'id_user' => Auth::id(),
                'nama_tabel' => 'tbl_transaksi',
                'aktivitas' => 'VOID TRANSAKSI',
                'id_referensi' => $transaksi->id_transaksi,
                'data_lama' => "kode={$transaksi->kode_transaksi}, status={$statusLama}",
                'data_baru' => "kode={$transaksi->kode_transaksi}, status=batal",
            ]);

            return $updated;
        });
    }

    private function resolveDefaultWalkInPelangganId(): int
    {
        $umum = Pelanggan::firstOrCreate(
            ['nama_pelanggan' => 'Pelanggan Umum (Non-Member)'],
            ['kota' => 'Umum', 'no_telepon' => '-']
        );

        return (int) $umum->id_pelanggan;
    }

    private function hitungTotal(float $harga, int $jumlah, bool $isMysql): float
    {
        if ($isMysql) {
            $row = DB::selectOne('SELECT HitungTotal(?, ?) AS subtotal', [$harga, $jumlah]);

            return (float) $row->subtotal;
        }

        return round($harga * $jumlah, 2);
    }

    private function hitungDiskon(float $subtotal, float $persenDiskon, bool $isMysql): float
    {
        if ($isMysql) {
            $row = DB::selectOne('SELECT HitungDiskon(?, ?) AS diskon', [$subtotal, $persenDiskon]);

            return (float) $row->diskon;
        }

        if ($persenDiskon <= 0) {
            return 0.0;
        }

        if ($persenDiskon > 100) {
            return $subtotal;
        }

        return round($subtotal * ($persenDiskon / 100), 2);
    }

    private function generateKodeTransaksi(string $tanggal): string
    {
        $prefix = 'TRX-'.date('Ym', strtotime($tanggal)).'-';
        $last = Transaksi::where('kode_transaksi', 'like', $prefix.'%')
            ->orderByDesc('id_transaksi')
            ->value('kode_transaksi');

        $nextNumber = $last ? ((int) substr((string) $last, -4) + 1) : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function isMysqlDriver(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
}
