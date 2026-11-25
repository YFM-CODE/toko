<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {
    formatRupiah,
    useTheme,
    generateQrSvgDataUrl,
    printThermalElement,
    printDirectToEpson,
    printSettingsState,
    resetPrintSettings,
} from '@/lib/useTheme';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    receipt: {
        type: Object,
        default: null,
    },
    tunai: {
        type: Number,
        default: 0,
    },
    kembalian: {
        type: Number,
        default: 0,
    },
    initialPrintMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['new-transaction', 'close']);
const { activeTokens } = useTheme();
const paperSize = ref(printSettingsState.paperSize || '80mm');
const showCalibrator = ref(false);
const isPrintingDirect = ref(false);
const printStatus = ref({ type: '', message: '' });

const selectPaperSize = (size) => {
    paperSize.value = size;
    printSettingsState.paperSize = size;
    if (size === '58mm' && printSettingsState.contentWidthMm > 54) {
        printSettingsState.contentWidthMm = 48;
        printSettingsState.escPosCols = 30;
    } else if (size === '80mm' && printSettingsState.contentWidthMm < 56) {
        printSettingsState.contentWidthMm = 68;
        printSettingsState.escPosCols = 38;
    }
};

const printDirect = async () => {
    if (!props.receipt?.id_transaksi) {
        return;
    }

    isPrintingDirect.value = true;
    printStatus.value = { type: 'info', message: 'Mengirim struk langsung ke printer thermal...' };

    const result = await printDirectToEpson(props.receipt.id_transaksi, {
        tunai: props.tunai || props.receipt.total_belanja,
        kembalian: props.kembalian,
        paperSize: paperSize.value,
    });

    isPrintingDirect.value = false;

    if (result.ok) {
        printStatus.value = {
            type: 'success',
            message: result.message,
        };
    } else {
        printStatus.value = {
            type: 'error',
            message: `${result.message} Membuka dialog cetak browser sebagai cadangan...`,
        };
        printThermalElement('printable-receipt', paperSize.value);
    }
};

const printReceiptBrowser = () => {
    printThermalElement('printable-receipt', paperSize.value);
};

const shortPaymentLabel = (receipt) => {
    if (!receipt?.metode_pembayaran || receipt.metode_pembayaran === 'TUNAI') {
        return 'TUNAI (CASH)';
    }
    const ch = String(receipt.payment_channel || receipt.metode_pembayaran || '').toUpperCase();
    if (ch.includes('GOPAY MERCHANT')) {
        return 'QRIS GOPAY';
    }
    if (ch.length > 22) {
        return ch.slice(0, 22);
    }
    return ch;
};
</script>

