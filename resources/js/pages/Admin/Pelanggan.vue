<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme, formatRupiah, playBeepSound } from '@/lib/useTheme';

const props = defineProps({
    pelanggan: {
        type: Object,
        default: () => ({ data: [] }),
    },
    daftarKota: {
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
const role = computed(() => page.props.auth?.user?.role || 'admin');
const isAdmin = computed(() => role.value === 'admin');
const canCreate = computed(() => role.value === 'admin' || role.value === 'kasir');

const search = ref(props.filters.search || '');
const kota = ref(props.filters.kota || '');
const showModal = ref(false);
const showTopUpModal = ref(false);
const selectedTopUpMember = ref(null);
const editingId = ref(null);
const rfidInputRef = ref(null);
const namaInputRef = ref(null);

const form = useForm({
    rfid_uid: '',
    nama_pelanggan: '',
    kota: '',
    no_telepon: '',
    tier: 'Reguler',
    poin: 0,
    saldo: 0,
    username: '',
    password: '',
});

const topUpForm = useForm({
    nominal: 50000,
});

const exportQueryString = computed(() => {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('search', search.value);
    }
    if (kota.value) {
        params.set('kota', kota.value);
    }
    const q = params.toString();
    return q ? `?${q}` : '';
});

const applyFilter = () => {
    router.get(
        '/admin/pelanggan',
        { search: search.value, kota: kota.value },
        { preserveState: true, replace: true },
    );
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.tier = 'Reguler';
    form.poin = 10;
    form.saldo = 0;
    form.clearErrors();
    showModal.value = true;
    nextTick(() => rfidInputRef.value?.focus());
};

const openEdit = (item) => {
    editingId.value = item.id_pelanggan;
    form.rfid_uid = item.rfid_uid || '';
    form.nama_pelanggan = item.nama_pelanggan;
    form.kota = item.kota;
    form.no_telepon = item.no_telepon || '';
    form.tier = item.tier || 'Reguler';
    form.poin = Number(item.poin || 0);
    form.saldo = Number(item.saldo || 0);
    form.username = '';
    form.password = '';
    form.clearErrors();
    showModal.value = true;
};

const openTopUp = (item) => {
    selectedTopUpMember.value = item;
    topUpForm.nominal = 50000;
    topUpForm.clearErrors();
    showTopUpModal.value = true;
};

const submitTopUp = () => {
    if (!selectedTopUpMember.value) {
        return;
    }
    topUpForm.post(`/pelanggan/${selectedTopUpMember.value.id_pelanggan}/topup`, {
        onSuccess: () => {
            showTopUpModal.value = false;
            playBeepSound();
        },
    });
};

const focusRfidInput = () => {
    nextTick(() => rfidInputRef.value?.focus());
};

const handleRfidReaderEnter = () => {
    playBeepSound();
    nextTick(() => namaInputRef.value?.focus());
};

const submitForm = () => {
    if (editingId.value) {
        form.put(`/admin/pelanggan/${editingId.value}`, {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post('/pelanggan', {
            onSuccess: () => (showModal.value = false),
        });
    }
};

const deleteItem = (item) => {
    if (confirm(`Hapus data pelanggan "${item.nama_pelanggan}"?`)) {
        router.delete(`/admin/pelanggan/${item.id_pelanggan}`);
    }
};
</script>

<template>
    <Head title="Data Member & Kartu RFID Pelanggan" />

    <AdminLayout title="Manajemen Data Member, Poin Loyalitas & Saldo RFID (tbl_pelanggan)">
        <div class="mb-4 p-3 rounded border-2 text-xs font-mono font-bold toko-banner-info">
            💡 FITUR MEMBER LOYALITAS: Setiap Member otomatis mendapatkan <strong>Diskon Tier (Reguler 2%, Silver 5%, Gold 10%)</strong>, <strong>+1 Poin tiap Rp 10.000 belanja (1 Poin = Rp 100)</strong>, serta dapat melakukan <strong>Top-Up &amp; Pembayaran Saldo Kartu RFID</strong> di Kasir POS.
        </div>

        <div
            v-if="page.props.errors?.pelanggan"
            class="mb-4 p-3 rounded border-2 text-xs font-mono font-extrabold toko-banner-error"
        >
            ⚠ {{ page.props.errors.pelanggan }}
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <form @submit.prevent="applyFilter" class="flex flex-wrap items-center gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari nama, kota, telepon, atau UID RFID..."
                    class="px-3 py-2 text-xs rounded border-2 font-bold w-64 toko-input-scan"
                />
                <select
                    v-model="kota"
                    @change="applyFilter"
                    class="px-3 py-2 text-xs rounded border-2 font-bold toko-panel"
                >
                    <option value="">Semua Kota (idx_pelanggan_kota)</option>
                    <option v-for="k in daftarKota" :key="k" :value="k">{{ k }}</option>
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
                    :href="`/export/pelanggan/excel${exportQueryString}`"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
                >
                    Export Excel
                </a>
                <a
                    :href="`/export/pelanggan/pdf${exportQueryString}`"
                    target="_blank"
                    class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition"
                >
                    Export PDF
                </a>
                <button
                    v-if="canCreate"
                    type="button"
                    @click="openCreate"
                    class="px-4 py-2 font-semibold uppercase text-white rounded-md bg-[#1E3A5F] hover:bg-[#162C49] transition"
                >
                    + Tambah Member &amp; RFID
                </button>
            </div>
        </div>

        <div class="rounded-md border overflow-hidden toko-panel">
            <table class="w-full text-xs text-left">
                <thead class="uppercase font-semibold text-[11px] border-b toko-thead">
                    <tr>
                        <th class="p-3 w-14">ID</th>
                        <th class="p-3">UID Kartu RFID</th>
                        <th class="p-3">Nama Member / Pelanggan</th>
                        <th class="p-3 text-center">Tier &amp; Diskon</th>
                        <th class="p-3 text-right">Poin</th>
                        <th class="p-3 text-right">Saldo RFID</th>
                        <th class="p-3">Kota &amp; Telp</th>
                        <th class="p-3 text-right">Trx</th>
                        <th v-if="canCreate" class="p-3 text-center w-52">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 font-mono">
                    <tr v-for="item in pelanggan.data || []" :key="item.id_pelanggan" class="hover:bg-slate-50/70">
                        <td class="p-3 font-semibold">#{{ item.id_pelanggan }}</td>
                        <td class="p-3">
                            <span
                                v-if="item.rfid_uid"
                                class="font-medium text-slate-700"
                            >
                                {{ item.rfid_uid }}
                            </span>
                            <span v-else class="text-slate-400">-</span>
                        </td>
                        <td class="p-3 font-sans font-medium">
                            {{ item.nama_pelanggan }}
                            <span
                                v-if="item.is_walk_in"
                                class="ml-1.5 text-[10px] font-mono text-slate-500"
                            >
                                (WALK-IN)
                            </span>
                        </td>
                        <td class="p-3 text-center">
                            <span
                                v-if="!item.is_walk_in"
                                class="font-medium text-slate-700"
                            >
                                {{ item.tier || 'Reguler' }} ({{ item.diskon_tier_persen }}%)
                            </span>
                            <span v-else class="text-slate-400">-</span>
                        </td>
                        <td class="p-3 text-right font-medium text-slate-700 tabular-nums">
                            {{ item.is_walk_in ? '-' : `${item.poin || 0} pts` }}
                        </td>
                        <td class="p-3 text-right font-semibold tabular-nums">
                            {{ item.is_walk_in ? '-' : formatRupiah(item.saldo || 0) }}
                        </td>
                        <td class="p-3 font-sans">
                            <div class="font-medium">{{ item.kota }}</div>
                            <div class="text-[11px] font-mono text-slate-500">
                                {{ item.no_telepon || '-' }}
                            </div>
                        </td>
                        <td class="p-3 text-right font-semibold tabular-nums">{{ item.transaksi_count ?? 0 }}</td>
                        <td v-if="canCreate" class="p-3 text-center space-x-1.5 font-sans">
                            <button
                                v-if="!item.is_walk_in"
                                type="button"
                                @click="openTopUp(item)"
                                class="px-2 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium text-[11px]"
                                title="Top-Up Saldo Kartu RFID Member"
                            >
                                +Saldo
                            </button>
                            <button
                                v-if="isAdmin"
                                type="button"
                                @click="openEdit(item)"
                                class="px-2 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium text-[11px]"
                            >
                                Edit
                            </button>
                            <button
                                v-if="isAdmin && !item.is_walk_in"
                                type="button"
                                @click="deleteItem(item)"
                                class="px-2 py-1 rounded border border-red-200 bg-white text-red-700 hover:bg-red-50 font-medium text-[11px]"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="(pelanggan.data || []).length === 0">
                        <td colspan="9" class="p-6 text-center font-bold">
                            Belum ada data pelanggan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Top-Up Saldo Kartu RFID Member -->
        <div
            v-if="showTopUpModal && selectedTopUpMember"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono"
        >
            <div class="w-full max-w-md rounded border-2 p-5 shadow-2xl toko-panel">
                <h3 class="text-sm font-extrabold uppercase mb-3 pb-2 border-b-2" :style="{ borderColor: activeTokens.border }">
                    💰 Top-Up Saldo Kartu RFID Member
                </h3>

                <form @submit.prevent="submitTopUp" class="space-y-3 text-xs">
                    <div class="p-3 rounded border-2 toko-banner-info space-y-1">
                        <div class="font-extrabold">Member: {{ selectedTopUpMember.nama_pelanggan }}</div>
                        <div>UID RFID: <strong>{{ selectedTopUpMember.rfid_uid || 'Belum Ada' }}</strong></div>
                        <div>Saldo Saat Ini: <strong>{{ formatRupiah(selectedTopUpMember.saldo || 0) }}</strong></div>
                    </div>

                    <div>
                        <label class="block font-extrabold uppercase mb-1">Nominal Top-Up (Rp) *</label>
                        <input
                            v-model.number="topUpForm.nominal"
                            type="number"
                            min="1000"
                            step="1000"
                            required
                            class="w-full px-3 py-2 rounded border-2 text-base font-extrabold tabular-nums toko-input-scan"
                        />
                        <div class="grid grid-cols-4 gap-1.5 mt-2">
                            <button
                                v-for="amt in [20000, 50000, 100000, 250000]"
                                :key="amt"
                                type="button"
                                @click="topUpForm.nominal = amt"
                                class="py-1 border rounded font-extrabold text-[11px] toko-thead"
                            >
                                {{ formatRupiah(amt) }}
                            </button>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showTopUpModal = false"
                            class="px-3 py-1.5 border-2 rounded font-bold"
                            :style="{ borderColor: activeTokens.border }"
                        >
                            BATAL
                        </button>
                        <button
                            type="submit"
                            :disabled="topUpForm.processing"
                            class="px-4 py-1.5 rounded font-extrabold border-2 border-black toko-btn-pay"
                        >
                            {{ topUpForm.processing ? 'MEMPROSES...' : 'PROSES TOP-UP SALDO' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Tambah / Edit Member + Tap Kartu RFID + Akun Login -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 overflow-y-auto"
        >
            <div class="w-full max-w-lg max-h-[95vh] overflow-y-auto rounded border-2 p-5 shadow-2xl toko-panel font-mono">
                <h3 class="text-sm font-extrabold uppercase mb-4 pb-2 border-b-2" :style="{ borderColor: activeTokens.border }">
                    {{ editingId ? 'Edit Data Member, Tier & Kartu RFID' : 'Tambah Member & Kartu RFID Baru' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold uppercase mb-1">
                            🪪 UID Kartu RFID Pelanggan (Tap Kartu ke Reader)
                        </label>
                        <div class="flex gap-1.5">
                            <input
                                ref="rfidInputRef"
                                v-model="form.rfid_uid"
                                type="text"
                                placeholder="Tempelkan kartu RFID pelanggan (mis. 0009364923)..."
                                @keydown.enter.prevent="handleRfidReaderEnter"
                                class="flex-1 px-3 py-2 rounded border-2 font-extrabold toko-input-scan"
                            />
                            <button
                                type="button"
                                @click="focusRfidInput"
                                class="px-3 py-2 rounded border-2 border-black font-extrabold text-[#111111]"
                                :style="{ backgroundColor: activeTokens.keycap }"
                            >
                                🪪 Tap
                            </button>
                        </div>
                        <div v-if="form.errors.rfid_uid" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.rfid_uid }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase mb-1">Nama Member *</label>
                            <input
                                ref="namaInputRef"
                                v-model="form.nama_pelanggan"
                                type="text"
                                required
                                class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                            />
                        </div>

                        <div>
                            <label class="block font-bold uppercase mb-1">Tier Member</label>
                            <select
                                v-model="form.tier"
                                class="w-full px-3 py-2 rounded border-2 font-extrabold toko-input-scan"
                            >
                                <option value="Reguler">Reguler (Diskon 2%)</option>
                                <option value="Silver">Silver (Diskon 5%)</option>
                                <option value="Gold">Gold (Diskon 10%)</option>
                                <option value="VIP">VIP (Diskon 10%)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase mb-1">Kota Asal *</label>
                            <input
                                v-model="form.kota"
                                type="text"
                                required
                                class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                            />
                        </div>

                        <div>
                            <label class="block font-bold uppercase mb-1">No. Telepon</label>
                            <input
                                v-model="form.no_telepon"
                                type="text"
                                class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase mb-1">Poin Loyalitas</label>
                            <input
                                v-model.number="form.poin"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 rounded border-2 font-extrabold tabular-nums toko-input-scan"
                            />
                        </div>

                        <div>
                            <label class="block font-bold uppercase mb-1">Saldo Kartu RFID (Rp)</label>
                            <input
                                v-model.number="form.saldo"
                                type="number"
                                min="0"
                                step="1000"
                                class="w-full px-3 py-2 rounded border-2 font-extrabold tabular-nums toko-input-scan"
                            />
                        </div>
                    </div>

                    <div class="p-3 rounded border-2 space-y-2" :style="{ borderColor: activeTokens.border }">
                        <div class="font-extrabold uppercase text-[11px]">
                            🔑 Akun Login Portal Member (Opsional)
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input
                                v-model="form.username"
                                type="text"
                                placeholder="Username login (opsional)"
                                class="px-2.5 py-1.5 rounded border-2 font-bold toko-input-scan"
                            />
                            <input
                                v-model="form.password"
                                type="password"
                                placeholder="Password (default: member123)"
                                class="px-2.5 py-1.5 rounded border-2 font-bold toko-input-scan"
                            />
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
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
                            SIMPAN MEMBER
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
