<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme, formatRupiah } from '@/lib/useTheme';

defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    stok_menipis: {
        type: Array,
        default: () => [],
    },
    log_terbaru: {
        type: Array,
        default: () => [],
    },
});

const { themeState } = useTheme();
</script>

<template>
    <Head title="Dashboard Admin" />

    <AdminLayout title="Dashboard Admin — Ringkasan Inventori & Operasional">
        <!-- Kartu Ringkasan KPI (Seragam Putih Polos & Border Tipis) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                class="p-4 rounded-md border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Produk</div>
                <div class="mt-2 text-2xl font-bold font-mono tabular-nums">{{ stats.total_produk ?? 0 }}</div>
                <div class="text-xs text-slate-500 mt-1">Dalam {{ stats.total_kategori ?? 0 }} kategori</div>
            </div>

            <div
                class="p-4 rounded-md border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Produk Stok Menipis (&le; 5)</div>
                <div
                    class="mt-2 text-2xl font-bold font-mono tabular-nums"
                    :class="(stats.produk_stok_menipis ?? stok_menipis.length) > 0 ? 'text-red-700' : ''"
                >
                    {{ stats.produk_stok_menipis ?? stok_menipis.length }}
                </div>
                <div class="text-xs text-slate-500 mt-1">Perlu restock segera</div>
            </div>

            <div
                class="p-4 rounded-md border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Member &amp; Pelanggan</div>
                <div class="mt-2 text-2xl font-bold font-mono tabular-nums">{{ stats.total_pelanggan ?? 0 }}</div>
                <div class="text-xs text-slate-500 mt-1">Termasuk Pelanggan Umum</div>
            </div>

            <div
                class="p-4 rounded-md border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transaksi Hari Ini</div>
                <div class="mt-2 text-2xl font-bold font-mono tabular-nums">
                    {{ stats.transaksi_hari_ini ?? 0 }} Nota
                </div>
                <div class="text-xs font-mono text-slate-500 mt-1">
                    Total Omzet: {{ formatRupiah(stats.total_pendapatan ?? 0) }}
                </div>
            </div>
        </div>

        <!-- 2 Kolom: Stok Menipis & 5 Aktivitas Terbaru tbl_log -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Tabel Produk Stok Menipis -->
            <div
                class="rounded-md border overflow-hidden"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div
                    class="px-4 py-3 border-b flex items-center justify-between"
                    :class="themeState.mode === 'dark' ? 'bg-slate-800 border-slate-700' : 'bg-slate-50 border-slate-300'"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-wide font-mono">Peringatan Stok Menipis (tbl_produk)</h2>
                    <Link href="/admin/produk" class="text-xs font-medium text-[#1E3A5F] hover:underline">
                        Kelola Produk &rarr;
                    </Link>
                </div>
                <table class="w-full text-xs text-left">
                    <thead
                        class="uppercase font-semibold text-[11px] border-b"
                        :class="themeState.mode === 'dark' ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-600'"
                    >
                        <tr>
                            <th class="p-3">Kode</th>
                            <th class="p-3">Nama Produk</th>
                            <th class="p-3">Kategori</th>
                            <th class="p-3 text-right">Sisa Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 font-mono">
                        <tr v-for="p in stok_menipis" :key="p.id_produk" class="hover:bg-slate-50/70">
                            <td class="p-3 font-semibold">{{ p.kode_produk }}</td>
                            <td class="p-3 font-sans font-medium">{{ p.nama_produk }}</td>
                            <td class="p-3">{{ p.kategori?.nama_kategori || '-' }}</td>
                            <td class="p-3 text-right font-semibold text-red-700 tabular-nums">
                                {{ p.stok }}
                            </td>
                        </tr>
                        <tr v-if="stok_menipis.length === 0">
                            <td colspan="4" class="p-6 text-center text-slate-500">
                                Seluruh stok produk dalam kondisi aman (&gt; 5).
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tabel Mini 5 Aktivitas Terbaru dari tbl_log -->
            <div
                class="rounded-md border overflow-hidden"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
            >
                <div
                    class="px-4 py-3 border-b flex items-center justify-between"
                    :class="themeState.mode === 'dark' ? 'bg-slate-800 border-slate-700' : 'bg-slate-50 border-slate-300'"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-wide font-mono">5 Aktivitas Terbaru (tbl_log)</h2>
                    <Link href="/audit-log" class="text-xs font-medium text-[#1E3A5F] hover:underline">
                        Lihat Audit Log &rarr;
                    </Link>
                </div>
                <table class="w-full text-xs text-left">
                    <thead
                        class="uppercase font-semibold text-[11px] border-b"
                        :class="themeState.mode === 'dark' ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-600'"
                    >
                        <tr>
                            <th class="p-3">Waktu</th>
                            <th class="p-3">Tabel</th>
                            <th class="p-3">Aktivitas</th>
                            <th class="p-3">Detail Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 font-mono">
                        <tr v-for="log in log_terbaru" :key="log.id_log" class="hover:bg-slate-50/70">
                            <td class="p-3 text-[11px] text-slate-600">{{ log.waktu }}</td>
                            <td class="p-3 font-semibold">{{ log.nama_tabel }}</td>
                            <td class="p-3 font-medium">{{ log.aktivitas }}</td>
                            <td class="p-3 text-[11px] text-slate-600 truncate max-w-[200px]">
                                {{ log.data_baru || log.data_lama || '-' }}
                            </td>
                        </tr>
                        <tr v-if="log_terbaru.length === 0">
                            <td colspan="4" class="p-6 text-center text-slate-500">
                                Belum ada catatan aktivitas di tbl_log.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
