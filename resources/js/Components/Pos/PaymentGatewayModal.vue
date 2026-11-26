<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
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
            duitku_configured: false,
            sandbox: true,
            mode_label: 'DUITKU SANDBOX',
            settlement_destination: 'DUITKU SANDBOX • YAZID, ELEKTRONIK',
        }),
    },
});

const emit = defineEmits(['close', 'confirm-payment', 'open-camera-scan']);
const { activeTokens } = useTheme();

const activeTab = ref(props.initialTab);
const mpmSubMode = ref('MANUAL_QRIS'); // 'MANUAL_QRIS' | 'DUITKU_AUTO'
const cpmTokenInput = ref('');
const selectedChannel = ref('AUTO');
const selectedDuitkuMethod = ref('BR');
const cpmInputRef = ref(null);

const duitkuQrisData = ref(null);
const loadingDuitkuQris = ref(false);
const copiedTextLabel = ref('');
const checkingDuitkuStatus = ref(false);
const duitkuStatusMessage = ref('');

const duitkuMethods = [
    { id: 'BR', short: 'BRI VA', label: 'BRI Virtual Account (BRIVA)' },
    { id: 'BC', short: 'BCA VA', label: 'BCA Virtual Account' },
    { id: 'M2', short: 'Mandiri VA', label: 'Mandiri Virtual Account' },
    { id: 'I1', short: 'BNI VA', label: 'BNI Virtual Account' },
    { id: 'B1', short: 'CIMB VA', label: 'CIMB Niaga Virtual Account' },
    { id: 'SP', short: 'QRIS', label: 'QRIS Duitku (All Bank & E-Wallet)' },
    { id: 'DA', short: 'DANA', label: 'DANA E-Wallet' },
];

const copyToClipboard = async (text, label = 'Kode') => {
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(String(text));
        copiedTextLabel.value = `${label} berhasil disalin!`;
        playBeepSound();
        setTimeout(() => {
            copiedTextLabel.value = '';
        }, 2500);
    } catch {
        copiedTextLabel.value = `Salin manual: ${text}`;
    }
};

const selectDuitkuBank = (methodId) => {
    selectedDuitkuMethod.value = methodId;
    duitkuStatusMessage.value = '';
    fetchDuitkuDynamicQris();
};

const checkDuitkuSandboxStatus = async (silent = false) => {
    if (!duitkuQrisData.value?.reference_id || checkingDuitkuStatus.value || duitkuQrisData.value?.gateway_error) {
        return;
    }
    checkingDuitkuStatus.value = true;
    if (!silent) {
        duitkuStatusMessage.value = 'Mengecek status pembayaran ke server Duitku Sandbox...';
    }
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/payment/duitku/check-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                merchant_order_id: duitkuQrisData.value.reference_id,
            }),
        });
        const payload = await res.json();
        if (payload?.is_paid) {
            stopPosAutoCheck();
            duitkuStatusMessage.value = '✓ LUNAS di Duitku Sandbox! Mencetak struk otomatis...';
            const methodObj = duitkuMethods.find((m) => m.id === selectedDuitkuMethod.value);
            confirmQrisMpmPayment(`DUITKU ${methodObj?.short || selectedDuitkuMethod.value}`);
        } else if (!silent) {
            duitkuStatusMessage.value = `Status Duitku: ${payload?.message || 'BELUM DIBAYAR (PENDING)'} — Silakan bayar di Simulator Sandbox terlebih dahulu.`;
        }
    } catch {
        if (!silent) {
            duitkuStatusMessage.value = 'Gagal memeriksa status ke server Duitku.';
        }
    } finally {
        checkingDuitkuStatus.value = false;
    }
};

let posAutoCheckTimer = null;

const stopPosAutoCheck = () => {
    if (posAutoCheckTimer) {
        clearInterval(posAutoCheckTimer);
        posAutoCheckTimer = null;
    }
};

const startPosAutoCheck = () => {
    stopPosAutoCheck();
    if (!props.show || activeTab.value !== 'QRIS_MPM' || mpmSubMode.value !== 'DUITKU_AUTO') {
        return;
    }
    posAutoCheckTimer = setInterval(() => {
        if (props.show && activeTab.value === 'QRIS_MPM' && mpmSubMode.value === 'DUITKU_AUTO' && duitkuQrisData.value?.reference_id && !duitkuQrisData.value?.gateway_error) {
            checkDuitkuSandboxStatus(true);
        }
    }, 3500);
};

