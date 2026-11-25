<?php

use App\Handlers\LaporanAnalisisHandler;
use App\Helpers\ExportHelper;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('welcome page renders public catalog, member tiers, and stats cleanly', function () {
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-WEL-01',
        'nama_produk' => 'Speaker Bluetooth',
        'harga' => 175000,
        'stok' => 8,
    ]);

    $this->get('/')->assertOk();
});

test('member can self-register via /register with locked pelanggan role, receive 10 bonus points, and access member portal', function () {
    $this->get('/register')->assertOk();

    $response = $this->post('/register', [
        'nama_lengkap' => 'Rina Member Baru',
        'username' => 'rina_member',
        'email' => 'rina@member.com',
        'rfid_uid' => '0009364923',
        'kota' => 'Ponorogo',
        'no_telepon' => '081234567899',
        'role' => 'admin', // Attempt privilege escalation — must be ignored and locked to pelanggan
        'password' => 'member123',
        'password_confirmation' => 'member123',
    ]);

    $response->assertRedirect('/member/dashboard');

    $user = User::where('username', 'rina_member')->first();
    expect($user)->not->toBeNull()
        ->and($user->role->value)->toBe('pelanggan')
        ->and($user->rfid_uid)->toBe('0009364923');

    $pelanggan = Pelanggan::where('id_user', $user->id_user)->first();
    expect($pelanggan)->not->toBeNull()
        ->and($pelanggan->poin)->toBe(10)
        ->and($pelanggan->tier)->toBe('Reguler');

    // Member can open /member/dashboard, but cannot access /pos or /admin/dashboard
    $this->actingAs($user)->get('/member/dashboard')->assertOk();
    $this->actingAs($user)->get('/pos')->assertForbidden();
    $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();

    // Registered member can purchase products directly via Member Self-Checkout (/member/api/checkout)
    // and automatically receive their 2% Reguler Tier discount + redeem 10 bonus points (Rp 1.000) + earn new points!
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-SELF-01',
        'nama_produk' => 'Headset Bluetooth',
        'harga' => 100000,
        'stok' => 10,
    ]);

    // Guest cannot buy without registering/logging in first
    $this->post('/logout');
    $this->assertGuest();
    $this->postJson('/member/api/checkout', [
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 1]],
    ])->assertUnauthorized();

    $selfBuyRes = $this->actingAs($user)->postJson('/member/api/checkout', [
        'metode_pembayaran' => 'QRIS_MPM',
        'poin_ditukar' => 10,
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 1],
        ],
    ]);

    $selfBuyRes->assertCreated()
        ->assertJsonPath('is_paid', false);

    $trxMember = Transaksi::where('id_pelanggan', $pelanggan->id_pelanggan)->first();
    // Subtotal = 100.000, Diskon Tier 2% = 2.000, Potongan 10 Poin = 1.000 -> Total Diskon = 3.000, Total Akhir = 97.000
    // Selama belum dibayar (status = 'pending'), poin baru BELUM masuk (0) dan struk TIDAK BISA dicetak!
    expect($trxMember)->not->toBeNull()
        ->and($trxMember->status)->toBe('pending')
        ->and($trxMember->subtotal)->toBe(100000.0)
        ->and($trxMember->diskon)->toBe(3000.0)
        ->and($trxMember->total_belanja)->toBe(97000.0)
        ->and($trxMember->poin_didapat)->toBe(0)
        ->and($produk->fresh()->stok)->toBe(9)
        ->and($pelanggan->fresh()->poin)->toBe(0);

    // Mencoba cetak struk atau buka halaman struk sebelum dibayar harus ditolak (422 / 403)
    $kasir = User::factory()->kasir()->create();
    $this->actingAs($kasir)->postJson("/api/transaksi/{$trxMember->id_transaksi}/print-direct")
        ->assertStatus(422);
    $this->actingAs($kasir)->get("/transaksi/{$trxMember->id_transaksi}")
        ->assertForbidden();

    // Member upload foto bukti transfer QRIS -> status TETAP 'pending' (Menunggu Verifikasi Kasir), belum lunas & struk masih terkunci!
    $buktiFile = UploadedFile::fake()->image('bukti-qris.jpg', 300, 300);
    $uploadRes = $this->actingAs($user)->post("/member/api/transaksi/{$trxMember->id_transaksi}/upload-bukti", [
        'bukti_pembayaran' => $buktiFile,
        'payment_ref' => 'GOPAY-RRN-99887766',
    ], ['Accept' => 'application/json']);

    $uploadRes->assertOk()
        ->assertJsonPath('is_paid', false);

    expect($trxMember->fresh()->status)->toBe('pending')
        ->and($trxMember->fresh()->bukti_pembayaran)->not->toBeNull()
        ->and($pelanggan->fresh()->poin)->toBe(0);

    // Setelah Kasir mengecek bukti dan menekan Verifikasi Lunas (/api/transaksi/{id}/confirm-payment),
    // status berubah menjadi 'selesai', poin loyalitas (+9 poin) otomatis masuk, dan struk resmi terbuka!
    $confirmRes = $this->actingAs($kasir)->postJson("/api/transaksi/{$trxMember->id_transaksi}/confirm-payment", [
        'payment_ref' => 'GOPAY-RRN-99887766',
        'nominal_bayar' => 97000,
    ]);

    $confirmRes->assertOk();

    expect($trxMember->fresh()->status)->toBe('selesai')
        ->and($trxMember->fresh()->payment_ref)->toBe('GOPAY-RRN-99887766')
        ->and($trxMember->fresh()->poin_didapat)->toBe(9)
        ->and($pelanggan->fresh()->poin)->toBe(9);

    // Kini setelah diverifikasi lunas oleh Kasir ('selesai'), halaman detail struk sudah bisa dibuka
    $this->actingAs($kasir)->get("/transaksi/{$trxMember->id_transaksi}")
        ->assertOk();

    if ($trxMember->fresh()->bukti_pembayaran) {
        File::delete(public_path('uploads/bukti_pembayaran/'.$trxMember->fresh()->bukti_pembayaran));
    }
});

