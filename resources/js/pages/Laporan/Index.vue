<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatRupiah } from '@/lib/useTheme';

const props = defineProps({
    laporanView: {
        type: Object,
        default: () => ({ laporan_penjualan: [], total_belanja_pelanggan: [] }),
    },
    laporanDetail: {
        type: Array,
        default: () => [],
    },
    rekapPerKasir: {
        type: Array,
        default: () => [],
    },
    analisisSubquery: {
        type: Object,
        default: () => ({
            rata_rata_belanja: 0,
            diatas_rata_rata: [],
            belum_transaksi: [],
            produk_terlaris: [],
            produk_belum_terjual: [],
        }),
    },
    auditLog: {
        type: Object,
        default: () => ({ data: [] }),
    },
    explainPlan: {
        type: Array,
        default: () => [],
    },
    kategori: {
        type: Array,
        default: () => [],
    },
    kasirList: {
        type: Array,
        default: () => [],
    },
    isKasirScoped: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role || 'owner');
const canSeeAudit = computed(() => role.value === 'admin' || role.value === 'owner');

const activeTab = ref('view');
const filterForm = ref({
    search: props.filters.search || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    status: props.filters.status || '',
    id_kategori: props.filters.id_kategori || '',
    id_user: props.filters.id_user || '',
    nama_tabel: props.filters.nama_tabel || '',
});

const exportModul = computed(() => {
    if (activeTab.value === 'join') {
        return 'laporan-detail';
    }
    if (activeTab.value === 'subquery') {
        return 'laporan-pelanggan';
    }
    if (activeTab.value === 'audit') {
        return 'audit-log';
    }
    return 'laporan-view';
});

const exportQueryString = computed(() => {
    const params = new URLSearchParams();
    Object.entries(filterForm.value).forEach(([k, v]) => {
        if (v) {
            params.set(k, v);
        }
    });
    const q = params.toString();
    return q ? `?${q}` : '';
});

const applyFilters = () => {
    router.get('/laporan', filterForm.value, { preserveState: true, replace: true });
};

const resetFilters = () => {
    filterForm.value = {
        search: '',
        date_from: '',
        date_to: '',
        status: '',
        id_kategori: '',
        id_user: props.isKasirScoped ? (props.filters.id_user || '') : '',
        nama_tabel: '',
    };
    applyFilters();
};

const kpiPenjualan = computed(() => {
    const rows = (props.laporanView?.laporan_penjualan || []).filter((r) => r.status === 'selesai');
    return {
        totalOmzet: rows.reduce((acc, r) => acc + Number(r.total_belanja || 0), 0),
        totalDiskon: rows.reduce((acc, r) => acc + Number(r.diskon || 0), 0),
        jumlahTransaksi: rows.length,
    };
});
</script>

<template>
    <Head title="Laporan & Analisis Bisnis SQL" />

    <AdminLayout title="Laporan Penjualan, Analisis SQL & Export (Excel / PDF)">
        <!-- A. Header & Tombol Aksi Atas (Gaya Netral Berlatar Putih & Border Abu-abu Tipis) -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="`/export/${exportModul}/excel${exportQueryString}`"
                    class="px-3 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
                >
                    Export Excel (Tab Aktif)
                </a>
                <a
                    :href="`/export/${exportModul}/pdf${exportQueryString}`"
                    class="px-3 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
                >
                    Export PDF (Tab Aktif)
                </a>
            </div>

            <Link
                v-if="role === 'kasir'"
                href="/pos"
                class="px-3.5 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
            >
                &larr; Kembali ke Layar Kasir (POS)
            </Link>
        </div>

        <!-- Tab Navigasi (Tab Aktif Navy #1E3A5F, Tab Tidak Aktif Netral text-slate-600) -->
        <div class="flex flex-wrap gap-2 mb-5 border-b border-slate-300 pb-3 text-xs font-medium">
            <button
                type="button"
                @click="activeTab = 'view'"
                class="px-4 py-2 rounded-md border transition"
                :class="
                    activeTab === 'view'
                        ? 'bg-[#1E3A5F] text-white border-[#1E3A5F] font-semibold'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                "
            >
                Tab 1: Rekap Penjualan (View)
            </button>
            <button
                type="button"
                @click="activeTab = 'join'"
                class="px-4 py-2 rounded-md border transition"
                :class="
                    activeTab === 'join'
                        ? 'bg-[#1E3A5F] text-white border-[#1E3A5F] font-semibold'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                "
            >
                Tab 2: Detail Transaksi (JOIN 5 Tabel)
            </button>
            <button
                type="button"
                @click="activeTab = 'subquery'"
                class="px-4 py-2 rounded-md border transition"
                :class="
                    activeTab === 'subquery'
                        ? 'bg-[#1E3A5F] text-white border-[#1E3A5F] font-semibold'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                "
            >
                Tab 3: Analisis Bisnis (Subquery &amp; Function)
            </button>
            <button
                v-if="canSeeAudit"
                type="button"
                @click="activeTab = 'audit'"
                class="px-4 py-2 rounded-md border transition"
                :class="
                    activeTab === 'audit'
                        ? 'bg-[#1E3A5F] text-white border-[#1E3A5F] font-semibold'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                "
            >
                Tab 4: Audit Log &amp; EXPLAIN Index
            </button>
        </div>

        <!-- Info Shift Kasir (Jika Login sebagai Kasir) -->
        <div
            v-if="isKasirScoped"
            class="mb-4 p-3 rounded-md bg-white border border-slate-300 text-xs text-slate-700"
        >
            Mode Shift Kasir Aktif: Laporan dan dokumen Export di halaman ini menampilkan transaksi shift milik Anda (<strong class="font-semibold text-slate-900">{{ page.props.auth?.user?.nama_lengkap }}</strong>).
        </div>

        <!-- Bar Filter Global -->
        <form
            @submit.prevent="applyFilters"
            class="p-4 rounded-md bg-white border border-slate-300 mb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 text-xs"
        >
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Kata kunci kode/pelanggan/produk..."
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 focus:border-[#1E3A5F] outline-none"
            />
            <select
                v-model="filterForm.id_user"
                :disabled="isKasirScoped"
                @change="applyFilters"
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 disabled:bg-slate-100"
            >
                <option v-if="!isKasirScoped" value="">Semua Kasir (Shift)</option>
                <option v-for="k in kasirList" :key="k.id_user" :value="k.id_user">
                    Kasir: {{ k.nama_lengkap }}
                </option>
            </select>
            <input
                v-model="filterForm.date_from"
                type="date"
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 font-mono"
            />
            <input
                v-model="filterForm.date_to"
                type="date"
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 font-mono"
            />
            <select
                v-model="filterForm.status"
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900"
            >
                <option value="">Semua Status</option>
                <option value="selesai">Selesai</option>
                <option value="batal">Batal (Void)</option>
            </select>
            <select
                v-model="filterForm.id_kategori"
                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900"
            >
                <option value="">Semua Kategori</option>
                <option v-for="k in kategori" :key="k.id_kategori" :value="k.id_kategori">
                    {{ k.nama_kategori }}
                </option>
            </select>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 font-medium text-white bg-[#1E3A5F] hover:bg-[#162C49] rounded-md transition"
                >
                    Filter
                </button>
                <button
                    type="button"
                    @click="resetFilters"
                    class="px-3 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
                >
                    Reset
                </button>
            </div>
        </form>

        <!-- Rekapitulasi Setoran Shift per Kasir -->
        <div
            v-if="rekapPerKasir.length > 0"
            class="mb-5 rounded-md bg-white border border-slate-300 overflow-hidden"
        >
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                Rekapitulasi Shift &amp; Setoran per Kasir
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Nama Kasir</th>
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3 text-right">Jumlah Struk</th>
                            <th class="px-4 py-3 text-right">Total Tunai (Cash)</th>
                            <th class="px-4 py-3 text-right">Total Non-Tunai (QRIS/RFID)</th>
                            <th class="px-4 py-3 text-right">Total Diskon</th>
                            <th class="px-4 py-3 text-right">Total Omzet Shift</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="rk in rekapPerKasir" :key="rk.id_user" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ rk.nama_kasir }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">@{{ rk.username }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-900">{{ rk.total_transaksi }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800">{{ formatRupiah(rk.total_tunai) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800">{{ formatRupiah(rk.total_non_tunai) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">- {{ formatRupiah(rk.total_diskon) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">{{ formatRupiah(rk.total_omzet) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 1: REKAP PENJUALAN (DATABASE VIEW) -->
        <div v-if="activeTab === 'view'" class="space-y-5">
            <!-- B. Kartu Ringkasan (KPI Cards — Seragam Putih Polos & Border Tipis) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border border-slate-300 rounded-md p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Total Omzet Bersih
                    </p>
                    <p class="mt-2 text-2xl font-bold font-mono text-slate-900 tabular-nums">
                        {{ formatRupiah(kpiPenjualan.totalOmzet) }}
                    </p>
                </div>

                <div class="bg-white border border-slate-300 rounded-md p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Total Diskon Diberikan
                    </p>
                    <p class="mt-2 text-2xl font-bold font-mono text-slate-900 tabular-nums">
                        {{ formatRupiah(kpiPenjualan.totalDiskon) }}
                    </p>
                </div>

                <div class="bg-white border border-slate-300 rounded-md p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Jumlah Transaksi Selesai
                    </p>
                    <p class="mt-2 text-2xl font-bold font-mono text-slate-900 tabular-nums">
                        {{ kpiPenjualan.jumlahTransaksi }} Nota
                    </p>
                </div>
            </div>

            <!-- C. Tabel Data VIEW_LAPORAN_PENJUALAN (Kolom STATUS Polos Tanpa Badge) -->
            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                    Database View: view_laporan_penjualan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Kode Transaksi</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Pelanggan</th>
                                <th class="px-4 py-3">Kasir</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                                <th class="px-4 py-3 text-right">Diskon</th>
                                <th class="px-4 py-3 text-right">Total Belanja</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 tabular-nums">
                            <tr v-for="row in laporanView.laporan_penjualan || []" :key="row.id_transaksi" class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ row.kode_transaksi }}</td>
                                <td class="px-4 py-3 font-mono text-slate-600">{{ row.tanggal }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.nama_pelanggan }} ({{ row.kota }})</td>
                                <td class="px-4 py-3 text-slate-700">{{ row.kasir }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700">{{ formatRupiah(row.subtotal) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600">- {{ formatRupiah(row.diskon) }}</td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-bold"
                                    :class="row.status === 'batal' ? 'text-red-700 line-through' : 'text-slate-900'"
                                >
                                    {{ formatRupiah(row.total_belanja) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-mono text-xs uppercase"
                                    :class="
                                        row.status === 'selesai'
                                            ? 'font-medium text-slate-700'
                                            : 'font-semibold text-red-700'
                                    "
                                >
                                    {{ row.status === 'selesai' ? 'SELESAI' : 'BATAL (VOID)' }}
                                </td>
                            </tr>
                            <tr v-if="(laporanView.laporan_penjualan || []).length === 0">
                                <td colspan="8" class="p-6 text-center text-slate-500">
                                    Tidak ada data pada view_laporan_penjualan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: LAPORAN DETAIL TRANSAKSI (MULTIPLE JOIN 5 TABEL) -->
        <div v-if="activeTab === 'join'" class="rounded-md bg-white border border-slate-300 overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                Multiple JOIN 5 Tabel (tbl_transaksi + tbl_pelanggan + tbl_detail_transaksi + tbl_produk + tbl_kategori)
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Kode Struk</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Kode &amp; Nama Produk</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Harga Satuan</th>
                            <th class="px-4 py-3 text-right">Subtotal Item</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="(item, idx) in laporanDetail" :key="idx" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ item.kode_transaksi }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ item.tanggal }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ item.nama_pelanggan }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ item.nama_kategori }}</td>
                            <td class="px-4 py-3 text-slate-900">
                                <span class="font-mono font-semibold">{{ item.kode_produk }}</span> — {{ item.nama_produk }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ item.jumlah }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ formatRupiah(item.harga_satuan) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                {{ formatRupiah(item.subtotal_item) }}
                            </td>
                        </tr>
                        <tr v-if="laporanDetail.length === 0">
                            <td colspan="8" class="p-6 text-center text-slate-500">
                                Belum ada rincian item transaksi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: ANALISIS TRANSAKSI (SUBQUERY & STORED FUNCTION KategoriBelanja) -->
        <div v-if="activeTab === 'subquery'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs flex justify-between items-center">
                    <span class="font-semibold uppercase text-slate-600">Subquery AVG: Transaksi &gt; Rata-Rata</span>
                    <span class="font-mono font-semibold text-slate-800">
                        AVG: {{ formatRupiah(analisisSubquery.rata_rata_belanja || 0) }}
                    </span>
                </div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Kode Struk</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3 text-right">Total Belanja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="t in analisisSubquery.diatas_rata_rata || []" :key="t.kode_transaksi" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ t.kode_transaksi }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ t.tanggal }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ t.nama_pelanggan || '#' + t.id_pelanggan }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                {{ formatRupiah(t.total_belanja) }}
                            </td>
                        </tr>
                        <tr v-if="(analisisSubquery.diatas_rata_rata || []).length === 0">
                            <td colspan="4" class="p-5 text-center text-slate-500">
                                Belum ada transaksi di atas rata-rata.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                    View + Function KategoriBelanja(total_belanja)
                </div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3 text-right">Trx</th>
                            <th class="px-4 py-3 text-right">Total Belanja</th>
                            <th class="px-4 py-3 text-center">Kategori Member</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="p in laporanView.total_belanja_pelanggan || []" :key="p.id_pelanggan" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ p.nama_pelanggan }} ({{ p.kota }})</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ p.total_transaksi }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                {{ formatRupiah(p.total_belanja) }}
                            </td>
                            <td class="px-4 py-3 text-center font-mono text-xs font-medium text-slate-700">
                                {{ p.kategori_pelanggan }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                    Subquery NOT EXISTS: Pelanggan Belum Pernah Transaksi
                </div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Nama Pelanggan</th>
                            <th class="px-4 py-3">Kota</th>
                            <th class="px-4 py-3">Telepon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="pel in analisisSubquery.belum_transaksi || []" :key="pel.id_pelanggan" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-semibold">#{{ pel.id_pelanggan }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ pel.nama_pelanggan }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ pel.kota }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ pel.no_telepon || '-' }}</td>
                        </tr>
                        <tr v-if="(analisisSubquery.belum_transaksi || []).length === 0">
                            <td colspan="4" class="p-5 text-center text-slate-500">
                                Seluruh pelanggan sudah pernah bertransaksi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600">
                    Analisis Produk Terlaris &amp; Belum Pernah Terjual
                </div>
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama Produk</th>
                            <th class="px-4 py-3 text-right">Qty Terjual</th>
                            <th class="px-4 py-3 text-right">Total Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="pr in analisisSubquery.produk_terlaris || []" :key="pr.id_produk" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ pr.kode_produk }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ pr.nama_produk }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ pr.total_terjual }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">{{ formatRupiah(pr.total_omzet) }}</td>
                        </tr>
                        <tr
                            v-for="un in analisisSubquery.produk_belum_terjual || []"
                            :key="'un-' + un.id_produk"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 font-mono text-slate-500">{{ un.kode_produk }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ un.nama_produk }} (Belum Terjual)</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">0</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">Rp 0</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: AUDIT LOG (tbl_log) & EXPLAIN QUERY PLAN -->
        <div v-if="activeTab === 'audit' && canSeeAudit" class="space-y-5">
            <div class="p-4 rounded-md bg-white border border-slate-300 text-xs">
                <div class="font-semibold uppercase text-slate-600 mb-2">
                    Verifikasi Optimasi Index (EXPLAIN SELECT * FROM tbl_transaksi WHERE id_pelanggan = 1 AND tanggal BETWEEN ...)
                </div>
                <pre class="p-3 rounded-md bg-slate-900 text-slate-100 border border-slate-700 overflow-x-auto font-mono text-[11px]">{{ JSON.stringify(explainPlan, null, 2) }}</pre>
            </div>

            <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-600 flex justify-between">
                    <span>Riwayat Audit Log (tbl_log — Read-Only)</span>
                    <Link href="/audit-log" class="text-[#1E3A5F] hover:underline">Buka Halaman Penuh &rarr;</Link>
                </div>
                <table class="w-full text-xs text-left font-mono">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Tabel</th>
                            <th class="px-4 py-3">Aktivitas</th>
                            <th class="px-4 py-3">Ref ID</th>
                            <th class="px-4 py-3">Data Lama</th>
                            <th class="px-4 py-3">Data Baru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="log in auditLog.data || []" :key="log.id_log" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-[11px] text-slate-600">{{ log.waktu }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ log.nama_tabel }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ log.aktivitas }}</td>
                            <td class="px-4 py-3 text-slate-600">#{{ log.id_referensi ?? '-' }}</td>
                            <td class="px-4 py-3 text-red-700 text-[11px]">{{ log.data_lama || '-' }}</td>
                            <td class="px-4 py-3 text-slate-800 text-[11px]">{{ log.data_baru || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