watch([() => props.show, activeTab, mpmSubMode, duitkuQrisData], () => {
    if (props.show && activeTab.value === 'QRIS_MPM' && mpmSubMode.value === 'DUITKU_AUTO' && duitkuQrisData.value?.reference_id && !duitkuQrisData.value?.gateway_error) {
        startPosAutoCheck();
    } else {
        stopPosAutoCheck();
    }
});

onBeforeUnmount(() => {
    stopPosAutoCheck();
});

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

const fetchDuitkuDynamicQris = async () => {
    loadingDuitkuQris.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/payment/duitku-qris', {
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
                payment_method: selectedDuitkuMethod.value,
            }),
        });
        const payload = await res.json();
        if (payload?.data) {
            duitkuQrisData.value = payload.data;
        }
    } catch {
        // Ignore network error and use fallback
    } finally {
        loadingDuitkuQris.value = false;
    }
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            activeTab.value = props.initialTab || 'SCAN_CPM';
            cpmTokenInput.value = '';
            fetchDuitkuDynamicQris();
            nextTick(() => {
                cpmInputRef.value?.focus();
            });
        }
    },
);

watch(activeTab, (tab) => {
    if (tab === 'SCAN_CPM') {
        nextTick(() => cpmInputRef.value?.focus());
    } else if (tab === 'QRIS_MPM' && !duitkuQrisData.value) {
        fetchDuitkuDynamicQris();
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
    return duitkuQrisData.value?.qr_string || `QRIS:DUITKU:${props.kodeTransaksi}:${props.totalAkhir}`;
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

const confirmQrisMpmPayment = (channelName = 'QRIS DUITKU SANDBOX') => {
    playBeepSound();
    emit('confirm-payment', {
        metode_pembayaran: 'QRIS_MPM',
        payment_channel: channelName,
        payment_token: duitkuQrisData.value?.qr_id || qrisDisplayString.value,
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
                        💳 PAYMENT GATEWAY (DUITKU SANDBOX &amp; AUTO-DETECT QR BANK)
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
                    📱 [F10] TAMPILKAN QRIS / VA DUITKU TOKO
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

            <!-- TAB 2: PILIH QRIS TOKO (MANUAL) ATAU DUITKU SANDBOX (OTOMATIS VA BANK / QRIS) -->
            <div v-else class="p-4 space-y-3 text-center">
                <!-- Toggle Mode Manual (QRIS Toko) vs Otomatis (Duitku Sandbox) -->
                <div class="grid grid-cols-2 gap-1.5 p-1 rounded-md bg-slate-100 border border-slate-300">
                    <button
                        type="button"
                        @click="mpmSubMode = 'MANUAL_QRIS'"
                        class="py-1.5 px-2 rounded text-xs font-bold transition"
                        :class="
                            mpmSubMode === 'MANUAL_QRIS'
                                ? 'bg-[#1E3A5F] text-white'
                                : 'text-slate-700 hover:bg-white'
                        "
                    >
                        QRIS Toko (Manual — GoPay Merchant)
                    </button>
                    <button
                        type="button"
                        @click="
                            mpmSubMode = 'DUITKU_AUTO';
                            fetchDuitkuDynamicQris();
                        "
                        class="py-1.5 px-2 rounded text-xs font-bold transition"
                        :class="
                            mpmSubMode === 'DUITKU_AUTO'
                                ? 'bg-[#1E3A5F] text-white'
                                : 'text-slate-700 hover:bg-white'
                        "
                    >
                        Duitku VA / QRIS (Otomatis)
                    </button>
                </div>

                <!-- SUB-MODE A: QRIS RESMI TOKO (MANUAL — YAZID, ELEKTRONIK) -->
                <div v-if="mpmSubMode === 'MANUAL_QRIS'" class="space-y-3">
                    <div class="text-xs font-extrabold uppercase">
                        QRIS RESMI TOKO — {{ gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK' }}
                    </div>
                    <div class="text-[11px] font-bold text-slate-600">
                        NMID: <strong>{{ gatewayConfig.merchant_nmid || 'ID1026647000219' }}</strong> • Bebas Nominal Berapa Pun
                    </div>

                    <div class="flex flex-col items-center justify-center py-1">
                        <div class="p-2.5 bg-white border border-slate-300 rounded-md shadow-sm max-w-[270px] w-full">
                            <img
                                :src="gatewayConfig.qris_image_url || '/images/qris-toko.jpg'"
                                :alt="`QRIS ${gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK'}`"
                                class="w-full max-h-60 object-contain mx-auto rounded"
                            />
                            <div class="mt-2 pt-2 border-t border-dashed border-slate-300">
                                <div class="text-[10px] font-bold text-slate-500 uppercase">
                                    NOMINAL TAGIHAN ASLI:
                                </div>
                                <div class="text-xl font-extrabold text-slate-900 tabular-nums">
                                    {{ formatRupiah(totalAkhir) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            :disabled="processing"
                            @click="confirmQrisMpmPayment(`QRIS GOPAY MERCHANT (${gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK'})`)"
                            class="py-2.5 px-3 font-bold uppercase rounded-md toko-btn-pay"
                        >
                            {{ processing ? 'MEMPROSES...' : '✓ DANA QRIS MASUK & CETAK STRUK' }}
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'SCAN_CPM'"
                            class="py-2.5 px-3 font-bold uppercase rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                        >
                            Pindah ke Scan QR HP Pembeli
                        </button>
                    </div>
                </div>

                <!-- SUB-MODE B: DUITKU SANDBOX (OTOMATIS — VA BANK / QRIS + COPY KODE VA) -->
                <div v-else class="space-y-3">
                    <div class="text-xs font-extrabold uppercase">
                        PILIH BANK VIRTUAL ACCOUNT / QRIS — {{ gatewayConfig.mode_label || 'DUITKU SANDBOX' }}
                    </div>

                    <div class="flex flex-wrap justify-center gap-1.5">
                        <button
                            v-for="m in duitkuMethods"
                            :key="m.id"
                            type="button"
                            @click="selectDuitkuBank(m.id)"
                            class="px-3 py-1.5 rounded-md border text-xs font-bold transition"
                            :class="
                                selectedDuitkuMethod === m.id
                                    ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                            "
                        >
                            {{ m.short }}
                        </button>
                    </div>

                    <div class="flex flex-col items-center justify-center py-1">
                        <div class="p-3 bg-white border border-slate-300 rounded-md shadow-sm max-w-md w-full space-y-3">
                            <div v-if="loadingDuitkuQris" class="py-8 text-xs font-bold text-slate-500">
                                Menghubungi Duitku Sandbox API ({{ selectedDuitkuMethod }})...
                            </div>

                            <!-- Jika Nominal di bawah Rp 10.000 (Batas Minimal VA Duitku) -->
                            <div
                                v-else-if="duitkuQrisData?.gateway_error"
                                class="p-3 rounded-md bg-amber-50 border border-amber-300 text-amber-900 text-left space-y-2"
                            >
                                <div class="font-bold text-xs">
                                    ⚠ Informasi Batas Minimal Duitku:
                                </div>
                                <p class="text-[11px] leading-relaxed">
                                    {{ duitkuQrisData.gateway_error }}
                                </p>
                                <button
                                    type="button"
                                    @click="mpmSubMode = 'MANUAL_QRIS'"
                                    class="w-full py-2 px-3 rounded-md bg-[#1E3A5F] text-white text-xs font-bold hover:bg-[#162C49]"
                                >
                                    Gunakan QRIS Toko (Manual — Bebas Nominal) &rarr;
                                </button>
                            </div>

                            <template v-else>
                                <!-- Tampilan Nomor Virtual Account + Tombol Copy -->
                                <div
                                    v-if="duitkuQrisData?.va_number"
                                    class="p-3 bg-slate-50 rounded-md border border-slate-300 space-y-2"
                                >
                                    <div class="text-[11px] font-bold text-slate-500 uppercase">
                                        NOMOR VIRTUAL ACCOUNT ({{ duitkuMethods.find((x) => x.id === selectedDuitkuMethod)?.label }}):
                                    </div>
                                    <div class="text-xl font-extrabold font-mono tracking-wider text-slate-900 select-all bg-white py-2 px-3 rounded border border-slate-300">
                                        {{ duitkuQrisData.va_number }}
                                    </div>
                                    <div class="flex justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="copyToClipboard(duitkuQrisData.va_number, 'Nomor VA')"
                                            class="px-3 py-1.5 rounded-md bg-[#1E3A5F] text-white text-xs font-semibold hover:bg-[#162C49]"
                                        >
                                            📋 Salin / Copy Nomor VA
                                        </button>
                                        <button
                                            v-if="duitkuQrisData?.qr_id"
                                            type="button"
                                            @click="copyToClipboard(duitkuQrisData.qr_id, 'Reference Duitku')"
                                            class="px-3 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50"
                                        >
                                            📋 Copy Ref
                                        </button>
                                    </div>
                                </div>

                                <!-- Tampilan QRIS jika memilih SP -->
                                <div v-else-if="duitkuQrisData?.qr_string" class="space-y-2">
                                    <img
                                        :src="generateQrSvgDataUrl(duitkuQrisData.qr_string)"
                                        alt="QRIS Dinamis Duitku"
                                        class="w-44 h-44 mx-auto border border-slate-300 p-1 bg-white rounded"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(duitkuQrisData.qr_id, 'Reference QRIS')"
                                        class="px-3 py-1 rounded border border-slate-300 bg-white text-slate-700 text-[11px] font-semibold"
                                    >
                                        📋 Copy Ref: {{ duitkuQrisData.qr_id }}
                                    </button>
                                </div>

                                <div v-if="duitkuQrisData?.qr_id" class="text-[11px] text-slate-600 space-y-0.5 text-left bg-slate-50 p-2 rounded border border-slate-200">
                                    <div class="flex justify-between">
                                        <span>Ref Duitku:</span>
                                        <strong class="select-all">{{ duitkuQrisData.qr_id }}</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Order ID:</span>
                                        <strong class="select-all">{{ duitkuQrisData.reference_id }}</strong>
                                    </div>
                                </div>

                                <div
                                    v-if="copiedTextLabel"
                                    class="py-1.5 px-3 rounded bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-bold"
                                >
                                    ✓ {{ copiedTextLabel }}
                                </div>

                                <div v-if="duitkuQrisData?.payment_url" class="space-y-1.5 pt-1">
                                    <div class="flex items-center justify-center gap-1.5 py-1 px-2 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold">
                                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                                        <span>AUTO-CHECK AKTIF (Mengecek pembayaran otomatis tiap 3 detik)</span>
                                    </div>
                                    <a
                                        :href="duitkuQrisData.payment_url"
                                        target="_blank"
                                        class="block w-full py-2 px-3 rounded-md bg-[#1E3A5F] text-white text-xs font-bold hover:bg-[#162C49]"
                                    >
                                        ↗ 1. Buka Halaman Simulator Bayar Duitku Sandbox
                                    </a>
                                    <button
                                        type="button"
                                        :disabled="checkingDuitkuStatus"
                                        @click="checkDuitkuSandboxStatus(false)"
                                        class="w-full py-2 px-3 rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 text-xs font-bold"
                                    >
                                        {{ checkingDuitkuStatus ? 'Mengecek ke Duitku...' : '🔄 Cek Manual Sekarang' }}
                                    </button>
                                </div>

                                <div
                                    v-if="duitkuStatusMessage"
                                    class="p-2 rounded border border-slate-300 bg-slate-50 text-[11px] font-semibold text-slate-700"
                                >
                                    {{ duitkuStatusMessage }}
                                </div>
                            </template>

                            <div class="pt-2 border-t border-dashed border-slate-300 flex items-center justify-between">
                                <span class="text-[11px] font-bold text-slate-500 uppercase">
                                    NOMINAL TAGIHAN ASLI:
                                </span>
                                <span class="text-base font-extrabold text-slate-900 tabular-nums">
                                    {{ formatRupiah(totalAkhir) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                        <button
                            type="button"
                            :disabled="processing"
                            @click="confirmQrisMpmPayment(`DUITKU ${selectedDuitkuMethod} (${gatewayConfig.merchant_code || 'SANDBOX'})`)"
                            class="py-2.5 px-3 font-bold uppercase rounded-md toko-btn-pay"
                        >
                            {{ processing ? 'MEMPROSES...' : '✓ KONFIRMASI LUNAS & CETAK STRUK' }}
                        </button>
                        <button
                            type="button"
                            @click="activeTab = 'SCAN_CPM'"
                            class="py-2.5 px-3 font-bold uppercase rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                        >
                            Pindah ke Scan QR HP Pembeli
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
