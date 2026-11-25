<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import {
    useTheme,
    formatRupiah,
    generateQrSvgDataUrl,
    playBeepSound,
    detectPaymentQrProvider,
} from '@/lib/useTheme';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    initialTab: {
        type: String,
        default: 'SCAN_CPM', // 'SCAN_CPM' | 'QRIS_MPM'
    },
    kodeTransaksi: {
        type: String,
        default: 'TRX-0001',
    },
    totalAkhir: {
        type: Number,
        default: 0,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    gatewayConfig: {
        type: Object,
        default: () => ({
            xendit_configured: false,
            mode_label: 'SIMULASI LOKAL (SIAP XENDIT)',
            settlement_destination: 'BANK BRI • 0071-01-002345-30-8 a.n TOKO RITEL MANDIRI',
        }),
    },
});

const emit = defineEmits(['close', 'confirm-payment', 'open-camera-scan']);
const { activeTokens } = useTheme();

const activeTab = ref(props.initialTab);
const cpmTokenInput = ref('');
const selectedChannel = ref('AUTO');
const cpmInputRef = ref(null);

const xenditQrisData = ref(null);
const loadingXenditQris = ref(false);

const channels = [
    { id: 'AUTO', label: 'Deteksi Otomatis dari QR (BRI, BCA, Mandiri, DANA, GoPay, dll.)' },
    { id: 'QR BANK BRI (BRIMO)', label: 'QR Bank BRI (BRImo / QRIS BRI)' },
    { id: 'QR BANK BCA', label: 'QR Bank BCA (myBCA / BCA Mobile)' },
    { id: 'QR BANK MANDIRI (LIVIN)', label: 'QR Bank Mandiri (Livin)' },
    { id: 'QR BANK BNI', label: 'QR Bank BNI' },
    { id: 'QR DANA', label: 'QR DANA Kode Bayar' },
    { id: 'QR GOPAY', label: 'QR GoPay Kode Bayar' },
    { id: 'QR SHOPEEPAY', label: 'QR ShopeePay Kode Bayar' },
    { id: 'QR OVO', label: 'QR OVO Barcode' },
];

const fetchXenditDynamicQris = async () => {
    loadingXenditQris.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/payment/xendit-qris', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                kode_transaksi: props.kodeTransaksi,
                amount: props.totalAkhir,
            }),
        });
        const payload = await res.json();
        if (payload?.data) {
            xenditQrisData.value = payload.data;
        }
    } catch {
        // Ignore network error and use fallback
    } finally {
        loadingXenditQris.value = false;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            activeTab.value = props.initialTab || 'SCAN_CPM';
            cpmTokenInput.value = '';
            fetchXenditDynamicQris();
            nextTick(() => {
                cpmInputRef.value?.focus();
            });
        }
    },
);

watch(activeTab, (tab) => {
    if (tab === 'SCAN_CPM') {
        nextTick(() => cpmInputRef.value?.focus());
    } else if (tab === 'QRIS_MPM' && !xenditQrisData.value) {
        fetchXenditDynamicQris();
    }
});

const detectedProviderLabel = computed(() => {
    const raw = String(cpmTokenInput.value || '').trim();
    if (!raw) {
        return selectedChannel.value === 'AUTO'
            ? 'Menunggu Scan QR BRI / BCA / Mandiri / DANA / GoPay...'
            : selectedChannel.value;
    }

    const autoDetected = detectPaymentQrProvider(raw);
    if (autoDetected) {
        return autoDetected;
    }

    return selectedChannel.value === 'AUTO' ? 'QR PAYMENT GATEWAY' : selectedChannel.value;
});

const qrisDisplayString = computed(() => {
    return xenditQrisData.value?.qr_string || `QRIS:BRI:${props.kodeTransaksi}:${props.totalAkhir}`;
});

