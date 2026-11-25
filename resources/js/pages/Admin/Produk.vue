<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QrScannerModal from '@/Components/Pos/QrScannerModal.vue';
import {
    useTheme,
    formatRupiah,
    getProductImage,
    handleProductImageError,
    generateQrSvgDataUrl,
    playBeepSound,
    printThermalElement,
} from '@/lib/useTheme';

const props = defineProps({
    produk: {
        type: Object,
        default: () => ({ data: [] }),
    },
    kategori: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const { activeTokens } = useTheme();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const search = ref(props.filters.search || '');
const idKategori = ref(props.filters.id_kategori || '');
const showModal = ref(false);
const showBarcodeScanModal = ref(false);
const editingId = ref(null);
const imagePreviewUrl = ref(null);
const namaProdukInputRef = ref(null);

const qrModalProduk = ref(null);

const form = useForm({
    id_kategori: '',
    kode_produk: '',
    nama_produk: '',
    harga: 0,
    stok: 0,
    gambar: null,
    hapus_gambar: false,
});

const exportQueryString = computed(() => {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('search', search.value);
    }
    if (idKategori.value) {
        params.set('id_kategori', idKategori.value);
    }
    const q = params.toString();
    return q ? `?${q}` : '';
});

const applyFilter = () => {
    router.get(
        '/admin/produk',
        { search: search.value, id_kategori: idKategori.value },
        { preserveState: true, replace: true },
    );
};

const generateNextAutoKode = () => {
    const existingCodes = (props.produk.data || []).map((p) => p.kode_produk || '');
    let maxNum = existingCodes.length;
    existingCodes.forEach((code) => {
        const match = code.match(/(\d+)$/);
        if (match && Number(match[1]) > maxNum) {
            maxNum = Number(match[1]);
        }
    });
    form.kode_produk = 'PRD-' + String(maxNum + 1).padStart(3, '0');
    playBeepSound();
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.id_kategori = props.kategori[0]?.id_kategori || '';
    form.gambar = null;
    form.hapus_gambar = false;
    imagePreviewUrl.value = null;
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (item) => {
    editingId.value = item.id_produk;
    form.id_kategori = item.id_kategori;
    form.kode_produk = item.kode_produk;
    form.nama_produk = item.nama_produk;
    form.harga = item.harga;
    form.stok = item.stok;
    form.gambar = null;
    form.hapus_gambar = false;
    imagePreviewUrl.value = item.gambar_url || null;
    form.clearErrors();
    showModal.value = true;
};

const handleBarcodeScannedForForm = (scannedCode) => {
    form.kode_produk = scannedCode;
    playBeepSound();
    showBarcodeScanModal.value = false;
    nextTick(() => namaProdukInputRef.value?.focus());
};

const handlePhysicalScannerEnterOnCode = () => {
    playBeepSound();
    nextTick(() => namaProdukInputRef.value?.focus());
};

const handleFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }
    form.gambar = file;
    form.hapus_gambar = false;
    imagePreviewUrl.value = URL.createObjectURL(file);
};

const clearSelectedImage = () => {
    form.gambar = null;
    form.hapus_gambar = true;
    imagePreviewUrl.value = null;
};

