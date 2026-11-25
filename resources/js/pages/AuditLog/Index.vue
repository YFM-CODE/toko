<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme } from '@/lib/useTheme';

const props = defineProps({
    logs: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { activeTokens } = useTheme();

const search = ref(props.filters.search || '');
const namaTabel = ref(props.filters.nama_tabel || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const exportQueryString = computed(() => {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('search', search.value);
    }
    if (namaTabel.value) {
        params.set('nama_tabel', namaTabel.value);
    }
    if (dateFrom.value) {
        params.set('date_from', dateFrom.value);
    }
    if (dateTo.value) {
        params.set('date_to', dateTo.value);
    }
    const q = params.toString();
    return q ? `?${q}` : '';
});

const applyFilter = () => {
    router.get(
        '/audit-log',
        {
            search: search.value,
            nama_tabel: namaTabel.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Audit Log Sistem" />

    <AdminLayout title="Audit Riwayat Log Aktivitas & Trigger (tbl_log — Read-Only)">
        <div class="mb-4 flex justify-end gap-2 text-xs font-medium">
            <a
                :href="`/export/audit-log/excel${exportQueryString}`"
                class="px-3 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
            >
                Export Excel Audit Log
            </a>
            <a
                :href="`/export/audit-log/pdf${exportQueryString}`"
                target="_blank"
                class="px-3 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
            >
                Export PDF Audit Log
            </a>
        </div>

        <form
            @submit.prevent="applyFilter"
            class="p-4 rounded-md border mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs font-mono toko-panel"
        >
            <input
                v-model="search"
                type="text"
                placeholder="Cari aktivitas atau perubahan data..."
                class="px-3 py-2 rounded-md border font-medium toko-input-scan"
            />

            <select
                v-model="namaTabel"
                @change="applyFilter"
                class="px-3 py-2 rounded-md border font-medium toko-input-scan"
            >
                <option value="">Semua Tabel (idx_log_tabel_waktu)</option>
                <option value="tbl_produk">tbl_produk</option>
                <option value="tbl_pelanggan">tbl_pelanggan</option>
                <option value="tbl_transaksi">tbl_transaksi</option>
                <option value="tbl_detail_transaksi">tbl_detail_transaksi</option>
                <option value="tbl_users">tbl_users</option>
            </select>

            <input
                v-model="dateFrom"
                type="date"
                class="px-3 py-2 rounded-md border font-medium toko-input-scan"
            />

            <input
                v-model="dateTo"
                type="date"
                class="px-3 py-2 rounded-md border font-medium toko-input-scan"
            />

            <button
                type="submit"
                class="py-2 font-semibold uppercase text-white rounded-md bg-[#1E3A5F] hover:bg-[#162C49] transition"
            >
                Terapkan Filter
            </button>
        </form>

        <div class="rounded-md border overflow-hidden toko-panel">
            <table class="w-full text-xs text-left font-mono">
                <thead class="uppercase text-[11px] font-semibold border-b toko-thead">
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">Waktu</th>
                        <th class="p-3">Nama Tabel</th>
                        <th class="p-3">Aktivitas</th>
                        <th class="p-3">ID Referensi</th>
                        <th class="p-3">Data Lama (OLD)</th>
                        <th class="p-3">Data Baru (NEW)</th>
                        <th class="p-3">Aktor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="log in logs.data || []" :key="log.id_log" class="hover:bg-slate-50/70">
                        <td class="p-3 font-semibold">#{{ log.id_log }}</td>
                        <td class="p-3 text-[11px] text-slate-600">{{ log.waktu }}</td>
                        <td class="p-3 font-semibold">{{ log.nama_tabel }}</td>
                        <td class="p-3 font-medium">{{ log.aktivitas }}</td>
                        <td class="p-3">#{{ log.id_referensi ?? '-' }}</td>
                        <td class="p-3 text-slate-600 text-[11px]">{{ log.data_lama || '-' }}</td>
                        <td class="p-3 text-slate-900 font-medium text-[11px]">{{ log.data_baru || '-' }}</td>
                        <td class="p-3 font-medium">{{ log.user?.nama_lengkap || 'DB Trigger' }}</td>
                    </tr>
                    <tr v-if="(logs.data || []).length === 0">
                        <td colspan="8" class="p-6 text-center font-bold">
                            Tidak ada catatan audit log yang sesuai filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