test('security headers are attached to responses', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('users authenticate by username or rfid card, redirect by role, log login/logout, and block inactive accounts', function () {
    $kasir = User::factory()->kasir()->create([
        'username' => 'kasir_aktif',
        'rfid_uid' => 'RFID-KASIR-99',
        'password_hash' => 'rahasia123',
    ]);

    // 1. Login via Username & Password
    $this->post('/login', [
        'username' => 'kasir_aktif',
        'password' => 'rahasia123',
    ])->assertRedirect('/pos');

    $this->assertAuthenticatedAs($kasir);
    expect(LogAktivitas::where('aktivitas', 'LOGIN')->exists())->toBeTrue();

    $this->post('/logout')->assertRedirect('/login');
    expect(LogAktivitas::where('aktivitas', 'LOGOUT')->exists())->toBeTrue();

    // 2. Login via Tap Kartu RFID Kasir
    $this->post('/login/rfid', [
        'rfid_uid' => 'RFID-KASIR-99',
    ])->assertRedirect('/pos');

    $this->assertAuthenticatedAs($kasir);
    expect(LogAktivitas::where('aktivitas', 'LOGIN RFID')->exists())->toBeTrue();
    $this->post('/logout');

    // 3. Inactive account blocked on both password and RFID login
    User::factory()->kasir()->inactive()->create([
        'username' => 'kasir_nonaktif',
        'rfid_uid' => 'RFID-NONAKTIF-01',
        'password_hash' => 'rahasia123',
    ]);

    $this->post('/login', [
        'username' => 'kasir_nonaktif',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('username');

    $this->post('/login/rfid', [
        'rfid_uid' => 'RFID-NONAKTIF-01',
    ])->assertSessionHasErrors('rfid_uid');

    $this->assertGuest();
});

test('user can update profile and change password with audit logging', function () {
    $admin = User::factory()->admin()->create([
        'nama_lengkap' => 'Admin Lama',
        'username' => 'admin_test',
        'password_hash' => 'password123',
    ]);

    $this->actingAs($admin)->put('/profil', [
        'nama_lengkap' => 'Admin Baru',
        'username' => 'admin_test',
    ])->assertRedirect();

    expect($admin->fresh()->nama_lengkap)->toBe('Admin Baru');
    expect(LogAktivitas::where('aktivitas', 'UPDATE PROFIL')->exists())->toBeTrue();

    $this->actingAs($admin)->put('/profil/password', [
        'current_password' => 'password123',
        'password' => 'passwordBaru123',
        'password_confirmation' => 'passwordBaru123',
    ])->assertRedirect();

    expect(LogAktivitas::where('aktivitas', 'UBAH PASSWORD')->exists())->toBeTrue();
});

test('admin can create product with scanned barcode and image upload', function () {
    $admin = User::factory()->admin()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    $fakeImage = UploadedFile::fake()->image('mouse.jpg', 120, 120);

    $this->actingAs($admin)->post('/admin/produk', [
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => '8991002101234',
        'nama_produk' => 'Mouse Gaming RGB',
        'harga' => 250000,
        'stok' => 15,
        'gambar' => $fakeImage,
    ])->assertRedirect();

    $produk = Produk::where('kode_produk', '8991002101234')->first();
    expect($produk)->not->toBeNull()
        ->and($produk->gambar)->not->toBeNull()
        ->and($produk->gambar_url)->toStartWith('/uploads/produk/');

    if ($produk?->gambar) {
        File::delete(public_path('uploads/produk/'.$produk->gambar));
    }
});

test('excel and pdf exports work and neutralize spreadsheet formula injection', function () {
    expect(ExportHelper::sanitizeCell('=CMD|\' /C calc\'!A0'))->toStartWith("'=CMD");
    expect(ExportHelper::sanitizeCell('+SUM(A1:A2)'))->toStartWith("'+SUM");
    expect(ExportHelper::sanitizeCell('@SUM(1,2)'))->toStartWith("'@SUM");

    $admin = User::factory()->admin()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-EXP-01',
        'nama_produk' => '=HYPERLINK("http://evil.tld")',
        'harga' => 100000,
        'stok' => 10,
    ]);

    $excelResponse = $this->actingAs($admin)->get('/export/produk/excel');
    $excelResponse->assertOk();
    expect($excelResponse->getContent())->toContain("'=HYPERLINK");

    $pdfResponse = $this->actingAs($admin)->get('/export/produk/pdf');
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($pdfResponse->headers->get('Content-Disposition'))->toContain('.pdf')
        ->and($pdfResponse->getContent())->toStartWith('%PDF-1.4')
        ->and($pdfResponse->getContent())->toContain('PRD-EXP-01');
});

test('rbac matrix enforces admin, kasir, and owner permissions accurately', function () {
    $admin = User::factory()->admin()->create();
    $kasir = User::factory()->kasir()->create();
    $owner = User::factory()->owner()->create();

    $kategori = Kategori::create(['nama_kategori' => 'Elektronik', 'deskripsi' => 'Aksesori']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-001',
        'nama_produk' => 'Mouse Wireless',
        'harga' => 150000,
        'stok' => 10,
    ]);
    $pelanggan = Pelanggan::create([
        'nama_pelanggan' => 'Andi',
        'kota' => 'Ponorogo',
        'no_telepon' => '081234567801',
    ]);

    $this->actingAs($admin)->get('/pos')->assertForbidden();
    $this->actingAs($admin)->get('/owner/dashboard')->assertForbidden();

    // Kasir can register new pelanggan with RFID card via JSON API, but cannot manage master produk/kategori or export audit log
    $this->actingAs($kasir)->postJson('/api/pelanggan', [
        'rfid_uid' => 'RFID-MEMBER-777',
        'nama_pelanggan' => 'Budi RFID',
        'kota' => 'Madiun',
        'no_telepon' => '081234567802',
    ])->assertCreated();

    expect(Pelanggan::where('rfid_uid', 'RFID-MEMBER-777')->exists())->toBeTrue();

    $this->actingAs($kasir)->post('/admin/kategori', ['nama_kategori' => 'ATK'])->assertForbidden();
    $this->actingAs($kasir)->get('/audit-log')->assertForbidden();
    $this->actingAs($kasir)->get('/export/audit-log/excel')->assertForbidden();

    // Owner is strictly read-only
    $this->actingAs($owner)->post('/pelanggan', [
        'nama_pelanggan' => 'Should Fail',
        'kota' => 'Solo',
    ])->assertForbidden();
    $this->actingAs($owner)->post('/transaksi', [
        'id_pelanggan' => $pelanggan->id_pelanggan,
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 1]],
    ])->assertForbidden();
});

