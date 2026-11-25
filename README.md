# YAZID ELEKTRONIK (`tokoku`) — Sistem Informasi POS Ritel, Member RFID, QRIS & Back-Office

Sistem Informasi Point of Sale (POS) Minimarket, Katalog Belanja Mandiri Member (*Hybrid Retail & Self-Order*), Manajemen Inventori, Kartu Loyalitas RFID, Payment Gateway QRIS, dan Pencetakan Struk Thermal Langsung (*1-Click Silent Direct Print*) yang dibangun di atas **PHP 8.5, Laravel, Inertia.js (Vue 3), Tailwind CSS, dan MySQL/MariaDB** dengan menerapkan standar rekayasa arsitektur bersih **`/yz-1`**.

---

## Daftar Isi

1. [Sorotan Fitur Lengkap (End-to-End)](#1-sorotan-fitur-lengkap-end-to-end)
2. [Standar Rekayasa & Arsitektur Clean Code (`/yz-1`)](#2-standar-rekayasa--arsitektur-clean-code-yz-1)
3. [Arsitektur Database & Objek SQL Tingkat Lanjut](#3-arsitektur-database--objek-sql-tingkat-lanjut)
4. [Matriks Hak Akses 4 Role (RBAC)](#4-matriks-hak-akses-4-role-rbac)
5. [Alur Pembelian Hybrid, Upload Bukti & Verifikasi Kasir](#5-alur-pembelian-hybrid-upload-bukti--verifikasi-kasir)
6. [Integrasi Hardware: Printer Thermal, Scanner Barcode & Reader RFID](#6-integrasi-hardware-printer-thermal-scanner-barcode--reader-rfid)
7. [Payment Gateway: QRIS GoPay Merchant, Xendit, Auto-Detect Bank NNS & Saldo RFID](#7-payment-gateway-qris-gopay-merchant-xendit-auto-detect-bank-nns--saldo-rfid)
8. [Sistem Desain UI Ganda (Hostinger Landing Page & Minimarket POS)](#8-sistem-desain-ui-ganda-hostinger-landing-page--minimarket-pos)
9. [Panduan Instalasi & Konfigurasi](#9-panduan-instalasi--konfigurasi)
10. [Daftar Akun Demo & Kartu RFID Bawaan (`DatabaseSeeder`)](#10-daftar-akun-demo--kartu-rfid-bawaan-databaseseeder)
11. [Pengujian Otomatis (`Pest`) & Standar Kode (`Pint`)](#11-pengujian-otomatis-pest--standar-kode-pint)

---

## 1. Sorotan Fitur Lengkap (End-to-End)

### A. Layar Kasir POS Minimarket (`100vh` Tanpa Scrollbar)
- **Antarmuka Kasir Ritel Profesional (Ala Indomaret / Alfamart):** Seluruh elemen (Header, Input Scan PLU/Barcode, Tabel Keranjang, Preview Produk, Panel Member RFID, Kalkulator Diskon/Poin, Pilihan Metode Bayar, hingga tombol `[F4] BAYAR & CETAK STRUK`) terukur pas dalam **1 layar (`100vh`)** tanpa *scrollbar* luar.
- **Shortcut Keyboard Fisik Kasir:**
  - `F1`: Cari / Pilih Produk dari Modal Katalog.
  - `F2`: Fokus ke Input Scan PLU / Barcode / QR Produk.
  - `F3`: Fokus ke Nominal Uang Tunai / Metode Pembayaran.
  - `F4`: Proses Checkout & Cetak Struk.
  - `F5`: Buka Kamera Pemindai QR / Barcode (WebRTC + `BarcodeDetector` / `jsQR`).
  - `F8`: Reset / Bersihkan Keranjang.
- **Dukungan Pembeli Walk-In & Member Terdaftar:**
  - Kasir dapat melayani **Pelanggan Umum (Non-Member)** secara instan tanpa perlu registrasi.
  - Kasir juga dapat **Tap Kartu RFID Member**, memilih Member dari daftar, **mendaftarkan Member baru + Kartu RFID langsung dari modal POS**, atau melakukan **Top-Up Saldo Kartu RFID Member** di tempat.

### B. Program Loyalitas Member, Poin, Tier Otomatis & Saldo Deposit RFID
- **3 Tingkatan Tier Member Otomatis Berdasarkan Akumulasi Belanja Lunas (`status = selesai`):**
  - **Reguler:** Aktif otomatis saat pendaftaran (`Diskon Otomatis 2%` di setiap transaksi).
  - **Silver:** Otomatis naik saat akumulasi belanja $\ge \text{Rp } 500.000$ (`Diskon Otomatis 5%`).
  - **Gold / VIP:** Otomatis naik saat akumulasi belanja $\ge \text{Rp } 1.000.000$ (`Diskon Otomatis 10%`).
- **Poin Loyalitas & Penukaran Poin:**
  - Bonus langsung **+10 Poin** saat registrasi akun Member baru (`/register`).
  - Setiap kelipatan belanja bersih **Rp 10.000** (yang sudah lunas) otomatis mendapatkan **+1 Poin**.
  - **1 Poin = Rp 100** potongan belanja tambahan yang bisa ditukarkan saat checkout di POS maupun saat belanja mandiri di Portal Member.
- **Saldo Deposit Kartu RFID Member:**
  - Member memiliki saldo elektronik yang tersimpan di akun/kartu RFID (`tbl_pelanggan.saldo`).
  - Dapat di-*Top-Up* melalui Kasir atau Admin, dan digunakan untuk pembayaran **1-Tap Saldo Member RFID** yang langsung lunas tanpa uang tunai.

### C. Registrasi Mandiri (`/register`) & Portal Belanja Mandiri Member (`/member/dashboard`)
- **Katalog Publik (`Welcome.vue`) dengan Auto-Cart:**
  - Pengunjung yang klik **`Daftar & Beli`** pada produk di halaman depan diarahkan ke `/register?buy={id_produk}`.
  - Setelah selesai registrasi (dikunci aman ke role `pelanggan`), produk tersebut **otomatis masuk ke keranjang belanja Member**.
- **Portal Member Lengkap:**
  - Menampilkan **Kartu Member Digital** (lengkap dengan QR Code siap scan di kasir & UID Kartu RFID), ringkasan Poin, Saldo RFID, progres kenaikan Tier, dan Total Penghematan.
  - **Tab Katalog & Belanja Mandiri (*Self-Checkout*):** Member dapat memilih produk yang stoknya tersedia, mengaktifkan potongan **Diskon Tier + Tukar Poin**, dan memilih metode bayar (**QRIS GoPay Merchant**, **Saldo RFID**, atau **Bayar di Kasir**).
  - **Penguncian Struk Sebelum Lunas (`pending` vs `selesai`):**
    - Jika membayar dengan **Saldo RFID** (dan saldo cukup), transaksi langsung berstatus **`selesai` (LUNAS)** dan struk langsung terbuka.
    - Jika membayar dengan **QRIS** atau **Bayar di Kasir**, transaksi berstatus **`pending` (BELUM DIBAYAR)** — **belum dianggap sukses membeli, poin belum masuk, dan tombol cetak/lihat struk DIKUNCI**.
    - Member mengunggah **foto/screenshot bukti transfer QRIS**, lalu status menjadi **`MENUNGGU VERIFIKASI KASIR`** sampai diverifikasi lunas oleh Kasir.

### D. Laporan Penjualan, Isolasi Shift Kasir & Export Native (Excel & A4 PDF)
- **Rekapitulasi Shift & Setoran Per Kasir (`getRekapPerKasir`):**
  - Menampilkan seluruh akun ber-role **`kasir`** (termasuk Kasir yang jumlah transaksinya masih `0`), lengkap dengan rincian Jumlah Struk, Total Tunai (Cash), Total Non-Tunai (QRIS/RFID), Total Diskon, dan Total Omzet Shift.
  - Akun ber-role `pelanggan` tidak pernah tercampur ke dalam tabel shift kasir.
- **Isolasi Shift Per Kasir:**
  - Saat login sebagai **Kasir** (`kasir1`, `kasir2`, dst.), laporan dan export di `/laporan` otomatis terkunci ke transaksi shift milik Kasir tersebut.
  - Di halaman `/transaksi`, Kasir dapat melihat transaksi shift miliknya **ditambah** seluruh pesanan `pending` dari Member untuk diverifikasi.
  - Saat login sebagai **Admin** atau **Owner**, tersedia dropdown filter **`Semua Kasir / Pilih Kasir`**.
- **5 Tab Analisis & Laporan SQL di `/laporan`:**
  1. `VIEW_LAPORAN_PENJUALAN` (Rekapitulasi transaksi + Rekap Shift Kasir).
  2. `MULTIPLE JOIN (5 TABEL)` (Rincian per baris item produk, kategori, pelanggan, dan transaksi).
  3. `VIEW & FUNCTION PELANGGAN` (Klasifikasi belanja pelanggan menggunakan Stored Function `KategoriPelanggan`).
  4. `ANALISIS SUBQUERY` (Transaksi di atas rata-rata `AVG`, Top 5 transaksi terbesar, Top 10 produk terlaris, pelanggan `NOT EXISTS` belum bertransaksi, dan produk `NOT EXISTS` belum terjual).
  5. `AUDIT LOG & EXPLAIN INDEX` (Log aktivitas/trigger database & hasil `EXPLAIN` penggunaan composite index `idx_transaksi_pelanggan_tgl`).
- **Export Excel (`.xls`) & Native A4 Landscape PDF (`.pdf`):**
  - Tersedia untuk Master Produk, Data Pelanggan, Laporan View, Laporan Detail 5 Tabel, Klasifikasi Pelanggan, dan Audit Log.
  - **File PDF A4 Murni (`SimplePdfBuilder.php`):** Menghasilkan binary `%PDF-1.4` A4 Landscape langsung terunduh (`Content-Disposition: attachment`) tanpa membuka dialog print thermal browser.

---

## 2. Standar Rekayasa & Arsitektur Clean Code (`/yz-1`)

Proyek ini mematuhi standar arsitektur **`/yz-1`** secara menyeluruh:

```text
app/
├── Enums/
│   └── UserRole.php                  # Enum Role: Admin, Kasir, Owner, Pelanggan
├── Exports/                          # Definisi title(), headings(), collection(), map()
│   ├── AuditLogExport.php
│   ├── LaporanPenjualanExport.php
│   ├── PelangganExport.php
│   └── ProdukExport.php
├── Filters/                          # Pencarian, filter kategori/kota/status/tanggal & sorting
│   ├── BaseFilter.php
│   ├── KategoriFilter.php
│   ├── LogFilter.php
│   ├── PelangganFilter.php
│   ├── ProdukFilter.php
│   ├── TransaksiFilter.php
│   └── UserFilter.php
├── Handlers/                         # Logika bisnis multi-tabel, TCL Transaction, & Analisis SQL
│   ├── LaporanAnalisisHandler.php
│   ├── PelangganHandler.php
│   ├── TransaksiHandler.php
│   └── UserHandler.php
├── Helpers/                          # Mesin Export PDF/Excel, Payment Gateway, & Thermal Printer
│   ├── DirectThermalPrinterHelper.php
│   ├── ExportHelper.php
│   ├── PaginateHelper.php
│   ├── PaymentGatewayHelper.php
│   └── SimplePdfBuilder.php
├── Http/
│   ├── Controllers/                  # Controller ramping dengan Constructor Auto-Wiring
│   ├── Middleware/
│   │   ├── RoleMiddleware.php        # Penjaga akses RBAC per rute
│   │   └── SecurityHeadersMiddleware.php
│   └── Requests/                     # Seluruh validasi mewarisi BaseFormRequest (Anti-XSS/Null-Byte)
│       ├── BaseFormRequest.php
│       ├── CheckoutRequest.php
│       ├── ConfirmPaymentRequest.php
│       ├── KategoriRequest.php
│       ├── PelangganRequest.php
│       ├── ProdukRequest.php
│       ├── ProfileUpdateRequest.php
│       ├── TopUpSaldoRequest.php
│       ├── UpdatePasswordRequest.php
│       ├── UserRequest.php
│       └── Auth/
│           ├── LoginRequest.php
│           ├── RegisterMemberRequest.php
│           └── RfidLoginRequest.php
├── Interfaces/                       # Kontrak Repository (Format: ContohInterface)
│   ├── KategoriInterface.php
│   ├── LogInterface.php
│   ├── PelangganInterface.php
│   ├── ProdukInterface.php
│   ├── TransaksiInterface.php
│   └── UserInterface.php
├── Models/                           # Eloquent Models untuk Tabel Utama
└── Repositories/                     # Implementasi query Eloquent + Filter
```

### Prinsip Utama `/yz-1` yang Diterapkan:
1. **Constructor Dependency Injection & Penamaan Ketat:**
   - Seluruh kontrak di-*bind* terpusat di `AppServiceProvider.php`.
   - Nama Interface menggunakan format `ContohInterface` dan nama properti injeksi **wajib** `$contohInterface` (`$produkInterface`, `$kategoriInterface`, `$pelangganInterface`, `$transaksiInterface`, `$userInterface`, `$logInterface`).
2. **Pemisahan Controller vs Handler vs Repository:**
   - Operasi CRUD 1 tabel murni (`KategoriController`, `ProdukController`, `LogController`) langsung memanggil `$this->contohInterface`.
   - Operasi bisnis multi-tabel, mutasi stok/poin/saldo, dan orkestrasi laporan SQL didelegasikan ke `Handler` di dalam blok `DB::transaction()`.
3. **Keamanan Berlapis (*Security by Default*):**
   - **Anti Brute-Force:** `RateLimiter` pada `LoginRequest`.
   - **Anti Malware, Null-Byte & Script Tag:** `BaseFormRequest::sanitizePayload()` membersihkan `\0` dan tag `<script>`, `<iframe>`, `<object>`, `<?php` secara otomatis tanpa mengubah *casing* (`strtoupper`) input pengguna.
   - **Anti Spreadsheet Formula Injection:** `ExportHelper::sanitizeCell()` menetralkan sel yang diawali `=`, `+`, `-`, `@` dengan tanda kutip tunggal (`'`).
   - **HTTP Security Headers:** `SecurityHeadersMiddleware` menyuntikkan `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-XSS-Protection`, dan `Permissions-Policy`.

---

## 3. Arsitektur Database & Objek SQL Tingkat Lanjut

### A. 6 Tabel Utama + Audit Log
1. **`tbl_users`**: Menyimpan akun `admin`, `kasir`, `owner`, dan `pelanggan` beserta `rfid_uid` unik untuk login cepat 1-Tap RFID.
2. **`tbl_kategori`**: Master kategori produk (`ON DELETE RESTRICT` terhadap `tbl_produk`).
3. **`tbl_produk`**: Master produk, kode PLU/Barcode unik, harga, stok, gambar produk (`/uploads/produk/`), dan composite index `idx_produk_kategori_harga`.
4. **`tbl_pelanggan`**: Profil pelanggan/member, `id_user`, `rfid_uid`, `tier` (`Reguler`/`Silver`/`Gold`), `poin`, `saldo`, dan index `idx_pelanggan_kota`.
5. **`tbl_transaksi`**: Header transaksi penjualan, `kode_transaksi`, `tanggal`, `id_pelanggan`, `id_user` (kasir/pembuat), `subtotal`, `diskon`, `total_belanja`, `metode_pembayaran`, `payment_channel`, `payment_token`, `payment_ref`, `bukti_pembayaran`, `nominal_bayar`, `kembalian`, `poin_didapat`, `poin_ditukar`, `status` (`pending`/`selesai`/`batal`), serta composite index `idx_transaksi_pelanggan_tgl`.
6. **`tbl_detail_transaksi`**: Rincian item produk per transaksi (`ON DELETE CASCADE` dari `tbl_transaksi`, `ON DELETE RESTRICT` terhadap `tbl_produk`).
7. **`tbl_log`**: Audit trail aktivitas pengguna (`LOGIN`, `LOGIN RFID`, `LOGOUT`, `REGISTER MEMBER`, `INSERT TRANSAKSI`, `ORDER PENDING`, `KONFIRMASI PEMBAYARAN`, `VOID TRANSAKSI`, `TOPUP SALDO MEMBER`, dll.) serta log otomatis dari trigger database (`idx_log_tabel_waktu`).

### B. Objek SQL Lanjut (Migrasi `2026_11_21_000001_create_sql_advanced_objects.php`)
- **Database Views:**
  - `view_laporan_penjualan`: Menggabungkan `tbl_transaksi`, `tbl_pelanggan`, dan `tbl_users`.
  - `view_total_belanja_pelanggan`: Rekap akumulasi transaksi & klasifikasi member per pelanggan.
- **Stored Functions:**
  - `HitungTotal(harga, jumlah)`: Menghitung subtotal baris item.
  - `HitungDiskon(total, persen_diskon)`: Menghitung potongan diskon transaksi.
  - `KategoriPelanggan(total_belanja)`: Mengklasifikasikan pelanggan (`Gold >= 1.000.000`, `Silver >= 500.000`, `Reguler < 500.000`).
- **Stored Procedure:**
  - `sp_tambah_transaksi(...)`: Eksekusi transaksi atomik di level database.
- **Database Triggers:**
  - `trg_kurangi_stok` (`AFTER INSERT ON tbl_detail_transaksi`): Mengurangi stok `tbl_produk` secara otomatis saat detail transaksi masuk.
  - `trg_log_stok_produk` (`AFTER UPDATE ON tbl_produk`): Mencatat perubahan stok lama (`OLD.stok`) ke stok baru (`NEW.stok`) secara otomatis ke `tbl_log`.

---

## 4. Matriks Hak Akses 4 Role (RBAC)

| Modul / Fitur | Admin | Kasir | Owner | Pelanggan (Member) |
| :--- | :---: | :---: | :---: | :---: |
| **Layar Kasir POS (`/pos`)** | ❌ | ✅ | ❌ | ❌ |
| **Belanja Mandiri & Upload Bukti (`/member/dashboard`)** | ❌ | ❌ | ❌ | ✅ |
| **Kartu Member QR/RFID Digital & Riwayat Poin** | ❌ | ❌ | ❌ | ✅ |
| **CRUD Master Kategori & Produk (`/admin/kategori`, `/admin/produk`)** | ✅ | 👁️ Read | 👁️ Read | Katalog Aktif |
| **Tambah Member Baru & Top-Up Saldo RFID** | ✅ | ✅ | ❌ | ❌ |
| **Edit / Hapus Member (`/admin/pelanggan`)** | ✅ | ❌ | 👁️ Read | ❌ |
| **Manajemen Akun Staf & RFID (`/admin/users`)** | ✅ | ❌ | ❌ | ❌ |
| **Verifikasi Bukti Pembayaran Member (`pending` $\rightarrow$ `selesai`)** | ✅ | ✅ | ❌ | ❌ |
| **Void / Batalkan Transaksi (`/admin/transaksi/{id}/void`)** | ✅ | ❌ | ❌ | ❌ |
| **Riwayat Transaksi (`/transaksi`)** | Semua | Shift Sendiri + Semua `pending` | Semua | Milik Sendiri |
| **Laporan Penjualan & Export (`/laporan`, `/export/*`)** | Semua Kasir | Terkunci ke Shift Sendiri | Semua Kasir | ❌ |
| **Audit Log (`/audit-log` & Export Audit Log)** | ✅ | ❌ | ✅ | ❌ |
| **Profil & Ubah Password (`/profil`)** | ✅ | ✅ | ✅ | ✅ |

---

## 5. Alur Pembelian Hybrid, Upload Bukti & Verifikasi Kasir

1. **Pembelian Offline di Meja Kasir (`/pos`):**
   - Pembeli *Walk-In* (`Pelanggan Umum`) maupun Member datang ke meja kasir $\rightarrow$ Kasir scan barang $\rightarrow$ bayar Tunai / QRIS / Saldo RFID $\rightarrow$ langsung berstatus **`selesai` (Lunas)** dan cetak struk thermal.
2. **Pembelian Mandiri Online oleh Pengunjung / Member (`/member/dashboard`):**
   - Pengunjung wajib **Register / Login** terlebih dahulu (otomatis menjadi role `pelanggan` + bonus **10 Poin**).
   - **Jika memilih `Saldo RFID`** (dan saldo mencukupi): Saldo langsung terpotong, status langsung **`selesai` (LUNAS)**, poin baru langsung masuk, dan **Struk Resmi langsung terbuka**.
   - **Jika memilih `QRIS GoPay` atau `Bayar di Kasir`**:
     - Pesanan dibuat dengan status **`pending` (`BELUM DIBAYAR`)**.
     - Selama masih `pending`, pesanan **belum sukses membeli**, poin baru **belum diberikan**, dan **Struk Resmi DIKUNCI** (tidak bisa dibuka atau dicetak).
     - Untuk QRIS, Member mengunggah **foto/screenshot bukti transfer** $\rightarrow$ status menjadi **`MENUNGGU VERIFIKASI KASIR`**.
     - **Kasir yang sedang aktif** (misal `Kasir Satu` atau `Kasir Dua`) membuka menu **`Riwayat & Struk Transaksi` (`/transaksi`)**, klik **`Cek Bukti & Verifikasi`**, memeriksa foto bukti transfer Member, lalu menekan **`Verifikasi Lunas & Buka Struk`**.
     - Begitu diverifikasi Kasir: status berubah menjadi **`selesai` (LUNAS)**, `id_user` tercatat atas nama **Kasir yang memverifikasi**, poin loyalitas masuk ke Member, dan **Struk Resmi terbuka** di akun Kasir maupun di Portal Member.

---

## 6. Integrasi Hardware: Printer Thermal, Scanner Barcode & Reader RFID

### A. 1-Click Silent Direct Thermal Printing (`EPSON TM-T82X Receipt`)
- Diimplementasikan pada `app/Helpers/DirectThermalPrinterHelper.php`.
- Mengirim byte stream **RAW ESC/POS** langsung ke Windows Print Spooler (`winspool.drv`) melalui PowerShell tanpa memunculkan dialog print browser:
  - **Logo Raster Monokrom `64x64` (`GS v 0`)** + Header Toko + Garis Putus-Putus.
  - **Mode Cetak Pekat (`ESC E 1` + `ESC G 1` Double-Strike)** agar hasil cetak thermal tajam dan jelas.
  - **Dukungan Kertas `80mm` (38 kolom) & `58mm` (30 kolom)** beserta pengaturan margin kiri hardware (`GS L`).
  - **Kalibrasi Posisi Langsung dari UI (`ReceiptModal.vue`):** Geser kiri/kanan (mm), margin atas, lebar konten, jumlah kolom ESC/POS, dan spasi kiri.
  - **Native QR Code (`GS ( k`)** & **Auto-Cutter (`GS V 66 0`)**.
- Nama printer dapat dikonfigurasi di `.env` melalui `THERMAL_PRINTER_NAME="EPSON TM-T82X Receipt"` (otomatis mendeteksi printer thermal Epson/POS yang terpasang jika nama default tidak ditemukan).

### B. Scanner Barcode USB & Kamera WebRTC (`QrScannerModal.vue`)
- Mendukung **2D/1D Barcode Scanner Fisik USB** (otomatis menangkap input cepat + `Enter` di kolom PLU maupun form tambah produk Admin).
- Mendukung **Kamera Laptop/HP** menggunakan native `BarcodeDetector` API dengan *fallback* otomatis ke `jsQR` + fitur **Unggah Gambar QR dari Galeri/File** apabila kamera tidak tersedia.

### C. RFID Card Reader USB (1-Tap Login & 1-Tap Member)
- Mendukung reader RFID USB HID (seperti R20DC / 13.56MHz / 125kHz) untuk:
  - **1-Tap Login Staf & Member** di halaman `/login`.
  - **1-Tap Klaim Diskon & Bayar Saldo RFID Member** di layar Kasir `/pos`.
  - **1-Tap Registrasi UID Kartu RFID** di `/register`, `/admin/pelanggan`, dan `/admin/users`.

---

## 7. Payment Gateway: QRIS GoPay Merchant, Xendit, Auto-Detect Bank NNS & Saldo RFID

Diimplementasikan pada `app/Helpers/PaymentGatewayHelper.php`:
1. **Official Static QRIS GoPay Merchant (`public/images/qris-toko.jpg`):**
   - Menampilkan QRIS cetak resmi toko (**`YAZID, ELEKTRONIK`**, NMID: `ID1026647000219`, Kode Merchant: `A01`).
2. **Xendit Dynamic QRIS API:**
   - Terintegrasi dengan konfigurasi `XENDIT_SECRET_KEY` di `.env` untuk pembuatan payload QRIS dinamis sesuai nominal transaksi.
3. **Auto-Detect Kode NNS QRIS Bank & E-Wallet Indonesia (Mode `SCAN_CPM`):**
   - Saat kasir memindai QR bayar dari layar HP pembeli (termasuk decoding payload Base64 EMVCo CPM `hQVDUFY...`), sistem otomatis mengenali penerbit QR berdasarkan kode *National Numbering System (NNS)*:
     - `93600002` $\rightarrow$ `QR BANK BRI (BRIMO)`
     - `93600014` $\rightarrow$ `QR BANK BCA`
     - `93600008` $\rightarrow$ `QR BANK MANDIRI (LIVIN)`
     - `93600009` $\rightarrow$ `QR BANK BNI`
     - `93600451` $\rightarrow$ `QR BANK BSI`
     - `93600915` $\rightarrow$ `QR DANA`
     - `93600914` $\rightarrow$ `QR GOPAY`
     - `93600918` $\rightarrow$ `QR SHOPEEPAY`
     - `93600912` $\rightarrow$ `QR OVO`

---

## 8. Sistem Desain UI Ganda (Hostinger Landing Page & Minimarket POS)

1. **Halaman Depan / Landing Page (`resources/js/pages/Welcome.vue`):**
   - Mengikuti spesifikasi **`hostinger-ui-design-spec.md`**:
     - Palet *Conversion-Driven* dengan aksen **Electric Purple (`#673DE6`)** & **Deep Navy (`#2F1C6A`)**.
     - Dukungan penuh **Light Mode & Dark Mode (`darkMode: 'class'`)** dengan ikon SVG monokrom beranimasi halus.
     - Dilengkapi *Sticky Glassmorphism Navbar*, *Split Hero + Digital Member Card Showcase*, *Pricing/Tier Member Cards*, *Live Interactive Product Catalog*, dan *FAQ Accordion*.
2. **Layar Kasir POS & Seluruh Halaman Back-Office (`Pos/*`, `Admin/*`, `Laporan/*`, `Transaksi/*`, `Member/*`):**
   - Mengikuti spesifikasi **`panduan-desain-ui-pos-tokoku.md`** (Estetika Kasir Minimarket Indomaret / Alfamart):
     - **90% Palet Netral:** Latar halaman `#F1F5F9` (`bg-slate-100`), kartu & tabel putih bersih `#FFFFFF` (`bg-white`), header tabel `#F8FAFC` (`bg-slate-50`), dan garis tepi tipis `#CBD5E1` (`border-slate-300`).
     - **Satu Aksen Korporat (`#1E3A5F` Navy):** Digunakan secara disiplin hanya pada header utama, menu/tab aktif, dan 1 tombol CTA utama per seksi (`Filter`, `Bayar`, `+ Tambah`). Tombol utilitas (`Export Excel`, `Export PDF`, `Struk`, `Detail`, `Reset`) menggunakan gaya netral putih.
     - **Status Normal Tanpa Badge:** Status normal (`SELESAI`, `AKTIF`) ditampilkan sebagai teks *monospace* polos tanpa blok warna mencolok; warna merah (`#B91C1C` / `text-red-700`) hanya muncul pada anomali (`BELUM BAYAR`, `BATAL / VOID`, atau stok menipis $\le 5$).

---

## 9. Panduan Instalasi & Konfigurasi

### Prasyarat
- **PHP >= 8.2 / 8.5**
- **Composer**
- **Node.js & NPM**
- **MySQL / MariaDB** (atau SQLite untuk pengujian cepat)

### Langkah Instalasi

1. **Instal Dependensi PHP & JavaScript:**
   ```bash
   composer install
   npm install
   ```

2. **Salin & Konfigurasi File `.env`:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Sesuaikan konfigurasi database, printer thermal, dan payment gateway di `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=tokoku
   DB_USERNAME=root
   DB_PASSWORD=

   THERMAL_PRINTER_NAME="EPSON TM-T82X Receipt"

   PAYMENT_GATEWAY_PROVIDER=XENDIT
   XENDIT_SECRET_KEY=coba_payment
   PAYMENT_MERCHANT_NAME="YAZID, ELEKTRONIK"
   PAYMENT_MERCHANT_CITY=PONOROGO
   PAYMENT_MERCHANT_NMID=ID1026647000219
   PAYMENT_STATIC_QRIS_IMAGE="/images/qris-toko.jpg"
   ```

3. **Jalankan Migrasi Database & Seeder Data Awal:**
   ```bash
   php artisan migrate --seed
   ```

4. **Build Aset Frontend & Jalankan Server:**
   ```bash
   npm run build
   php artisan serve
   ```
   Atau gunakan mode pengembangan live:
   ```bash
   composer run dev
   ```

---

## 10. Daftar Akun Demo & Kartu RFID Bawaan (`DatabaseSeeder`)

Setelah menjalankan `php artisan migrate --seed`, Anda dapat login menggunakan **Username & Password** atau langsung **Tap Kartu RFID** dengan akun berikut:

| Role | Nama Lengkap | Username | Password | UID Kartu RFID | Halaman Tujuan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Admin** | Admin Toko | `admin` | `admin123` | `RFID-ADMIN-001` | `/admin/dashboard` |
| **Kasir** | Kasir Satu | `kasir1` | `kasir123` | `RFID-KASIR-001` | `/pos` |
| **Owner** | Pemilik Toko | `owner` | `owner123` | `RFID-OWNER-001` | `/laporan` |
| **Pelanggan (Member)** | Andi Member | `member1` | `member123` | `RFID-PEL-001` | `/member/dashboard` |

Data Member tambahan untuk simulasi scan kartu RFID di meja Kasir (`/pos`):
- **Andi** (`RFID-PEL-001`) — Tier: **Gold (Diskon 10%)**, Poin: `120`, Saldo RFID: `Rp 250.000`
- **Budi** (`RFID-PEL-002`) — Tier: **Silver (Diskon 5%)**, Poin: `50`, Saldo RFID: `Rp 100.000`
- **Citra** (`RFID-PEL-003`) — Tier: **Reguler (Diskon 2%)**, Poin: `25`, Saldo RFID: `Rp 50.000`
- **Dewi** (`RFID-PEL-004`) — Tier: **Reguler (Diskon 2%)**, Poin: `10`, Saldo RFID: `Rp 0`

---

## 11. Pengujian Otomatis (`Pest`) & Standar Kode (`Pint`)

Seluruh fitur utama (Katalog Publik, Registrasi Member + Bonus Poin, Belanja Mandiri + Upload Bukti Transfer + Verifikasi Kasir + Penguncian Struk, Login Password & RFID, RBAC 4 Role, Checkout Walk-In & Member RFID, Rollback Stok, Void Transaksi, Isolasi Laporan Shift per Kasir, Export Excel/PDF Anti-Formula Injection, dan Objek SQL View/Join/Subquery/Explain) telah tercakup dalam pengujian otomatis **Pest PHP** ([tests/Feature/TokoBackendTest.php](tests/Feature/TokoBackendTest.php)):

```bash
# Menjalankan seluruh rangkaian pengujian fitur (15 Tests, 149 Assertions)
php artisan test tests/Feature/TokoBackendTest.php

# Memeriksa dan merapikan format kode PHP sesuai standar Laravel Pint
vendor/bin/pint
```
#   t o k o  
 