const submitForm = () => {
    if (editingId.value) {
        form.post(`/admin/produk/${editingId.value}`, {
            forceFormData: true,
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post('/admin/produk', {
            forceFormData: true,
            onSuccess: () => (showModal.value = false),
        });
    }
};

const deleteItem = (item) => {
    if (confirm(`Hapus produk "${item.nama_produk}" (${item.kode_produk})?`)) {
        router.delete(`/admin/produk/${item.id_produk}`);
    }
};

const printQrLabel = () => {
    printThermalElement('printable-receipt', '58mm');
};
</script>

<template>
    <Head title="Manajemen Produk, Barcode Scan & Gambar" />

    <AdminLayout title="Manajemen Produk, Stok, Scan Barcode/QR & Gambar (tbl_produk)">
        <div
            v-if="page.props.errors?.produk"
            class="mb-4 p-3 rounded border-2 text-xs font-mono font-extrabold toko-banner-error"
        >
            ⚠ {{ page.props.errors.produk }}
        </div>

        <!-- Bar Filter, Export Excel/PDF & Tombol Tambah -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <form @submit.prevent="applyFilter" class="flex flex-wrap items-center gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari kode PLU/Barcode atau nama produk..."
                    class="px-3 py-2 text-xs rounded border-2 font-bold w-64 toko-input-scan"
                />
                <select
                    v-model="idKategori"
                    @change="applyFilter"
                    class="px-3 py-2 text-xs rounded border-2 font-bold toko-panel"
                >
                    <option value="">Semua Kategori</option>
                    <option v-for="k in kategori" :key="k.id_kategori" :value="k.id_kategori">
                        {{ k.nama_kategori }}
                    </option>
                </select>
                <button
                    type="submit"
                    class="px-3 py-2 text-xs font-mono font-extrabold uppercase border-2 rounded toko-thead"
                >
                    Filter
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                <a
                    :href="`/export/produk/excel${exportQueryString}`"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
                >
                    Export Excel
                </a>
                <a
                    :href="`/export/produk/pdf${exportQueryString}`"
                    target="_blank"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
                >
                    Export PDF
                </a>
                <button
                    v-if="isAdmin"
                    type="button"
                    @click="openCreate"
                    class="px-4 py-2 font-semibold uppercase text-white rounded-md bg-[#1E3A5F] hover:bg-[#162C49] transition"
                >
                    + Tambah Produk
                </button>
            </div>
        </div>

        <!-- Tabel Produk dengan Foto & QR -->
        <div class="rounded-md border overflow-hidden toko-panel">
            <table class="w-full text-xs text-left">
                <thead class="uppercase font-semibold text-[11px] border-b toko-thead">
                    <tr>
                        <th class="p-3 w-20 text-center">Gambar</th>
                        <th class="p-3">Kode PLU / Barcode</th>
                        <th class="p-3">Nama Produk</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3 text-right">Harga Jual</th>
                        <th class="p-3 text-right">Stok</th>
                        <th class="p-3 text-center">QR Code</th>
                        <th v-if="isAdmin" class="p-3 text-center w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="item in produk.data || []" :key="item.id_produk" class="hover:bg-slate-50/70">
                        <td class="p-2 text-center">
                            <img
                                :src="getProductImage(item)"
                                :alt="item.nama_produk"
                                @error="(e) => handleProductImageError(e, item)"
                                class="w-11 h-11 object-contain border border-slate-300 bg-white mx-auto rounded"
                            />
                        </td>
                        <td class="p-3 font-mono font-semibold">{{ item.kode_produk }}</td>
                        <td class="p-3 font-medium">{{ item.nama_produk }}</td>
                        <td class="p-3 text-slate-600">{{ item.kategori?.nama_kategori || '-' }}</td>
                        <td class="p-3 text-right font-mono font-semibold tabular-nums">
                            {{ formatRupiah(item.harga) }}
                        </td>
                        <td class="p-3 text-right font-mono tabular-nums">
                            <span
                                :class="item.stok <= 5 ? 'text-red-700 font-semibold' : 'text-slate-700 font-medium'"
                            >
                                {{ item.stok }}
                            </span>
                        </td>
                        <td class="p-3 text-center">
                            <button
                                type="button"
                                @click="qrModalProduk = item"
                                class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                QR Label
                            </button>
                        </td>
                        <td v-if="isAdmin" class="p-3 text-center space-x-1.5">
                            <button
                                type="button"
                                @click="openEdit(item)"
                                class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="deleteItem(item)"
                                class="px-2.5 py-1 rounded border border-red-200 bg-white text-red-700 hover:bg-red-50 font-medium"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="(produk.data || []).length === 0">
                        <td colspan="8" class="p-6 text-center font-bold">
                            Belum ada data produk.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah / Edit Produk + Scan Barcode Kamera/USB + Upload Gambar -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-center justify-center bg-black p-4"
        >
            <div class="w-full max-w-lg rounded border-2 p-5 shadow-2xl toko-panel">
                <h3 class="text-sm font-extrabold uppercase font-mono mb-4 pb-2 border-b-2" :style="{ borderColor: activeTokens.border }">
                    {{ editingId ? 'Edit Produk, Barcode & Gambar' : 'Tambah Produk Baru (Bisa Scan Barcode/QR)' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Kategori *</label>
                        <select
                            v-model="form.id_kategori"
                            required
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        >
                            <option v-for="k in kategori" :key="k.id_kategori" :value="k.id_kategori">
                                {{ k.nama_kategori }}
                            </option>
                        </select>
                    </div>

                    <!-- Kolom Kode Batang / PLU dengan Tombol Scan Kamera & Auto-Kode -->
                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">
                            Kode Batang / PLU / QR (Bisa Tembak Scanner USB atau Klik Scan Kamera) *
                        </label>
                        <div class="flex gap-1.5">
                            <input
                                v-model="form.kode_produk"
                                type="text"
                                required
                                autofocus
                                placeholder="Scan barcode kemasan atau ketik PRD-005..."
                                @keydown.enter.prevent="handlePhysicalScannerEnterOnCode"
                                class="flex-1 px-3 py-2 rounded border-2 font-mono font-extrabold toko-input-scan"
                            />
                            <button
                                type="button"
                                @click="showBarcodeScanModal = true"
                                class="px-3 py-2 rounded border-2 border-black font-mono font-extrabold toko-btn-pay shrink-0"
                                title="Buka Kamera untuk Scan Barcode / QR Kemasan Barang"
                            >
                                📷 Scan
                            </button>
                            <button
                                type="button"
                                @click="generateNextAutoKode"
                                class="px-2.5 py-2 rounded border-2 border-black font-mono font-extrabold text-[#111111] shrink-0"
                                :style="{ backgroundColor: activeTokens.keycap }"
                                title="Buat Kode PLU Otomatis Berikutnya"
                            >
                                ⚡ Auto
                            </button>
                        </div>
                        <div v-if="form.errors.kode_produk" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.kode_produk }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Nama Produk *</label>
                        <input
                            ref="namaProdukInputRef"
                            v-model="form.nama_produk"
                            type="text"
                            required
                            placeholder="Masukkan nama produk..."
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-mono font-bold uppercase mb-1">Harga Jual (&ge; 0) *</label>
                            <input
                                v-model.number="form.harga"
                                type="number"
                                min="0"
                                required
                                class="w-full px-3 py-2 rounded border-2 font-mono font-bold toko-input-scan"
                            />
                        </div>
                        <div>
                            <label class="block font-mono font-bold uppercase mb-1">Stok (&ge; 0) *</label>
                            <input
                                v-model.number="form.stok"
                                type="number"
                                min="0"
                                required
                                class="w-full px-3 py-2 rounded border-2 font-mono font-bold toko-input-scan"
                            />
                        </div>
                    </div>

                    <!-- Upload Foto Produk + Live Preview 120x120 -->
                    <div class="p-3 rounded border-2 flex items-center gap-4" :style="{ borderColor: activeTokens.border }">
                        <div class="w-24 h-24 border-2 border-slate-400 bg-white flex items-center justify-center shrink-0 overflow-hidden">
                            <img
                                :src="imagePreviewUrl || getProductImage({ kode_produk: form.kode_produk || 'PRD', nama_produk: form.nama_produk || 'PRODUK' })"
                                alt="Preview"
                                class="max-w-full max-h-full object-contain"
                            />
                        </div>
                        <div class="flex-1 space-y-2">
                            <label class="block font-mono font-bold uppercase">
                                Foto Produk (JPG/PNG/WEBP, Maks 2MB)
                            </label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleFileChange"
                                class="w-full text-xs"
                            />
                            <button
                                v-if="imagePreviewUrl"
                                type="button"
                                @click="clearSelectedImage"
                                class="px-2 py-1 text-[10px] font-mono font-bold bg-[#b91c1c] text-white rounded-sm"
                            >
                                Hapus Foto &amp; Gunakan SVG Default
                            </button>
                            <div v-if="form.errors.gambar" class="text-[#b91c1c] font-bold">
                                {{ form.errors.gambar }}
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2 font-mono">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-3 py-1.5 border-2 rounded font-bold"
                            :style="{ borderColor: activeTokens.border }"
                        >
                            BATAL
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-1.5 text-white rounded font-extrabold border-2 border-black"
                            :style="{ backgroundColor: activeTokens.primary }"
                        >
                            {{ form.processing ? 'MENYIMPAN...' : 'SIMPAN PRODUK' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Kamera Pemindai Barcode / QR untuk Input Kode Batang Admin -->
        <QrScannerModal
            :show="showBarcodeScanModal"
            :produk-list="produk.data || []"
            @close="showBarcodeScanModal = false"
            @detected="handleBarcodeScannedForForm"
        />

        <!-- Modal Cetak Label QR Produk -->
        <div
            v-if="qrModalProduk"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono"
        >
            <div class="w-full max-w-sm rounded border-2 border-black p-5 shadow-2xl toko-receipt-paper">
                <div id="printable-receipt" class="text-center space-y-2 p-3 border-2 border-dashed border-[#111111] toko-receipt-paper">
                    <div class="text-xs font-extrabold uppercase">LABEL QR PRODUK TOKO</div>
                    <img
                        :src="generateQrSvgDataUrl(qrModalProduk.kode_produk)"
                        :alt="qrModalProduk.kode_produk"
                        class="w-36 h-36 mx-auto border border-[#111111] p-1 bg-white"
                    />
                    <div class="text-base font-extrabold">{{ qrModalProduk.kode_produk }}</div>
                    <div class="text-xs font-bold">{{ qrModalProduk.nama_produk }}</div>
                    <div class="text-sm font-extrabold">{{ formatRupiah(qrModalProduk.harga) }}</div>
                </div>

                <div class="grid grid-cols-2 gap-2 mt-4 text-xs font-extrabold no-print">
                    <button
                        type="button"
                        @click="printQrLabel"
                        class="py-2 border-2 border-black rounded toko-btn-pay"
                    >
                        🖨 CETAK QR
                    </button>
                    <button
                        type="button"
                        @click="qrModalProduk = null"
                        class="py-2 border-2 border-black bg-[#1e293b] text-white rounded"
                    >
                        TUTUP
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