<template>
    <div
        v-if="show && receipt && receipt.status === 'selesai'"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono overflow-y-auto"
    >
        <!-- Kertas Struk Digital (#fffef8 dengan teks #0f172a) -->
        <div
            class="w-full max-w-md max-h-[95vh] overflow-y-auto rounded border-2 shadow-2xl p-5 toko-receipt-paper border-[#94a3b8]"
        >
            <!-- Pilihan Lebar Kertas Struk Thermal & Tombol Kalibrasi Posisi -->
            <div class="mb-2 pb-2 border-b border-[#94a3b8] space-y-2 text-xs no-print">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex gap-1.5 font-extrabold">
                        <button
                            type="button"
                            @click="selectPaperSize('80mm')"
                            class="px-2 py-1 rounded-sm border border-[#111111]"
                            :class="paperSize === '80mm' ? 'bg-[#111111] text-[#ffd54f]' : 'bg-white text-[#111111]'"
                        >
                            80mm
                        </button>
                        <button
                            type="button"
                            @click="selectPaperSize('58mm')"
                            class="px-2 py-1 rounded-sm border border-[#111111]"
                            :class="paperSize === '58mm' ? 'bg-[#111111] text-[#ffd54f]' : 'bg-white text-[#111111]'"
                        >
                            58mm
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="showCalibrator = !showCalibrator"
                        class="px-2.5 py-1 rounded-sm border border-[#111111] font-extrabold text-[11px]"
                        :style="{ backgroundColor: activeTokens.keycap, color: '#111111' }"
                    >
                        ⚙ {{ showCalibrator ? 'Tutup Atur Posisi' : 'Atur Posisi (Kiri/Kanan/Atas)' }}
                    </button>
                </div>

                <!-- Panel Kalibrasi Langsung Posisi Cetak Kiri / Kanan / Atas / Lebar -->
                <div
                    v-if="showCalibrator"
                    class="p-2.5 rounded border-2 border-[#111111] bg-white text-[#111111] space-y-2 text-[11px]"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold uppercase">Kalibrasi Posisi Struk</span>
                        <button
                            type="button"
                            @click="resetPrintSettings(paperSize)"
                            class="px-2 py-0.5 rounded-sm bg-[#1e293b] text-white font-bold text-[10px]"
                        >
                            Reset Default
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Geser Kiri/Kanan (mm): <strong>{{ printSettingsState.offsetLeftMm }}mm</strong></span>
                            <input
                                v-model.number="printSettingsState.offsetLeftMm"
                                type="range"
                                min="0"
                                max="10"
                                step="1"
                                class="accent-[#0b3c6f]"
                            />
                        </label>
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Margin Kanan (mm): <strong>{{ printSettingsState.offsetRightMm }}mm</strong></span>
                            <input
                                v-model.number="printSettingsState.offsetRightMm"
                                type="range"
                                min="0"
                                max="10"
                                step="1"
                                class="accent-[#0b3c6f]"
                            />
                        </label>
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Jarak Atas (mm): <strong>{{ printSettingsState.offsetTopMm }}mm</strong></span>
                            <input
                                v-model.number="printSettingsState.offsetTopMm"
                                type="range"
                                min="-5"
                                max="10"
                                step="1"
                                class="accent-[#0b3c6f]"
                            />
                        </label>
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Lebar Isi (mm): <strong>{{ printSettingsState.contentWidthMm }}mm</strong></span>
                            <input
                                v-model.number="printSettingsState.contentWidthMm"
                                type="range"
                                :min="paperSize === '58mm' ? 40 : 58"
                                :max="paperSize === '58mm' ? 54 : 76"
                                step="1"
                                class="accent-[#0b3c6f]"
                            />
                        </label>
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Kolom Epson (1-Klik): <strong>{{ printSettingsState.escPosCols }}</strong></span>
                            <input
                                v-model.number="printSettingsState.escPosCols"
                                type="range"
                                min="28"
                                max="42"
                                step="1"
                                class="accent-[#047857]"
                            />
                        </label>
                        <label class="flex flex-col gap-0.5 font-bold">
                            <span>Geser Kiri Epson: <strong>{{ printSettingsState.escPosLeftSpace }} spasi</strong></span>
                            <input
                                v-model.number="printSettingsState.escPosLeftSpace"
                                type="range"
                                min="0"
                                max="6"
                                step="1"
                                class="accent-[#047857]"
                            />
                        </label>
                    </div>
                </div>
            </div>

            <!-- AREA CETAK THERMAL (#printable-receipt) -->
            <div
                id="printable-receipt"
                class="toko-receipt-paper font-mono text-xs mx-auto text-[#000000]"
                :style="{
                    maxWidth: `${printSettingsState.contentWidthMm}mm`,
                    paddingLeft: `${printSettingsState.offsetLeftMm}mm`,
                    paddingRight: `${printSettingsState.offsetRightMm}mm`,
                }"
            >
                <!-- Kop Struk Thermal dengan Logo Monokrom (#000000 & #ffffff) -->
                <div class="text-center border-b-2 border-dashed border-[#000000] pb-2 mb-2 flex flex-col items-center">
                    <ApplicationLogo variant="mono" :size="32" class="mb-1" />
                    <div class="text-sm font-extrabold uppercase tracking-wider text-[#000000]">
                        TOKO RITEL MANDIRI
                    </div>
                    <div class="text-[10px] font-bold text-[#000000]">
                        Jl. Raya Ponorogo No. 10 • Telp: (0352) 481234
                    </div>
                    <div class="text-[10px] font-bold text-[#000000]">
                        STRUK PENJUALAN RESMI
                    </div>
                </div>

                <!-- Metadata Transaksi -->
                <div class="text-[11px] space-y-1 border-b-2 border-dashed border-[#000000] pb-2 mb-2">
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">NO. STRUK:</span>
                        <span class="font-extrabold whitespace-nowrap shrink-0">{{ receipt.kode_transaksi }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">TANGGAL:</span>
                        <span class="font-bold whitespace-nowrap shrink-0">{{ receipt.tanggal }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">KASIR:</span>
                        <span class="font-bold">{{ receipt.kasir?.nama_lengkap || 'Kasir' }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">PELANGGAN:</span>
                        <span class="font-bold text-right">
                            {{ receipt.pelanggan?.nama_pelanggan || 'Pelanggan Umum' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">PEMBAYARAN:</span>
                        <span class="font-extrabold uppercase whitespace-nowrap shrink-0">
                            {{ shortPaymentLabel(receipt) }}
                        </span>
                    </div>
                    <div
                        v-if="receipt.payment_ref"
                        class="flex justify-between gap-2"
                    >
                        <span class="font-bold">NO. REF:</span>
                        <span class="font-extrabold whitespace-nowrap shrink-0">{{ receipt.payment_ref }}</span>
                    </div>
                </div>

                <!-- Rincian Item -->
                <div class="border-b-2 border-dashed border-[#000000] pb-2 mb-2 space-y-1.5 text-[11px]">
                    <div
                        v-for="(item, idx) in receipt.detail || []"
                        :key="idx"
                        class="flex justify-between items-start gap-2"
                    >
                        <div class="min-w-0">
                            <div class="font-extrabold">
                                {{ item.produk?.nama_produk || 'Produk #' + item.id_produk }}
                            </div>
                            <div class="text-[10px] font-bold tabular-nums">
                                {{ item.jumlah }} x {{ formatRupiah(item.harga) }}
                            </div>
                        </div>
                        <div class="font-extrabold tabular-nums whitespace-nowrap shrink-0">
                            {{ formatRupiah(item.subtotal_item) }}
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Total -->
                <div class="text-[11px] space-y-1 border-b-2 border-dashed border-[#000000] pb-2 mb-2 tabular-nums">
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">SUBTOTAL:</span>
                        <span class="font-bold whitespace-nowrap shrink-0">{{ formatRupiah(receipt.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">DISKON:</span>
                        <span class="font-bold whitespace-nowrap shrink-0">- {{ formatRupiah(receipt.diskon) }}</span>
                    </div>
                    <div class="flex justify-between gap-2 text-xs font-extrabold pt-1 border-t border-[#000000]">
                        <span>GRAND TOTAL:</span>
                        <span class="whitespace-nowrap shrink-0">{{ formatRupiah(receipt.total_belanja) }}</span>
                    </div>
                    <div class="flex justify-between gap-2 font-bold">
                        <span>PEMBAYARAN:</span>
                        <span class="font-extrabold uppercase whitespace-nowrap shrink-0">
                            {{ shortPaymentLabel(receipt) }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="font-bold">
                            {{
                                receipt.metode_pembayaran && receipt.metode_pembayaran !== 'TUNAI'
                                    ? 'NOMINAL BAYAR:'
                                    : 'TUNAI DITERIMA:'
                            }}
                        </span>
                        <span class="font-bold whitespace-nowrap shrink-0">{{ formatRupiah(receipt.nominal_bayar || tunai || receipt.total_belanja) }}</span>
                    </div>
                    <div class="flex justify-between gap-2 font-extrabold">
                        <span>KEMBALIAN:</span>
                        <span class="whitespace-nowrap shrink-0">{{ formatRupiah(receipt.kembalian ?? kembalian) }}</span>
                    </div>
                    <div
                        v-if="receipt.poin_didapat > 0 || receipt.poin_ditukar > 0"
                        class="flex justify-between gap-2 pt-1 border-t border-dashed border-[#000000] text-[10px]"
                    >
                        <span class="font-bold">POIN MEMBER:</span>
                        <span class="font-extrabold whitespace-nowrap shrink-0">
                            +{{ receipt.poin_didapat || 0 }} Poin
                            <template v-if="receipt.poin_ditukar > 0">
                                (-{{ receipt.poin_ditukar }})
                            </template>
                            • Total: {{ receipt.pelanggan?.poin ?? 0 }}
                        </span>
                    </div>
                </div>

                <!-- QR Code Struk & Ucapan Terima Kasih -->
                <div class="flex flex-col items-center text-center pt-1">
                    <img
                        :src="generateQrSvgDataUrl(receipt.kode_transaksi)"
                        :alt="receipt.kode_transaksi"
                        class="w-20 h-20 border border-[#000000] p-1 bg-white mb-1"
                    />
                    <div class="text-[10px] font-bold">{{ receipt.kode_transaksi }}</div>
                    <div class="text-[10px] font-extrabold mt-0.5">
                        TERIMA KASIH ATAS KUNJUNGAN ANDA
                    </div>
                    <div class="text-[9px] font-bold">
                        Barang yang sudah dibeli tidak dapat ditukar/dikembalikan
                    </div>
                </div>
            </div>

            <!-- Status Cetak Langsung -->
            <div
                v-if="printStatus.message || initialPrintMessage"
                class="mt-3 px-3 py-2 rounded-sm border-2 text-[11px] font-extrabold no-print"
                :class="{
                    'toko-banner-success': printStatus.type === 'success' || (!printStatus.type && initialPrintMessage),
                    'toko-banner-error': printStatus.type === 'error',
                    'toko-banner-info': printStatus.type === 'info',
                }"
            >
                {{ printStatus.message || initialPrintMessage }}
            </div>

            <!-- Tombol Aksi -->
            <div class="space-y-2 text-xs font-extrabold mt-3 no-print">
                <button
                    type="button"
                    :disabled="isPrintingDirect"
                    @click="printDirect"
                    class="w-full py-2.5 border-2 border-[#111111] rounded uppercase tracking-wider transition toko-btn-pay disabled:opacity-50"
                >
                    {{
                        isPrintingDirect
                            ? '⚡ MENGIRIM KE PRINTER...'
                            : `⚡ CETAK LANGSUNG 1-KLIK (${paperSize})`
                    }}
                </button>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        @click="printReceiptBrowser"
                        class="py-2 border-2 border-[#111111] rounded uppercase tracking-wider bg-white text-[#111111] transition"
                    >
                        🖨 PREVIEW BROWSER
                    </button>
                    <button
                        type="button"
                        @click="emit('new-transaction')"
                        class="py-2 text-white rounded uppercase tracking-wider border-2 border-[#111111] transition"
                        :style="{ backgroundColor: activeTokens.primary }"
                    >
                        TRANSAKSI BARU
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
