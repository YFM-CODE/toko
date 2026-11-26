<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\Pengguna\DashboardController as MemberDashboardController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Halaman Depan / Welcome (Gaya Modern dengan Arsitektur /yz-1)
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

// Pengarah Otomatis sesuai Spesifikasi frontend.md & login,logout,profil.md:
// kasir -> /pos, admin -> /admin/dashboard, owner -> /laporan
Route::get('/dashboard', function () {
    $role = Auth::user()->role->value;

    return match ($role) {
        'admin' => redirect()->route('admin.dashboard'),
        'kasir' => redirect()->route('pos.index'),
        'owner' => redirect()->route('laporan.index'),
        'pelanggan' => redirect()->route('member.dashboard'),
        default => redirect()->route('login'),
    };
})->middleware(['auth'])->name('dashboard');

// 1. Grup Khusus Admin (Master Data CRUD, User Management, Void Transaksi)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/kategori', [KategoriController::class, 'index'])->name('kategori.index');
    Route::post('/kategori', [KategoriController::class, 'store'])->name('kategori.store');
    Route::put('/kategori/{kategori}', [KategoriController::class, 'update'])->name('kategori.update');
    Route::delete('/kategori/{kategori}', [KategoriController::class, 'destroy'])->name('kategori.destroy');

    Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::post('/produk', [ProdukController::class, 'store'])->name('produk.store');
    Route::put('/produk/{produk}', [ProdukController::class, 'update'])->name('produk.update');
    Route::post('/produk/{produk}', [ProdukController::class, 'update'])->name('produk.update.post');
    Route::delete('/produk/{produk}', [ProdukController::class, 'destroy'])->name('produk.destroy');

    Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');
    Route::post('/pelanggan', [PelangganController::class, 'store'])->name('pelanggan.store');
    Route::put('/pelanggan/{pelanggan}', [PelangganController::class, 'update'])->name('pelanggan.update');
    Route::delete('/pelanggan/{pelanggan}', [PelangganController::class, 'destroy'])->name('pelanggan.destroy');

    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

    Route::patch('/transaksi/{id}/void', [TransaksiController::class, 'void'])->name('transaksi.void');
});

// 2. Grup Khusus Kasir (Halaman POS /pos & Proses Transaksi Penjualan)
Route::middleware(['auth', 'role:kasir'])->group(function () {
    Route::get('/pos', [TransaksiController::class, 'pos'])->name('pos.index');
    Route::get('/kasir/dashboard', [KasirDashboardController::class, 'index'])->name('kasir.dashboard');
    Route::get('/transaksi/create', [TransaksiController::class, 'create'])->name('transaksi.create');
    Route::post('/transaksi', [TransaksiController::class, 'store'])->name('transaksi.store');
    Route::post('/api/transaksi', [TransaksiController::class, 'store'])->name('api.transaksi.store');
});

// Webhook Callback Resmi Duitku (Tanpa Session/CSRF — Verifikasi Signature MD5)
Route::post('/api/payment/duitku/callback', [TransaksiController::class, 'duitkuCallback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('api.payment.duitku-callback');

// Endpoint Inquiry VA/QRIS & Cek Status Transaksi Duitku (Bisa dipanggil Kasir, Admin, maupun Member)
Route::middleware(['auth', 'role:admin,kasir,pelanggan'])->group(function () {
    Route::post('/api/payment/duitku-qris', [TransaksiController::class, 'createQris'])->name('api.payment.duitku-qris');
    Route::post('/api/payment/duitku/check-status', [TransaksiController::class, 'checkDuitkuStatus'])->name('api.payment.duitku-check-status');
});

// Webhook Callback Resmi Duitku (Tanpa Session/CSRF — Verifikasi Signature MD5)
Route::post('/api/payment/duitku/callback', [TransaksiController::class, 'duitkuCallback'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('api.payment.duitku-callback');

// Cek Status Transaksi Duitku (Bisa dipanggil Kasir, Admin, maupun Member)
Route::middleware(['auth', 'role:admin,kasir,pelanggan'])->group(function () {
    Route::post('/api/payment/duitku/check-status', [TransaksiController::class, 'checkDuitkuStatus'])->name('api.payment.duitku-check-status');
});

// 3. Grup Khusus Owner / Manajer (Dashboard Eksekutif Read-Only)
Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
});

// 4. Grup Admin & Kasir (Pendaftaran Pelanggan Baru, Top-Up Saldo RFID Member & Konfirmasi Lunas Pesanan Pending)
Route::middleware(['auth', 'role:admin,kasir'])->group(function () {
    Route::post('/pelanggan', [PelangganController::class, 'store'])->name('pelanggan.store');
    Route::post('/api/pelanggan', [PelangganController::class, 'store'])->name('api.pelanggan.store');
    Route::post('/pelanggan/{id}/topup', [PelangganController::class, 'topUp'])->name('pelanggan.topup');
    Route::post('/api/pelanggan/{id}/topup', [PelangganController::class, 'topUp'])->name('api.pelanggan.topup');
    Route::patch('/transaksi/{id}/confirm-payment', [TransaksiController::class, 'confirmPayment'])->name('transaksi.confirm-payment');
    Route::post('/api/transaksi/{id}/confirm-payment', [TransaksiController::class, 'confirmPayment'])->name('api.transaksi.confirm-payment');
});

// 4b. Grup Khusus Member / Pelanggan (Portal Member, Belanja Mandiri, Kartu RFID/QR Digital, Poin, Saldo & Riwayat Struk)
Route::middleware(['auth', 'role:pelanggan'])->prefix('member')->name('member.')->group(function () {
    Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('dashboard');
    Route::get('/belanja', [MemberDashboardController::class, 'index'])->name('belanja');
    Route::post('/checkout', [MemberDashboardController::class, 'checkout'])->name('checkout');
    Route::post('/api/checkout', [MemberDashboardController::class, 'checkout'])->name('api.checkout');
    Route::post('/api/transaksi/{id}/upload-bukti', [MemberDashboardController::class, 'uploadBukti'])->name('api.upload-bukti');
});

// 5. Grup Admin & Owner (Audit Log Read-Only: /audit-log & /log)
Route::middleware(['auth', 'role:admin,owner'])->group(function () {
    Route::get('/audit-log', [LogController::class, 'index'])->name('audit-log.index');
    Route::get('/log', [LogController::class, 'index'])->name('log.index');
});

// 6. Grup Bersama Semua Role Login (Read Master Data, Read Transaksi, Read Laporan, Export Excel/PDF, Profil)
Route::middleware(['auth', 'role:admin,kasir,owner'])->group(function () {
    Route::get('/kategori', [KategoriController::class, 'index'])->name('kategori.index');
    Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');

    Route::get('/transaksi', [TransaksiController::class, 'index'])->name('transaksi.index');
    Route::get('/transaksi/{id}', [TransaksiController::class, 'show'])->name('transaksi.show');
    Route::post('/api/transaksi/{id}/print-direct', [TransaksiController::class, 'printDirect'])->name('api.transaksi.print-direct');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/export/{modul}/{format}', [ExportController::class, 'export'])->name('export.download');
});

// 7. Grup Profil untuk Semua User Terautentikasi (Termasuk Member / Pelanggan)
Route::middleware(['auth', 'role:admin,kasir,owner,pelanggan'])->group(function () {
    Route::get('/profil', [ProfileController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