test('kasir can process pos checkout for walk-in general customer without specifying id_pelanggan', function () {
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-002',
        'nama_produk' => 'Keyboard Mechanical',
        'harga' => 450000,
        'stok' => 10,
    ]);

    $response = $this->actingAs($kasir)->postJson('/api/transaksi', [
        'persen_diskon' => 10,
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 2],
        ],
    ]);

    $response->assertCreated();

    $transaksi = Transaksi::with(['pelanggan', 'detail'])->first();
    expect($transaksi)->not->toBeNull()
        ->and($transaksi->subtotal)->toBe(900000.0)
        ->and($transaksi->diskon)->toBe(90000.0)
        ->and($transaksi->total_belanja)->toBe(810000.0)
        ->and($transaksi->pelanggan->nama_pelanggan)->toBe('Pelanggan Umum (Non-Member)')
        ->and($transaksi->status)->toBe('selesai')
        ->and($transaksi->id_user)->toBe($kasir->id_user)
        ->and($transaksi->detail)->toHaveCount(1);

    expect($produk->fresh()->stok)->toBe(8);
    expect(LogAktivitas::where('aktivitas', 'INSERT TRANSAKSI')->exists())->toBeTrue();
});

test('checkout rolls back completely when requested quantity exceeds stock', function () {
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-002',
        'nama_produk' => 'Keyboard Mechanical',
        'harga' => 450000,
        'stok' => 5,
    ]);

    $this->actingAs($kasir)->postJson('/api/transaksi', [
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 99],
        ],
    ])->assertUnprocessable();

    expect(Transaksi::count())->toBe(0);
    expect($produk->fresh()->stok)->toBe(5);
});

