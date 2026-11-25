<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useTheme, formatRupiah, playBeepSound } from '@/lib/useTheme';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    pelangganList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'selected', 'created']);
const { activeTokens } = useTheme();

const activeTab = ref('cari');
const searchQuery = ref('');
const rfidScanInput = ref('');
const rfidSearchRef = ref(null);
const newRfidInputRef = ref(null);
const loading = ref(false);
const errorMessage = ref('');

const newCustomer = ref({
    rfid_uid: '',
    nama_pelanggan: '',
    kota: '',
    no_telepon: '',
});

const filteredPelanggan = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.pelangganList;
    }

    return props.pelangganList.filter(
        (p) =>
            (p.nama_pelanggan || '').toLowerCase().includes(q) ||
            (p.kota || '').toLowerCase().includes(q) ||
            (p.no_telepon || '').toLowerCase().includes(q) ||
            (p.rfid_uid || '').toLowerCase().includes(q),
    );
});

const selectCustomer = (pelanggan) => {
    playBeepSound();
    emit('selected', pelanggan);
    emit('close');
};

const handleRfidTapLookup = (customUid = null) => {
    errorMessage.value = '';
    const uid = String(customUid ?? rfidScanInput.value).trim().toLowerCase();
    if (!uid) {
        return;
    }

    const matched = props.pelangganList.find(
        (p) => (p.rfid_uid || '').toLowerCase() === uid,
    );

    if (matched) {
        rfidScanInput.value = '';
        selectCustomer(matched);
    } else {
        errorMessage.value = `Kartu RFID "${customUid ?? rfidScanInput.value}" belum terdaftar. Silakan daftarkan di tab "+ Daftar Member Baru".`;
        newCustomer.value.rfid_uid = String(customUid ?? rfidScanInput.value).trim();
        activeTab.value = 'baru';
    }
};

const focusNewRfid = () => {
    nextTick(() => newRfidInputRef.value?.focus());
};

