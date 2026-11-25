<script setup>
import { ref, watch, onUnmounted } from 'vue';
import { useTheme, generateQrSvgDataUrl } from '@/lib/useTheme';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    produkList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'detected']);
const { themeState } = useTheme();

const videoRef = ref(null);
const cameraActive = ref(false);
const statusText = ref('Siap memindai QR Code / Barcode produk...');
const manualCode = ref('');
let mediaStream = null;
let scanInterval = null;

const stopCamera = () => {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    cameraActive.value = false;
};

const startCamera = async () => {
    stopCamera();
    statusText.value = 'Mengaktifkan kamera perangkat...';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusText.value = 'Browser ini tidak mendukung akses kamera langsung. Gunakan Unggah Gambar QR atau tombol simulasi di bawah.';
        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
            audio: false,
        });

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            cameraActive.value = true;
            statusText.value = 'Arahkan QR Code / Barcode produk ke kamera...';
        }

        if ('BarcodeDetector' in window) {
            const detector = new window.BarcodeDetector({
                formats: ['qr_code', 'code_128', 'ean_13', 'ean_8', 'code_39'],
            });

            scanInterval = setInterval(async () => {
                if (!videoRef.value || videoRef.value.readyState < 2) {
                    return;
                }
                try {
                    const barcodes = await detector.detect(videoRef.value);
                    if (barcodes.length > 0 && barcodes[0].rawValue) {
                        handleCodeDetected(barcodes[0].rawValue);
                    }
                } catch {
                    // Continue scanning frame
                }
            }, 350);
        } else {
            statusText.value = 'Kamera aktif. Untuk browser tanpa BarcodeDetector native, Anda dapat mengunggah foto QR atau klik simulasi scan di bawah.';
        }
    } catch (err) {
        statusText.value = 'Izin kamera ditolak atau kamera tidak ditemukan: ' + (err.message || '');
    }
};

const handleImageUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }

    if ('BarcodeDetector' in window) {
        try {
            const bitmap = await createImageBitmap(file);
            const detector = new window.BarcodeDetector({
                formats: ['qr_code', 'code_128', 'ean_13', 'ean_8', 'code_39'],
            });
            const barcodes = await detector.detect(bitmap);
            if (barcodes.length > 0 && barcodes[0].rawValue) {
                handleCodeDetected(barcodes[0].rawValue);
                return;
            }
        } catch {
            // Fallback below
        }
    }

    // Match filename against product kode_produk as helpful fallback
    const fname = file.name.toUpperCase();
    const matched = props.produkList.find((p) => fname.includes((p.kode_produk || '').toUpperCase()));
    if (matched) {
        handleCodeDetected(matched.kode_produk);
    } else {
        statusText.value = 'QR pada gambar tidak terbaca. Pastikan gambar QR jelas atau pilih dari daftar QR produk di bawah.';
    }
};

const handleCodeDetected = (code) => {
    const clean = String(code || '').trim();
    if (!clean) {
        return;
    }
    stopCamera();
    emit('detected', clean);
    emit('close');
};

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            startCamera();
        } else {
            stopCamera();
        }
    },
);

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono"
    >
        <div
            class="w-full max-w-2xl rounded border-2 shadow-2xl overflow-hidden"
            :class="
                themeState.mode === 'dark'
                    ? 'bg-slate-900 border-slate-600 text-white'
                    : 'bg-white border-slate-400 text-slate-900'
            "
        >
            <!-- Header Solid -->
            <div
                class="px-4 py-3 flex items-center justify-between text-white font-bold"
                :style="{ backgroundColor: themeState.accentColor }"
            >
                <h3 class="text-sm uppercase tracking-wider">
                    [F5] PEMINDAI QR CODE &amp; BARCODE PRODUK
                </h3>
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-2.5 py-0.5 text-xs font-bold bg-red-700 hover:bg-red-800 text-white rounded"
                >
                    TUTUP [ESC]
                </button>
            </div>

            <div class="p-4 space-y-4 text-xs">
                <!-- Status Box Solid -->
                <div
                    class="p-2.5 rounded border-2 font-bold"
                    :class="
                        themeState.mode === 'dark'
                            ? 'bg-slate-950 border-amber-400 text-amber-300'
                            : 'bg-[#fffde7] border-[#0b3c6f] text-[#0b3c6f]'
                    "
                >
                    STATUS: {{ statusText }}
                </div>

                <!-- Kamera Live + Upload File QR -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div class="relative bg-black border-2 border-slate-600 rounded h-52 flex items-center justify-center overflow-hidden">
                        <video
                            ref="videoRef"
                            class="w-full h-full object-cover"
                            playsinline
                            muted
                        ></video>
                        <!-- Frame Bidik QR Solid -->
                        <div class="absolute inset-8 border-2 border-[#00ff66] pointer-events-none flex items-end justify-center pb-1">
                            <span class="px-2 py-0.5 bg-black text-[#00ff66] text-[10px] font-bold">
                                AREA SCAN QR / BARCODE
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block font-bold uppercase mb-1">
                                1. Unggah Foto / Screenshot QR Produk:
                            </label>
                            <input
                                type="file"
                                accept="image/*"
                                @change="handleImageUpload"
                                class="w-full p-2 rounded border-2 text-xs cursor-pointer"
                                :class="
                                    themeState.mode === 'dark'
                                        ? 'bg-slate-950 border-slate-700 text-slate-200'
                                        : 'bg-slate-100 border-slate-300 text-slate-900'
                                "
                            />
                        </div>

                        <form @submit.prevent="handleCodeDetected(manualCode)" class="space-y-1.5">
                            <label class="block font-bold uppercase">
                                2. Input / Paste Payload QR Manual:
                            </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="manualCode"
                                    type="text"
                                    placeholder="Ketik kode QR (mis. PRD-001)..."
                                    class="flex-1 px-3 py-2 rounded border-2 font-bold"
                                    :class="
                                        themeState.mode === 'dark'
                                            ? 'bg-slate-950 border-slate-700 text-white'
                                            : 'bg-[#fffde7] border-slate-400 text-slate-900'
                                    "
                                />
                                <button
                                    type="submit"
                                    class="px-3 py-2 font-bold text-white rounded"
                                    :style="{ backgroundColor: themeState.accentColor }"
                                >
                                    PROSES
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Simulasi Cepat Scan QR Produk (Untuk Uji Coba Tanpa Webcam) -->
                <div class="pt-3 border-t-2 border-slate-400">
                    <div class="font-bold uppercase mb-2">
                        Daftar Kartu QR Produk (Klik kartu untuk langsung Scan QR ke Keranjang):
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 max-h-44 overflow-y-auto p-1">
                        <button
                            v-for="p in produkList"
                            :key="p.id_produk"
                            type="button"
                            :disabled="p.stok <= 0"
                            @click="handleCodeDetected(p.kode_produk)"
                            class="p-2 rounded border-2 flex flex-col items-center text-center transition disabled:opacity-40"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-700 hover:border-amber-400 text-white'
                                    : 'bg-slate-100 border-slate-300 hover:border-[#0b3c6f] text-slate-900'
                            "
                        >
                            <img
                                :src="generateQrSvgDataUrl(p.kode_produk)"
                                :alt="p.kode_produk"
                                class="w-16 h-16 border border-slate-400 bg-white p-0.5 mb-1"
                            />
                            <span class="font-extrabold text-[11px]">{{ p.kode_produk }}</span>
                            <span class="text-[10px] truncate w-full">{{ p.nama_produk }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