test('admin can void transaction, restore stock, and record audit trail', function () {
    $admin = User::factory()->admin()->create();
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'ATK']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-003',
        'nama_produk' => 'Buku Catatan A5',
        'harga' => 25000,
        'stok' => 20,
    ]);

    $this->actingAs($kasir)->postJson('/api/transaksi', [
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 4]],
    ]);

    $transaksi = Transaksi::firstOrFail();
    expect($produk->fresh()->stok)->toBe(16);

    $this->actingAs($admin)
        ->patch(route('admin.transaksi.void', $transaksi->id_transaksi))
        ->assertRedirect();

    expect($transaksi->fresh()->status)->toBe('batal');
    expect($produk->fresh()->stok)->toBe(20);
    expect(LogAktivitas::where('aktivitas', 'VOID TRANSAKSI')->exists())->toBeTrue();
});

test('laporan analisis handler returns view, multiple join, subquery, and explain plan', function () {
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Minuman']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-004',
        'nama_produk' => 'Kopi Botol 250ml',
        'harga' => 12000,
        'stok' => 40,
    ]);
    $pelanggan1 = Pelanggan::create(['nama_pelanggan' => 'Andi', 'kota' => 'Ponorogo']);
    $pelanggan2 = Pelanggan::create(['nama_pelanggan' => 'Dewi', 'kota' => 'Ponorogo']);

    $this->actingAs($kasir)->postJson('/api/transaksi', [
        'id_pelanggan' => $pelanggan1->id_pelanggan,
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 5]],
    ]);

    $handler = app(LaporanAnalisisHandler::class);

    $laporanView = $handler->getLaporanView();
    $laporanDetail = $handler->getLaporanDetailMultipleJoin();
    $analisis = $handler->getAnalisisSubquery();
    $explain = $handler->getExplainTransaksi($pelanggan1->id_pelanggan);

    expect($laporanView['laporan_penjualan'])->toHaveCount(1)
        ->and($laporanView['total_belanja_pelanggan'])->toHaveCount(2)
        ->and($laporanDetail)->toHaveCount(1)
        ->and($laporanDetail->first()->nama_kategori)->toBe('Minuman')
        ->and($analisis['belum_transaksi'])->toHaveCount(1)
        ->and($analisis['belum_transaksi'][0]->id_pelanggan)->toBe($pelanggan2->id_pelanggan)
        ->and($analisis['produk_terlaris'])->toHaveCount(1)
        ->and($explain)->not->toBeEmpty();
});