const submitQuickRegister = async () => {
    errorMessage.value = '';
    loading.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/api/pelanggan', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(newCustomer.value),
        });

        const payload = await response.json();

        if (!response.ok) {
            const firstErr =
                payload?.errors && Object.values(payload.errors)[0]
                    ? Object.values(payload.errors)[0][0]
                    : payload?.message || 'Gagal mendaftarkan pelanggan.';
            errorMessage.value = firstErr;
            return;
        }

        const created = payload.data;
        newCustomer.value = { rfid_uid: '', nama_pelanggan: '', kota: '', no_telepon: '' };
        playBeepSound();
        emit('created', created);
        emit('selected', created);
        emit('close');
    } catch (err) {
        errorMessage.value = err.message || 'Terjadi kesalahan jaringan.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            errorMessage.value = '';
            rfidScanInput.value = '';
            nextTick(() => rfidSearchRef.value?.focus());
        }
    },
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono"
    >
        <div class="w-full max-w-xl rounded border-2 shadow-2xl overflow-hidden toko-panel">
            <!-- Header Solid -->
            <div
                class="px-4 py-3 flex items-center justify-between text-white font-bold"
                :style="{ backgroundColor: activeTokens.primary }"
            >
                <h3 class="text-sm uppercase tracking-wider">
                    [F3] Pilih Member / Tap Kartu RFID Pelanggan
                </h3>
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-2.5 py-0.5 text-xs font-bold bg-[#b91c1c] text-white rounded"
                >
                    TUTUP [ESC]
                </button>
            </div>

            <!-- Tabs -->
            <div class="grid grid-cols-2 border-b-2 text-xs font-extrabold uppercase" :style="{ borderColor: activeTokens.border }">
                <button
                    type="button"
                    @click="activeTab = 'cari'"
                    class="py-2.5 text-center transition"
                    :style="
                        activeTab === 'cari'
                            ? { backgroundColor: activeTokens.primary, color: '#ffffff' }
                            : { backgroundColor: activeTokens.theadBg, color: activeTokens.textMain }
                    "
                >
                    🪪 Tap RFID / Cari Member ({{ pelangganList.length }})
                </button>
                <button
                    type="button"
                    @click="activeTab = 'baru'"
                    class="py-2.5 text-center transition"
                    :style="
                        activeTab === 'baru'
                            ? { backgroundColor: activeTokens.primary, color: '#ffffff' }
                            : { backgroundColor: activeTokens.theadBg, color: activeTokens.textMain }
                    "
                >
                    + Daftar Member &amp; Kartu RFID Baru
                </button>
            </div>

            <!-- Tab 1: Tap RFID Member atau Cari Nama -->
            <div v-if="activeTab === 'cari'" class="p-4 space-y-3">
                <!-- Kolom Khusus Tap Kartu RFID Member -->
                <form @submit.prevent="handleRfidTapLookup()" class="p-3 rounded border-2 toko-banner-info space-y-2">
                    <div class="flex items-center justify-between text-xs font-extrabold uppercase">
                        <span>🪪 Tap Kartu RFID Member Pelanggan:</span>
                        <span class="text-[10px] px-1.5 py-0.5 bg-[#111111] text-[#00ff66] rounded-sm">READER READY</span>
                    </div>
                    <div class="flex gap-2">
                        <input
                            ref="rfidSearchRef"
                            v-model="rfidScanInput"
                            type="text"
                            placeholder="Tempelkan kartu RFID pelanggan ke reader (mis. RFID-PEL-001)..."
                            class="flex-1 px-3 py-1.5 text-xs rounded border-2 font-extrabold toko-input-scan"
                        />
                        <button
                            type="submit"
                            class="px-3 py-1.5 text-xs font-extrabold rounded border border-black toko-btn-pay"
                        >
                            BACA RFID
                        </button>
                    </div>
                    <!-- Tombol Simulasi Tap RFID Member -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[10px] font-bold">Simulasi Tap:</span>
                        <button
                            v-for="p in pelangganList.filter((item) => item.rfid_uid)"
                            :key="'rfid-' + p.id_pelanggan"
                            type="button"
                            @click="handleRfidTapLookup(p.rfid_uid)"
                            class="px-2 py-0.5 text-[10px] font-extrabold rounded-sm border border-black text-[#111111]"
                            :style="{ backgroundColor: activeTokens.keycap }"
                        >
                            🪪 {{ p.nama_pelanggan }} ({{ p.rfid_uid }})
                        </button>
                    </div>
                </form>

                <!-- Pencarian Manual Nama / Kota / Telepon -->
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Atau ketik nama pelanggan, kota, no telepon, atau UID RFID..."
                    class="w-full px-3 py-2 text-xs rounded border-2 font-bold toko-input-scan"
                />

                <div class="max-h-56 overflow-y-auto divide-y border-2 rounded" :style="{ borderColor: activeTokens.border }">
                    <div
                        v-for="p in filteredPelanggan"
                        :key="p.id_pelanggan"
                        @click="selectCustomer(p)"
                        class="px-3 py-2.5 flex items-center justify-between text-xs cursor-pointer transition hover:opacity-90"
                    >
                        <div>
                            <div class="font-extrabold flex flex-wrap items-center gap-1.5">
                                <span>{{ p.nama_pelanggan }}</span>
                                <span
                                    v-if="!p.is_walk_in"
                                    class="px-1.5 py-0.5 text-[10px] font-extrabold rounded-sm border border-black"
                                    :class="{
                                        'bg-[#ffd54f] text-[#111111]': p.tier === 'Gold' || p.tier === 'VIP',
                                        'bg-[#e2e8f0] text-[#0f172a]': p.tier === 'Silver',
                                        'bg-[#047857] text-white': !p.tier || p.tier === 'Reguler',
                                    }"
                                >
                                    ★ {{ p.tier || 'Reguler' }} (Diskon {{ p.diskon_tier_persen ?? 2 }}%)
                                </span>
                                <span
                                    v-if="p.rfid_uid"
                                    class="px-1.5 py-0.5 text-[10px] font-mono font-extrabold bg-[#1e293b] text-white rounded-sm"
                                >
                                    🪪 {{ p.rfid_uid }}
                                </span>
                            </div>
                            <div class="text-[11px]" :style="{ color: activeTokens.textMuted }">
                                Kota: {{ p.kota }} • Poin: <strong>{{ p.poin || 0 }} pts</strong> • Saldo RFID:
                                <strong>{{ formatRupiah(p.saldo || 0) }}</strong>
                            </div>
                        </div>
                        <span
                            class="px-2.5 py-1 text-[10px] font-extrabold text-white rounded-sm"
                            :style="{ backgroundColor: activeTokens.primary }"
                        >
                            PILIH
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Quick Register Member Baru + Kartu RFID -->
            <form v-else @submit.prevent="submitQuickRegister" class="p-4 space-y-3 text-xs">
                <div
                    v-if="errorMessage"
                    class="p-2.5 rounded border-2 font-extrabold toko-banner-error"
                >
                    ⚠ {{ errorMessage }}
                </div>

                <div>
                    <label class="block font-bold uppercase mb-1">
                        🪪 UID Kartu RFID Member (Opsional — Tap Kartu ke Reader)
                    </label>
                    <div class="flex gap-2">
                        <input
                            ref="newRfidInputRef"
                            v-model="newCustomer.rfid_uid"
                            type="text"
                            placeholder="Klik 'Tap Kartu' lalu tempelkan kartu RFID pelanggan..."
                            @keydown.enter.prevent
                            class="flex-1 px-3 py-2 rounded border-2 font-extrabold toko-input-scan"
                        />
                        <button
                            type="button"
                            @click="focusNewRfid"
                            class="px-3 py-2 font-extrabold rounded border border-black text-[#111111]"
                            :style="{ backgroundColor: activeTokens.keycap }"
                        >
                            🪪 Tap Kartu
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block font-bold uppercase mb-1">Nama Pelanggan / Member *</label>
                    <input
                        v-model="newCustomer.nama_pelanggan"
                        type="text"
                        required
                        placeholder="Misal: Budi Santoso"
                        class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold uppercase mb-1">Kota Asal *</label>
                        <input
                            v-model="newCustomer.kota"
                            type="text"
                            required
                            placeholder="Misal: Ponorogo"
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        />
                    </div>

                    <div>
                        <label class="block font-bold uppercase mb-1">No. Telepon</label>
                        <input
                            v-model="newCustomer.no_telepon"
                            type="text"
                            placeholder="Misal: 081234567890"
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        />
                    </div>
                </div>

                <div class="pt-2 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-3 py-2 border-2 rounded font-bold"
                        :style="{ borderColor: activeTokens.border }"
                    >
                        BATAL
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 font-extrabold rounded border-2 border-black shadow disabled:opacity-50 toko-btn-pay"
                    >
                        {{ loading ? 'MENYIMPAN...' : 'SIMPAN & PILIH MEMBER' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
