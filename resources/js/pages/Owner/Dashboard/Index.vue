<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme, formatRupiah } from '@/lib/useTheme';

defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const { themeState } = useTheme();
</script>

<template>
    <Head title="Dashboard Eksekutif Owner" />

    <AdminLayout title="Dashboard Eksekutif Owner / Manajer (Read-Only)">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 font-mono">
            <div
                class="p-5 rounded border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'"
            >
                <div class="text-xs uppercase text-slate-400">Total Pendapatan</div>
                <div class="text-2xl font-extrabold text-emerald-400 mt-1">
                    {{ formatRupiah(stats.total_pendapatan ?? 0) }}
                </div>
            </div>
            <div
                class="p-5 rounded border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'"
            >
                <div class="text-xs uppercase text-slate-400">Transaksi Selesai</div>
                <div class="text-2xl font-extrabold mt-1">{{ stats.total_transaksi ?? 0 }} Nota</div>
            </div>
            <div
                class="p-5 rounded border"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'"
            >
                <div class="text-xs uppercase text-slate-400">Total Pelanggan</div>
                <div class="text-2xl font-extrabold mt-1">{{ stats.total_pelanggan ?? 0 }}</div>
            </div>
        </div>

        <Link
            href="/laporan"
            class="inline-block px-5 py-2.5 text-xs font-mono font-bold uppercase text-white rounded shadow"
            :style="{ backgroundColor: themeState.accentColor }"
        >
            Buka Laporan Lengkap (View, Multiple JOIN, Subquery, Audit Log) &rarr;
        </Link>
    </AdminLayout>
</template>