test('kasir can process payment gateway via cpm barcode scan and qris mpm', function () {
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Minuman']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-CPM-01',
        'nama_produk' => 'Teh Kotak 250ml',
        'harga' => 5000,
        'stok' => 30,
    ]);

    // 1. Scan Barcode/QR Kode Bayar di HP Pembeli (contoh: QR BRI dengan kode NNS 93600002 / ID.CO.BRI)
    $cpmResponse = $this->actingAs($kasir)->postJson('/api/transaksi', [
        'metode_pembayaran' => 'SCAN_CPM',
        'payment_channel' => 'AUTO',
        'payment_token' => '00020101021226580014ID.CO.BRI.WWW0118936000020100088991',
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 3],
        ],
    ]);

    $cpmResponse->assertCreated();
    $trxCpm = Transaksi::latest('id_transaksi')->first();
    expect($trxCpm)->not->toBeNull()
        ->and($trxCpm->metode_pembayaran)->toBe('SCAN_CPM')
        ->and($trxCpm->payment_channel)->toBe('QR BANK BRI (BRIMO)')
        ->and($trxCpm->payment_ref)->toStartWith('BRI-')
        ->and($trxCpm->nominal_bayar)->toBe(15000.0)
        ->and($trxCpm->kembalian)->toBe(0.0);

    // 2. Endpoint pembuatan QRIS Dinamis (Xendit / EMVCo Bank BRI)
    $qrisInit = $this->actingAs($kasir)->postJson('/api/payment/xendit-qris', [
        'kode_transaksi' => 'TRX-202611-0099',
        'amount' => 10000,
    ]);
    $qrisInit->assertOk()->assertJsonPath('ok', true);

    // 3. QRIS Dinamis Toko (MPM)
    $mpmResponse = $this->actingAs($kasir)->postJson('/api/transaksi', [
        'metode_pembayaran' => 'QRIS_MPM',
        'payment_channel' => 'QRIS XENDIT / BANK BRI',
        'payment_token' => 'QRIS:TRX-002:10000',
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 2],
        ],
    ]);

    $mpmResponse->assertCreated();
    $trxMpm = Transaksi::latest('id_transaksi')->first();
    expect($trxMpm->metode_pembayaran)->toBe('QRIS_MPM')
        ->and($trxMpm->payment_channel)->toBe('QRIS XENDIT / BANK BRI')
        ->and($trxMpm->payment_ref)->toStartWith('BRI-');
});

