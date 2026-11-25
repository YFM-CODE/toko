# Spesifikasi Desain & Kontrak Data Halaman Welcome (`Welcome.vue`)

Dokumen ini berisi seluruh kontrak data, route, helper, komponen, dan fitur yang wajib dipertahankan apabila Anda ingin mengganti atau mendesain ulang tampilan halaman depan (`resources/js/pages/Welcome.vue`).

---

## 1. Stack & Aturan Teknis Frontend
- **Framework:** Vue 3 (`<script setup>`) + **Inertia.js** + **Tailwind CSS v4**
- **Lokasi File:** `resources/js/pages/Welcome.vue`
- **Aturan Wajib:**
  - Komponen Vue **wajib memiliki 1 root element** (`<div>...</div>`).
  - Wajib mendukung **Mobile, Tablet, dan Desktop** (*responsive*).
  - Disarankan mendukung **Light Mode & Dark Mode** melalui `useTheme()`.

---

## 2. Kontrak Props dari Backend (`WelcomeController.php`)

Halaman `Welcome.vue` menerima `props` berikut secara otomatis dari `App\Http\Controllers\WelcomeController`:

```js
defineProps({
    isAuthenticated: Boolean, // true jika user sudah login
    userRole: String | null,  // 'admin' | 'kasir' | 'owner' | 'pelanggan' | null
    kategoriList: Array,      // [{ id_kategori, nama_kategori }]
    produkUnggulan: Array,    // Maks. 12 produk aktif (stok > 0)
    stats: Object,            // { total_produk, total_kategori, total_member }
});
```

### Struktur Item `produkUnggulan`:
```json
{
  "id_produk": 1,
  "id_kategori": 1,
  "kode_produk": "PRD-001",
  "nama_produk": "Mouse Wireless",
  "harga": 150000,
  "stok": 25,
  "gambar_url": "/uploads/produk/contoh.jpg",
  "nama_kategori": "Elektronik"
}
```

---

## 3. Route & Tautan Navigasi Wajib

Gunakan `<Link href="...">` dari `@inertiajs/vue3`:
- **Beranda:** `/`
- **Login (Username/Password & Tap Kartu RFID):** `/login`
- **Daftar Member Baru (+10 Poin Bonus):** `/register`
- **Arah Dashboard Jika Sudah Login (`isAuthenticated === true`):**
  - `kasir` $\rightarrow$ `/pos`
  - `admin` $\rightarrow$ `/admin/dashboard`
  - `owner` $\rightarrow$ `/laporan`
  - `pelanggan` (Member) $\rightarrow$ `/member/dashboard`

---

## 4. Komponen & Helper Bawaan yang Tersedia

Anda dapat langsung mengimpor komponen & fungsi berikut agar selaras dengan seluruh sistem:

```js
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemePicker from '@/Components/Pos/ThemePicker.vue';
import {
    useTheme,
    formatRupiah,
    getProductImage,
    handleProductImageError,
    generateQrSvgDataUrl,
} from '@/lib/useTheme';
```

- `ApplicationLogo` (`variant="color"` atau `"mono"`, `:size="36"`)
- `ThemePicker` (tombol ganti Mode Terang/Gelap & 4 preset warna tema)
- `formatRupiah(150000)` $\rightarrow$ `"Rp 150.000"`
- `getProductImage(produk)` & `handleProductImageError(event, produk)` $\rightarrow$ otomatis fallback ke SVG jika produk belum punya foto
- `generateQrSvgDataUrl('RFID-PEL-001')` $\rightarrow$ menghasilkan QR Code SVG offline

---

## 5. Konten & Fitur Bisnis yang Harus Ada di Desain Baru

1. **Identitas Toko:**
   - Nama Toko: **TOKO RITEL MANDIRI / YAZID, ELEKTRONIK**
   - Alamat: **Jl. Raya Ponorogo No. 10**
2. **Hero Section & Call-To-Action (CTA):**
   - Headline promo toko + tombol utama ke `/register` (**Daftar Member Gratis +10 Poin**) dan `/login` (**Masuk / Tap RFID**).
   - Menampilkan ringkasan statistik `stats.total_produk`, `stats.total_kategori`, dan `stats.total_member`.
3. **Katalog Produk Live (`produkUnggulan`):**
   - Pencarian produk (`nama_produk` / `kode_produk`) & filter berdasarkan `kategoriList`.
   - Kartu produk menampilkan gambar, nama, kode PLU, stok, kategori, dan harga (`formatRupiah`).
4. **Informasi Tier & Keuntungan Member:**
   - **Reguler:** Diskon otomatis **2%** (langsung aktif saat daftar, bonus +10 poin).
   - **Silver:** Diskon otomatis **5%** (akumulasi belanja $\ge$ Rp 500.000).
   - **Gold:** Diskon otomatis **10%** (akumulasi belanja $\ge$ Rp 1.000.000).
   - **Poin & Saldo RFID:** +1 Poin tiap Rp 10.000 belanja (1 Poin = Rp 100), serta pembayaran 1-Tap Saldo Kartu RFID & QRIS GoPay Merchant.
5. **FAQ & Footer:**
   - Bagian tanya-jawab singkat seputar pendaftaran member, pembayaran QRIS/RFID, dan struk digital.
