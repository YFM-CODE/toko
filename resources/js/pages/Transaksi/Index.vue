<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ReceiptModal from '@/Components/Pos/ReceiptModal.vue';
import { formatRupiah } from '@/lib/useTheme';

const props = defineProps({
    transaksi: {
        type: Object,
        default: () => ({ data: [] }),
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
const role = computed(() => page.props.auth?.user?.role || 'kasir');
const isAdmin = computed(() => role.value === 'admin');

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const idUser = ref(props.filters.id_user || '');
const selectedReceipt = ref(null);
const selectedVerifyTrx = ref(null);

const exportQueryString = computed(() => {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('search', search.value);
    }
    if (status.value) {
        params.set('status', status.value);
    }
    if (idUser.value) {
        params.set('id_user', idUser.value);
    }
    const q = params.toString();
    return q ? `?${q}` : '';
});

const applyFilter = () => {
    router.get(
        '/transaksi',
        { search: search.value, status: status.value, id_user: idUser.value },
        { preserveState: true, replace: true },
    );
};

const voidTransaksi = (trx) => {
    if (confirm(`Batalkan (VOID) transaksi ${trx.kode_transaksi}? Stok produk akan dikembalikan otomatis.`)) {
        router.patch(`/admin/transaksi/${trx.id_transaksi}/void`);
    }
};

const confirmPendingPayment = (trx) => {
    selectedVerifyTrx.value = trx;
};

const submitVerifyLunas = () => {
    if (!selectedVerifyTrx.value) {
        return;
    }
    const trx = selectedVerifyTrx.value;
    router.patch(
        `/transaksi/${trx.id_transaksi}/confirm-payment`,
        {
            payment_ref: trx.payment_ref || (trx.metode_pembayaran === 'TUNAI' ? 'CASH-LUNAS' : 'QRIS-VERIFIED'),
            nominal_bayar: trx.total_belanja,
        },
        {
            onSuccess: () => {
                selectedVerifyTrx.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Riwayat & Cetak Struk Transaksi" />

    <AdminLayout title="Riwayat Transaksi, Cetak Struk & Export (tbl_transaksi)">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 text-xs">
            <form @submit.prevent="applyFilter" class="flex flex-wrap items-center gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari kode struk atau nama pelanggan..."
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 w-64 focus:border-[#1E3A5F] outline-none"
                />
                <select
                    v-model="idUser"
                    :disabled="isKasirScoped"
                    @change="applyFilter"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900 disabled:bg-slate-100"
                >
                    <option v-if="!isKasirScoped" value="">Semua Kasir</option>
                    <option v-for="k in kasirList" :key="k.id_user" :value="k.id_user">
                        Kasir: {{ k.nama_lengkap }}
                    </option>
                </select>
                <select
                    v-model="status"
                    @change="applyFilter"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-900"
                >
                    <option value="">Semua Status</option>
                    <option value="selesai">Selesai (Lunas)</option>
                    <option value="pending">Pending (Belum Bayar)</option>
                    <option value="batal">Batal (Void)</option>
                </select>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-md font-medium text-white bg-[#1E3A5F] hover:bg-[#162C49] transition"
                >
                    Filter
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="`/export/laporan-view/excel${exportQueryString}`"
                    class="px-3 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
                >
                    Export Excel
                </a>
                <a
                    :href="`/export/laporan-view/pdf${exportQueryString}`"
                    class="px-3 py-2 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition"
                >
                    Export PDF
                </a>
                <Link
                    v-if="role === 'kasir'"
                    href="/pos"
                    class="px-4 py-2 font-medium text-white bg-[#1E3A5F] hover:bg-[#162C49] rounded-md transition"
                >
                    + Transaksi Baru (POS)
                </Link>
            </div>
        </div>

        <div class="rounded-md bg-white border border-slate-300 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 uppercase text-xs font-semibold text-slate-600 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Kode Struk</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Kasir</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                            <th class="px-4 py-3 text-right">Diskon</th>
                            <th class="px-4 py-3 text-right">Total Belanja</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 tabular-nums">
                        <tr v-for="t in transaksi.data || []" :key="t.id_transaksi" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ t.kode_transaksi }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ t.tanggal }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ t.pelanggan?.nama_pelanggan || 'Pelanggan Umum' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ t.kasir?.nama_lengkap || 'System' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ formatRupiah(t.subtotal) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">- {{ formatRupiah(t.diskon) }}</td>
                            <td
                                class="px-4 py-3 text-right font-mono font-bold"
                                :class="t.status === 'batal' ? 'text-red-700 line-through' : 'text-slate-900'"
                            >
                                {{ formatRupiah(t.total_belanja) }}
                            </td>
                            <td
                                class="px-4 py-3 text-center font-mono text-xs uppercase"
                                :class="
                                    t.status === 'selesai'
                                        ? 'font-medium text-slate-700'
                                        : 'font-semibold text-red-700'
                                "
                            >
                                {{
                                    t.status === 'selesai'
                                        ? 'SELESAI'
                                        : t.status === 'pending'
                                          ? 'BELUM BAYAR'
                                          : 'BATAL (VOID)'
                                }}
                            </td>
                            <td class="px-4 py-3 text-center space-x-1.5">
                                <template v-if="t.status === 'selesai'">
                                    <button
                                        type="button"
                                        @click="selectedReceipt = t"
                                        class="px-2.5 py-1 rounded text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 font-medium"
                                    >
                                        Struk
                                    </button>
                                    <Link
                                        :href="`/transaksi/${t.id_transaksi}`"
                                        class="px-2.5 py-1 rounded text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 font-medium inline-block"
                                    >
                                        Detail
                                    </Link>
                                </template>
                                <template v-else-if="t.status === 'pending'">
                                    <button
                                        v-if="role === 'admin' || role === 'kasir'"
                                        type="button"
                                        @click="confirmPendingPayment(t)"
                                        class="px-2.5 py-1 rounded text-white bg-[#1E3A5F] hover:bg-[#162C49] font-medium"
                                    >
                                        {{ t.bukti_pembayaran_url ? 'Cek Bukti & Verifikasi' : 'Konfirmasi Lunas' }}
                                    </button>
                                    <span v-else class="text-slate-400 font-mono text-[11px]">
                                        Struk Terkunci
                                    </span>
                                </template>
                                <span v-else class="text-slate-400 font-mono text-[11px]">
                                    Dibatalkan
                                </span>
                                <button
                                    v-if="isAdmin && (t.status === 'selesai' || t.status === 'pending')"
                                    type="button"
                                    @click="voidTransaksi(t)"
                                    class="px-2.5 py-1 rounded text-red-700 bg-white border border-red-300 hover:bg-red-50 font-medium"
                                >
                                    Void
                                </button>
                            </td>
                        </tr>
                        <tr v-if="(transaksi.data || []).length === 0">
                            <td colspan="9" class="p-6 text-center text-slate-500">
                                Belum ada data transaksi.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ReceiptModal
            :show="Boolean(selectedReceipt)"
            :receipt="selectedReceipt"
            :tunai="Number(selectedReceipt?.total_belanja || 0)"
            :kembalian="0"
            @new-transaction="selectedReceipt = null"
        />

        <!-- Modal Verifikasi Bukti Pembayaran oleh Kasir / Admin -->
        <div
            v-if="selectedVerifyTrx"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto"
        >
            <div class="w-full max-w-md rounded-md border border-slate-300 bg-white p-5 shadow-xl space-y-4 text-xs">
                <div class="flex items-start justify-between gap-2 pb-2 border-b border-slate-200">
                    <div>
                        <div class="text-[10px] font-mono font-semibold uppercase text-slate-500">
                            VERIFIKASI PEMBAYARAN PESANAN MEMBER
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 font-mono mt-0.5">
                            {{ selectedVerifyTrx.kode_transaksi }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="selectedVerifyTrx = null"
                        class="text-slate-500 hover:text-slate-800 font-bold"
                    >
                        ✕
                    </button>
                </div>

                <div class="p-3 rounded-md bg-slate-50 border border-slate-200 space-y-1 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Pelanggan:</span>
                        <span class="font-semibold text-slate-900">{{ selectedVerifyTrx.pelanggan?.nama_pelanggan || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Metode:</span>
                        <span class="font-semibold text-slate-900">{{ selectedVerifyTrx.payment_channel || selectedVerifyTrx.metode_pembayaran }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Total Tagihan:</span>
                        <span class="font-bold text-slate-900">{{ formatRupiah(selectedVerifyTrx.total_belanja) }}</span>
                    </div>
                    <div v-if="selectedVerifyTrx.payment_ref" class="flex justify-between">
                        <span class="text-slate-600">Ref Member:</span>
                        <span class="font-semibold text-slate-900">{{ selectedVerifyTrx.payment_ref }}</span>
                    </div>
                </div>

                <div v-if="selectedVerifyTrx.bukti_pembayaran_url" class="space-y-1.5">
                    <div class="font-semibold text-slate-700">Foto Bukti Pembayaran dari Member:</div>
                    <div class="p-2 rounded border border-slate-300 bg-slate-50 text-center">
                        <img
                            :src="selectedVerifyTrx.bukti_pembayaran_url"
                            alt="Bukti Transfer Member"
                            class="max-h-64 mx-auto rounded object-contain"
                        />
                    </div>
                </div>
                <div v-else class="p-3 rounded border border-dashed border-slate-300 text-center text-slate-500">
                    Member belum mengunggah foto bukti transfer (pembayaran tunai di kasir atau belum upload).
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="selectedVerifyTrx = null"
                        class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitVerifyLunas"
                        class="px-4 py-2 rounded-md font-semibold text-white bg-[#1E3A5F] hover:bg-[#162C49]"
                    >
                        Verifikasi Lunas &amp; Buka Struk
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