test('cashier shift reports are strictly isolated per kasir while admin can filter per kasir', function () {
    $admin = User::factory()->admin()->create();
    $kasir1 = User::factory()->kasir()->create(['nama_lengkap' => 'Kasir Shift Pagi', 'username' => 'kasir_pagi']);
    $kasir2 = User::factory()->kasir()->create(['nama_lengkap' => 'Kasir Shift Malam', 'username' => 'kasir_malam']);
    $kategori = Kategori::create(['nama_kategori' => 'ATK']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-SHIFT-01',
        'nama_produk' => 'Pulpen Hitam',
        'harga' => 5000,
        'stok' => 50,
    ]);

    $this->actingAs($kasir1)->postJson('/api/transaksi', [
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 2]],
    ])->assertCreated();

    $this->actingAs($kasir2)->postJson('/api/transaksi', [
        'items' => [['id_produk' => $produk->id_produk, 'jumlah' => 4]],
    ])->assertCreated();

    $trx1 = Transaksi::where('id_user', $kasir1->id_user)->firstOrFail();
    $trx2 = Transaksi::where('id_user', $kasir2->id_user)->firstOrFail();

    // Kasir 1 export Excel hanya berisi transaksi Kasir 1 (tidak berisi transaksi Kasir 2)
    $exportKasir1 = $this->actingAs($kasir1)->get('/export/laporan-view/excel')->assertOk();
    expect($exportKasir1->getContent())->toContain($trx1->kode_transaksi)
        ->not->toContain($trx2->kode_transaksi);

    // Kasir 2 export PDF hanya berisi transaksi Kasir 2 (tidak berisi transaksi Kasir 1)
    $exportKasir2 = $this->actingAs($kasir2)->get('/export/laporan-view/pdf')->assertOk();
    expect($exportKasir2->getContent())->toContain($trx2->kode_transaksi)
        ->not->toContain($trx1->kode_transaksi);

    // Admin filter berdasarkan id_user Kasir 1 hanya melihat transaksi Kasir 1
    $exportAdminFilter = $this->actingAs($admin)->get('/export/laporan-view/excel?id_user='.$kasir1->id_user)->assertOk();
    expect($exportAdminFilter->getContent())->toContain($trx1->kode_transaksi)
        ->not->toContain($trx2->kode_transaksi);
});

test('member earns loyalty points, redeems points, upgrades tier, and pays with rfid member balance', function () {
    $kasir = User::factory()->kasir()->create();
    $kategori = Kategori::create(['nama_kategori' => 'Elektronik']);
    $produk = Produk::create([
        'id_kategori' => $kategori->id_kategori,
        'kode_produk' => 'PRD-MBR-01',
        'nama_produk' => 'Monitor LED 24 Inch',
        'harga' => 600000,
        'stok' => 10,
    ]);

    $member = Pelanggan::create([
        'rfid_uid' => 'RFID-VIP-888',
        'nama_pelanggan' => 'Siti Member',
        'kota' => 'Ponorogo',
        'no_telepon' => '081299998888',
        'tier' => 'Reguler',
        'poin' => 50,
        'saldo' => 100000,
    ]);

    // 1. Kasir performs Top-Up Saldo RFID Member (+Rp 600.000 -> total Rp 700.000)
    $this->actingAs($kasir)->postJson("/api/pelanggan/{$member->id_pelanggan}/topup", [
        'nominal' => 600000,
    ])->assertOk();

    expect($member->fresh()->saldo)->toBe(700000.0);

    // 2. Checkout using SALDO_MEMBER + redeem 50 points (Rp 5.000 extra discount) + 2% Reguler tier discount (Rp 12.000)
    // Subtotal = 600.000, Diskon = 12.000 + 5.000 = 17.000, Total Akhir = 583.000
    // Earned points = floor(583.000 / 10.000) = 58 points
    $checkoutRes = $this->actingAs($kasir)->postJson('/api/transaksi', [
        'id_pelanggan' => $member->id_pelanggan,
        'persen_diskon' => 2,
        'poin_ditukar' => 50,
        'metode_pembayaran' => 'SALDO_MEMBER',
        'items' => [
            ['id_produk' => $produk->id_produk, 'jumlah' => 1],
        ],
    ]);

    $checkoutRes->assertCreated();

    $memberFresh = $member->fresh();
    expect($memberFresh->saldo)->toBe(117000.0)
        ->and($memberFresh->poin)->toBe(58)
        ->and($memberFresh->tier)->toBe('Silver'); // Cumulative >= 500.000 auto-upgraded to Silver!
});