const submitCpmScan = () => {
    const token = String(cpmTokenInput.value || '').trim();
    if (token.length < 6) {
        return;
    }

    playBeepSound();
    emit('confirm-payment', {
        metode_pembayaran: 'SCAN_CPM',
        payment_channel: detectedProviderLabel.value,
        payment_token: token,
        nominal_bayar: props.totalAkhir,
    });
};

const fillQuickDemoToken = (sampleQrPayload, channelId) => {
    selectedChannel.value = channelId;
    cpmTokenInput.value = sampleQrPayload;
    nextTick(() => {
        submitCpmScan();
    });
};

const confirmQrisMpmPayment = (channelName = 'QRIS XENDIT / BANK BRI') => {
    playBeepSound();
    emit('confirm-payment', {
        metode_pembayaran: 'QRIS_MPM',
        payment_channel: channelName,
        payment_token: xenditQrisData.value?.qr_id || qrisDisplayString.value,
        nominal_bayar: props.totalAkhir,
    });
};

defineExpose({
    setScannedToken(code) {
        activeTab.value = 'SCAN_CPM';
        cpmTokenInput.value = String(code || '').trim();
        nextTick(() => {
            if (cpmTokenInput.value.length >= 6) {
                submitCpmScan();
            }
        });
    },
});
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono text-xs no-print overflow-y-auto"
    >
        <div
            class="w-full max-w-xl max-h-[95vh] overflow-y-auto rounded-sm border-2 shadow-2xl toko-panel"
            :style="{ borderColor: activeTokens.border }"
        >
            <!-- Header Modal Payment Gateway -->
            <div
                class="px-4 py-3 text-white flex items-center justify-between border-b-2"
                :style="{
                    backgroundColor: activeTokens.primary,
                    borderColor: activeTokens.primaryDark,
                }"
            >
                <div>
                    <div class="font-extrabold text-sm uppercase tracking-wider">
                        💳 PAYMENT GATEWAY (XENDIT &amp; AUTO-DETECT QR BANK)
                    </div>
                    <div class="text-[11px] opacity-90">
                        Tujuan Dana: <strong>{{ gatewayConfig.settlement_destination }}</strong>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-2.5 py-1 rounded-sm font-extrabold text-[#111111]"
                    :style="{ backgroundColor: activeTokens.keycap }"
                >
                    TUTUP [ESC]
                </button>
            </div>

            <!-- Kotak LED Tagihan & Deteksi Bank Otomatis -->
            <div class="p-3 bg-[#111111] border-b-2 border-black flex items-center justify-between">
                <div>
                    <div class="text-[11px] font-bold text-[#94a3b8] uppercase">
                        DETEKSI PEMBAYARAN OTOMATIS ({{ gatewayConfig.mode_label }})
                    </div>
                    <div class="text-xs font-extrabold text-white mt-0.5">
                        Terdeteksi: <span class="text-[#ffd54f]">{{ detectedProviderLabel }}</span>
                    </div>
                </div>
                <div
                    class="text-2xl font-extrabold tabular-nums"
                    :style="{ color: activeTokens.ledText }"
                >
                    {{ formatRupiah(totalAkhir) }}
                </div>
            </div>

            <!-- Tab Mode: Scan HP Pembeli (CPM) vs Tampilkan QRIS Xendit/BRI Toko (MPM) -->
            <div class="grid grid-cols-2 border-b-2" :style="{ borderColor: activeTokens.border }">
                <button
                    type="button"
                    @click="activeTab = 'SCAN_CPM'"
                    class="py-2.5 px-3 font-extrabold uppercase text-center border-r transition"
                    :style="{
                        backgroundColor:
                            activeTab === 'SCAN_CPM' ? activeTokens.primary : activeTokens.theadBg,
                        color: activeTab === 'SCAN_CPM' ? '#ffffff' : activeTokens.textMain,
                        borderColor: activeTokens.border,
                    }"
                >
                    🔫 [F9] SCAN QR BRI / BANK / E-WALLET HP PEMBELI
                </button>
                <button
                    type="button"
                    @click="activeTab = 'QRIS_MPM'"
                    class="py-2.5 px-3 font-extrabold uppercase text-center transition"
                    :style="{
                        backgroundColor:
                            activeTab === 'QRIS_MPM' ? activeTokens.primary : activeTokens.theadBg,
                        color: activeTab === 'QRIS_MPM' ? '#ffffff' : activeTokens.textMain,
                    }"
                >
                    📱 [F10] TAMPILKAN QRIS XENDIT / BRI TOKO
                </button>
            </div>

            <!-- TAB 1: SCAN BARCODE / QR HP PEMBELI (DETEKSI OTOMATIS QR BRI, BCA, MANDIRI, DANA, GOPAY) -->
            <div v-if="activeTab === 'SCAN_CPM'" class="p-4 space-y-3">
                <div class="p-3 rounded-sm border-2 toko-banner-info">
                    <div class="font-extrabold uppercase text-xs">
                        Deteksi Otomatis QR Bank Indonesia (NNS &amp; EMVCo):
                    </div>
                    <div class="mt-1 text-[11px] font-bold">
                        Saat Anda scan QR Code dari HP pembeli (misal <strong>QR BRImo / Bank BRI</strong> dengan kode NNS <code>93600002</code> / <code>ID.CO.BRI</code>, BCA <code>93600014</code>, Mandiri <code>93600008</code>, DANA <code>93600915</code>, GoPay <code>93600914</code>), sistem <strong>otomatis mengenali nama banknya</strong> dan langsung mencetaknya di struk!
                    </div>
                </div>

                <!-- Pilihan Channel / Deteksi Otomatis -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-center">
                    <label class="font-extrabold uppercase">Mode Deteksi QR:</label>
                    <select
                        v-model="selectedChannel"
                        class="px-2.5 py-1.5 border-2 font-extrabold rounded-sm toko-input-scan"
                    >
                        <option v-for="ch in channels" :key="ch.id" :value="ch.id">
                            {{ ch.label }}
                        </option>
                    </select>
                </div>

                <!-- Input Fokus untuk Alat Scanner Laser / 2D Imager -->
                <form @submit.prevent="submitCpmScan" class="space-y-2">
                    <label class="block font-extrabold uppercase">
                        TEMBAK / SCAN QR CODE PEMBAYARAN (CONTOH: QR BRI / BRIMO):
                    </label>
                    <div class="flex gap-2">
                        <input
                            ref="cpmInputRef"
                            v-model="cpmTokenInput"
                            type="text"
                            required
                            minlength="6"
                            placeholder="Scan QR BRI (93600002 / ID.CO.BRI), BCA, Mandiri, DANA, GoPay..."
                            class="flex-1 px-3 py-2.5 text-base font-extrabold border-2 outline-none toko-input-scan"
                        />
                        <button
                            type="button"
                            @click="emit('open-camera-scan')"
                            class="px-3 py-2 font-extrabold text-[#111111] border-2 border-black rounded-sm shrink-0"
                            :style="{ backgroundColor: activeTokens.keycap }"
                            title="Gunakan Kamera Webcam untuk Scan QR BRI / E-Wallet di HP Pembeli"
                        >
                            📷 SCAN KAMERA
                        </button>
                    </div>

                    <button
                        type="submit"
                        :disabled="processing || cpmTokenInput.trim().length < 6"
                        class="w-full py-3 font-extrabold text-sm uppercase tracking-wider rounded-sm border-2 border-black transition disabled:opacity-40 toko-btn-pay"
                    >
                        {{
                            processing
                                ? '⚡ MEMPROSES & MENCETAK STRUK...'
                                : `⚡ BAYAR (${detectedProviderLabel}) & CETAK STRUK [ENTER]`
                        }}
                    </button>
                </form>

                <!-- Tombol Simulasi Cepat 1-Klik (Format Payload Asli QRIS Bank Indonesia) -->
                <div class="pt-2 border-t-2 border-dashed" :style="{ borderColor: activeTokens.border }">
                    <div class="text-[11px] font-bold mb-1.5" :style="{ color: activeTokens.textMuted }">
                        SIMULASI SCAN QR CODE ASLI (OTOMATIS TERDETEKSI &amp; TERCETAK DI STRUK):
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226580014ID.CO.BRI.WWW0118936000020100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#0b3c6f] text-white"
                        >
                            ⚡ Scan QR BRI (BRImo)
                        </button>
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226580014ID.CO.BCA.WWW0118936000140100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#1e293b] text-white"
                        >
                            ⚡ Scan QR BCA
                        </button>
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226620018ID.CO.MANDIRI.WWW0118936000080100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#7f1d1d] text-white"
                        >
                            ⚡ Scan QR Mandiri
                        </button>
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226590015ID.DANA.WWW0118936009150100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#047857] text-white"
                        >
                            ⚡ Scan QR DANA
                        </button>
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226600016ID.CO.GOPAY.WWW0118936009140100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#166534] text-white"
                        >
                            ⚡ Scan QR GoPay
                        </button>
                        <button
                            type="button"
                            @click="
                                fillQuickDemoToken(
                                    '00020101021226610017ID.SHOPEEPAY.WWW0118936009180100088991',
                                    'AUTO',
                                )
                            "
                            class="py-2 px-2 border-2 border-black rounded-sm font-extrabold text-[11px] bg-[#b91c1c] text-white"
                        >
                            ⚡ Scan QR ShopeePay
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TAMPILKAN QRIS RESMI TOKO (YAZID, ELEKTRONIK / GOPAY MERCHANT) -->
            <div v-else class="p-4 space-y-3 text-center">
                <div class="text-xs font-extrabold uppercase">
                    QRIS RESMI TOKO — {{ gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK' }}
                </div>
                <div class="text-[11px] font-bold" :style="{ color: activeTokens.textMuted }">
                    NMID: <strong>{{ gatewayConfig.merchant_nmid || 'ID1026647000219' }}</strong> • Dana masuk langsung ke rekening Merchant Anda
                </div>

                <div class="flex flex-col items-center justify-center py-1">
                    <div class="p-2.5 bg-white border-2 border-black rounded-sm shadow max-w-[270px] w-full">
                        <img
                            :src="gatewayConfig.qris_image_url || '/images/qris-toko.jpg'"
                            :alt="`QRIS ${gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK'}`"
                            class="w-full max-h-64 object-contain mx-auto rounded-sm"
                        />
                        <div class="mt-2 pt-2 border-t-2 border-dashed border-[#111111]">
                            <div class="text-[10px] font-bold text-[#475569] uppercase">
                                NOMINAL YANG HARUS DIBAYAR PEMBELI:
                            </div>
                            <div class="text-xl font-extrabold text-[#b91c1c] tabular-nums">
                                {{ formatRupiah(totalAkhir) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 rounded-sm border-2 text-[11px] font-bold toko-banner-info">
                    Minta pembeli scan QRIS di atas via GoPay, BCA, Mandiri, BNI, BRI, DANA, OVO, atau ShopeePay. Setelah notifikasi masuk di aplikasi <strong>GoPay Merchant</strong> Anda, klik tombol konfirmasi di bawah.
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <button
                        type="button"
                        :disabled="processing"
                        @click="confirmQrisMpmPayment(`QRIS GOPAY MERCHANT (${gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK'})`)"
                        class="py-3 px-3 font-extrabold uppercase rounded-sm border-2 border-black toko-btn-pay"
                    >
                        {{
                            processing
                                ? '⚡ MEMPROSES...'
                                : '✓ DANA QRIS MASUK & CETAK STRUK'
                        }}
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'SCAN_CPM'"
                        class="py-3 px-3 font-extrabold uppercase rounded-sm border-2 border-black text-white"
                        :style="{ backgroundColor: activeTokens.primary }"
                    >
                        🔫 PINDAH KE SCAN QR HP PEMBELI
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
