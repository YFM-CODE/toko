<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PosLayout from '@/Layouts/PosLayout.vue';
import CustomerSelectModal from '@/Components/Pos/CustomerSelectModal.vue';
import ProductLookupModal from '@/Components/Pos/ProductLookupModal.vue';
import QrScannerModal from '@/Components/Pos/QrScannerModal.vue';
import PaymentGatewayModal from '@/Components/Pos/PaymentGatewayModal.vue';
import ReceiptModal from '@/Components/Pos/ReceiptModal.vue';
import {
    useTheme,
    formatRupiah,
    getProductImage,
    createProductSvg,
    playBeepSound,
    printDirectToEpson,
    detectPaymentQrProvider,
} from '@/lib/useTheme';

const props = defineProps({
    nextKodeTransaksi: {
        type: String,
        default: 'TRX-0001',
    },
    pelangganList: {
        type: Array,
        default: () => [],
    },
    produkList: {
        type: Array,
        default: () => [],
    },
    kategoriList: {
        type: Array,
        default: () => [],
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

const { activeTokens } = useTheme();

const localPelangganList = ref([...props.pelangganList]);
const localProdukList = ref([...props.produkList]);

const defaultCustomer = computed(() => {
    return (
        localPelangganList.value.find((p) =>
            (p.nama_pelanggan || '').toLowerCase().includes('umum'),
        ) ||
        localPelangganList.value[0] ||
        null
    );
});

const selectedPelanggan = ref(defaultCustomer.value);
const barcodeInput = ref('');
const scanQty = ref(1);
const cart = ref([]);
const activeRowIndex = ref(0);
const persenDiskon = ref(0);
const poinDitukar = ref(0);
const tunai = ref(0);
const inlinePaymentQrInput = ref('');
const inlineDetectedBank = computed(() => detectPaymentQrProvider(inlinePaymentQrInput.value));

const isActiveMember = computed(() => {
    return Boolean(selectedPelanggan.value && !selectedPelanggan.value.is_walk_in);
});

const maxRedeemablePoints = computed(() => {
    if (!isActiveMember.value) {
        return 0;
    }
    const available = Number(selectedPelanggan.value?.poin || 0);
    const maxByTotal = Math.floor(Math.max(0, subtotalBelanja.value - diskonPersenNominal.value) / 100);
    return Math.min(available, maxByTotal);
});

const potonganPoinNominal = computed(() => {
    const pts = Math.min(maxRedeemablePoints.value, Math.max(0, Number(poinDitukar.value) || 0));
    return pts * 100;
});

const barcodeInputRef = ref(null);
const tunaiInputRef = ref(null);

const showCustomerModal = ref(false);
const showProductModal = ref(false);
const showQrModal = ref(false);
const qrScanPurpose = ref('PRODUCT'); // 'PRODUCT' | 'PAYMENT_CPM'
const showPaymentGatewayModal = ref(false);
const paymentGatewayInitialTab = ref('SCAN_CPM');
const paymentGatewayModalRef = ref(null);
const showReceiptModal = ref(false);
const latestReceipt = ref(null);
const autoDirectPrint = ref(
    typeof window !== 'undefined'
        ? localStorage.getItem('toko_auto_direct_print') !== 'false'
        : true,
);
const directPrintBannerMessage = ref('');

const toggleAutoDirectPrint = () => {
    autoDirectPrint.value = !autoDirectPrint.value;
    if (typeof window !== 'undefined') {
        localStorage.setItem('toko_auto_direct_print', String(autoDirectPrint.value));
    }
};

const processing = ref(false);
const banner = ref({ type: '', message: '' });

const setBanner = (type, message) => {
    banner.value = { type, message };
};

const clearBanner = () => {
    banner.value = { type: '', message: '' };
};

const activeItem = computed(() => {
    if (cart.value.length === 0) {
        return null;
    }
    return cart.value[activeRowIndex.value] || cart.value[0];
});

const previewImageSrc = computed(() => {
    return getProductImage(activeItem.value);
});

const handleImageError = (event) => {
    const item = activeItem.value;
    event.target.src = item
        ? createProductSvg(item.kode_produk, item.nama_produk)
        : createProductSvg('POS', 'SIAP SCAN');
};

const subtotalBelanja = computed(() => {
    return cart.value.reduce((acc, item) => acc + item.harga * item.jumlah, 0);
});

const diskonPersenNominal = computed(() => {
    const p = Number(persenDiskon.value) || 0;
    if (p <= 0) {
        return 0;
    }
    if (p > 100) {
        return subtotalBelanja.value;
    }
    return Math.round(subtotalBelanja.value * (p / 100));
});

const nominalDiskon = computed(() => {
    return Math.min(subtotalBelanja.value, diskonPersenNominal.value + potonganPoinNominal.value);
});

const totalAkhir = computed(() => {
    return Math.max(0, subtotalBelanja.value - nominalDiskon.value);
});

const kategoriBelanjaBadge = computed(() => {
    const total = totalAkhir.value;
    if (total >= 1000000) {
        return { label: 'Pelanggan Gold', bg: activeTokens.value.keycap, text: '#111111' };
    }
    if (total >= 500000) {
        return { label: 'Pelanggan Silver', bg: '#e2e8f0', text: '#0f172a' };
    }
    return { label: 'Pelanggan Reguler', bg: '#047857', text: '#ffffff' };
});

const kembalian = computed(() => {
    const bayar = Number(tunai.value) || 0;
    return Math.max(0, bayar - totalAkhir.value);
});

const addProductToCart = (produk, qtyToAdd = 1) => {
    const qty = Math.max(1, Number(qtyToAdd) || 1);

    const existingIndex = cart.value.findIndex((c) => c.id_produk === produk.id_produk);
    if (existingIndex !== -1) {
        cart.value[existingIndex].jumlah += qty;
        cart.value[existingIndex].subtotal_item =
            cart.value[existingIndex].jumlah * cart.value[existingIndex].harga;
        activeRowIndex.value = existingIndex;
    } else {
        cart.value.push({
            id_produk: produk.id_produk,
            kode_produk: produk.kode_produk,
            nama_produk: produk.nama_produk,
            nama_kategori: produk.kategori?.nama_kategori || '-',
            harga: Number(produk.harga),
            stok: Number(produk.stok),
            gambar_url: produk.gambar_url || null,
            jumlah: qty,
            subtotal_item: Number(produk.harga) * qty,
        });
        activeRowIndex.value = cart.value.length - 1;
    }

    playBeepSound();
    setBanner('success', `Item "${produk.nama_produk}" (${qty} pcs) berhasil ditambahkan ke keranjang.`);

    if (tunai.value < totalAkhir.value) {
        tunai.value = totalAkhir.value;
    }
};

/**
 * Smart Scan: Mendeteksi otomatis apakah input dari scanner/reader adalah Kartu RFID Member atau Barcode/QR Barang!
 */
const findAndAddByCode = (rawCode) => {
    const q = String(rawCode || '').trim().toLowerCase();
    if (!q) {
        return;
    }

    // 1. Cek apakah kode yang di-tap adalah UID Kartu RFID Member Pelanggan atau QR Member (MBR-{id})
    const matchedMember = localPelangganList.value.find(
        (pel) =>
            (pel.rfid_uid && pel.rfid_uid.toLowerCase() === q) ||
            `mbr-${pel.id_pelanggan}` === q,
    );

    if (matchedMember) {
        handleCustomerSelected(matchedMember);
        barcodeInput.value = '';
        playBeepSound();
        nextTick(() => barcodeInputRef.value?.focus());
        return;
    }

    // 2. Cek apakah kode adalah Kode PLU / Barcode / Nama Produk
    const found =
        localProdukList.value.find((p) => (p.kode_produk || '').toLowerCase() === q) ||
        localProdukList.value.find((p) => (p.nama_produk || '').toLowerCase().includes(q));

    if (!found) {
        // 3. Cek apakah kode yang di-scan kasir adalah QR Code Pembayaran (contoh: QR BRI / BRImo, BCA, Mandiri, DANA, GoPay)
        const detectedBank = detectPaymentQrProvider(rawCode);
        if (detectedBank) {
            barcodeInput.value = '';
            if (cart.value.length === 0) {
                setBanner(
                    'info',
                    `💳 Terdeteksi ${detectedBank}, tetapi keranjang belanja masih kosong. Scan barang terlebih dahulu.`,
                );
                return;
            }
            playBeepSound();
            setBanner('success', `💳 Terdeteksi ${detectedBank}! Memproses pembayaran & mencetak struk...`);
            tunai.value = totalAkhir.value;
            prosesTransaksi({
                metode_pembayaran: 'SCAN_CPM',
                payment_channel: detectedBank,
                payment_token: String(rawCode).trim(),
                nominal_bayar: totalAkhir.value,
            });
            return;
        }

        setBanner('error', `Kode PLU / QR / RFID "${rawCode}" tidak ditemukan! Tekan [F1] Cari Barang atau [F3] Member.`);
        return;
    }

    addProductToCart(found, scanQty.value);
    barcodeInput.value = '';
    scanQty.value = 1;
    nextTick(() => barcodeInputRef.value?.focus());
};

const handleScanSubmit = () => {
    findAndAddByCode(barcodeInput.value);
};

const handleQrDetected = (code) => {
    if (qrScanPurpose.value === 'PAYMENT_CPM') {
        qrScanPurpose.value = 'PRODUCT';
        paymentGatewayModalRef.value?.setScannedToken(code);
        return;
    }
    findAndAddByCode(code);
};

const openPaymentGateway = (tab = 'SCAN_CPM') => {
    clearBanner();
    if (cart.value.length === 0) {
        setBanner('error', 'Keranjang belanja masih kosong. Scan barang terlebih dahulu.');
        return;
    }
    paymentGatewayInitialTab.value = tab;
    showPaymentGatewayModal.value = true;
};

const openCameraForPaymentCpm = () => {
    qrScanPurpose.value = 'PAYMENT_CPM';
    showQrModal.value = true;
};

const handlePaymentGatewayConfirmed = async (paymentPayload) => {
    showPaymentGatewayModal.value = false;
    tunai.value = totalAkhir.value;
    await prosesTransaksi(paymentPayload);
};

const handleInlinePaymentQrSubmit = async () => {
    const raw = String(inlinePaymentQrInput.value || '').trim();
    if (!raw) {
        return;
    }
    if (cart.value.length === 0) {
        setBanner('error', 'Keranjang belanja masih kosong.');
        return;
    }
    const detectedBank = detectPaymentQrProvider(raw) || 'QR PAYMENT GATEWAY';
    inlinePaymentQrInput.value = '';
    tunai.value = totalAkhir.value;
    playBeepSound();
    setBanner('success', `💳 Terdeteksi ${detectedBank}! Memproses pembayaran & mencetak struk...`);
    await prosesTransaksi({
        metode_pembayaran: 'SCAN_CPM',
        payment_channel: detectedBank,
        payment_token: raw,
        nominal_bayar: totalAkhir.value,
    });
};

const updateItemQty = (index, newQty) => {
    const val = Math.max(1, Number(newQty) || 1);
    cart.value[index].jumlah = val;
    cart.value[index].subtotal_item = val * cart.value[index].harga;
    setBanner('success', `Kuantitas "${cart.value[index].nama_produk}" diperbarui menjadi ${val} pcs.`);
};

const removeCartItem = (index) => {
    if (index < 0 || index >= cart.value.length) {
        return;
    }
    const removedName = cart.value[index].nama_produk;
    cart.value.splice(index, 1);
    if (activeRowIndex.value >= cart.value.length) {
        activeRowIndex.value = Math.max(0, cart.value.length - 1);
    }
    setBanner('info', `Item "${removedName}" dihapus (void) dari daftar belanja.`);
};

const setQuickCash = (amount) => {
    tunai.value = amount;
};

const handleCustomerSelected = (pelanggan) => {
    selectedPelanggan.value = pelanggan;
    poinDitukar.value = 0;

    if (pelanggan && !pelanggan.is_walk_in) {
        const autoDisc = Number(pelanggan.diskon_tier_persen ?? 2);
        persenDiskon.value = autoDisc;
        setBanner(
            'success',
            `★ Member Aktif: ${pelanggan.nama_pelanggan} [Tier ${pelanggan.tier || 'Reguler'} -> Diskon Otomatis ${autoDisc}% • Poin: ${pelanggan.poin || 0} pts • Saldo: ${formatRupiah(pelanggan.saldo || 0)}]`,
        );
    } else {
        persenDiskon.value = 0;
        setBanner('info', 'Pembeli diatur ke Pelanggan Umum (Non-Member).');
    }
};

const handleCustomerCreated = (newCustomer) => {
    localPelangganList.value.push(newCustomer);
    handleCustomerSelected(newCustomer);
};

const bayarDenganSaldoMember = async () => {
    if (!isActiveMember.value) {
        setBanner('error', 'Pilih atau Tap Kartu RFID Member terlebih dahulu.');
        return;
    }
    const saldoMember = Number(selectedPelanggan.value?.saldo || 0);
    if (saldoMember < totalAkhir.value) {
        setBanner(
            'error',
            `Saldo Kartu RFID Member (${formatRupiah(saldoMember)}) tidak cukup untuk tagihan (${formatRupiah(totalAkhir.value)}).`,
        );
        return;
    }
    tunai.value = totalAkhir.value;
    await prosesTransaksi({
        metode_pembayaran: 'SALDO_MEMBER',
        payment_channel: 'SALDO KARTU MEMBER RFID',
        payment_token: selectedPelanggan.value?.rfid_uid || `MBR-${selectedPelanggan.value?.id_pelanggan}`,
        nominal_bayar: totalAkhir.value,
    });
};

const prosesTransaksi = async (paymentData = null) => {
    clearBanner();

    if (cart.value.length === 0) {
        setBanner('error', 'Keranjang belanja masih kosong.');
        return;
    }

    const metodePembayaran = paymentData?.metode_pembayaran || 'TUNAI';
    const nominalBayar =
        metodePembayaran === 'TUNAI'
            ? Number(tunai.value) || 0
            : Number(paymentData?.nominal_bayar) || totalAkhir.value;

    if (metodePembayaran === 'TUNAI' && nominalBayar < totalAkhir.value) {
        setBanner(
            'error',
            `Nominal tunai (${formatRupiah(nominalBayar)}) kurang dari total belanja (${formatRupiah(totalAkhir.value)}).`,
        );
        return;
    }

    processing.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/api/transaksi', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                id_pelanggan: selectedPelanggan.value?.id_pelanggan || null,
                persen_diskon: Number(persenDiskon.value) || 0,
                poin_ditukar: Number(poinDitukar.value) || 0,
                metode_pembayaran: metodePembayaran,
                payment_channel: paymentData?.payment_channel || 'CASH',
                payment_token: paymentData?.payment_token || null,
                nominal_bayar: nominalBayar,
                items: cart.value.map((item) => ({
                    id_produk: item.id_produk,
                    jumlah: item.jumlah,
                })),
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            const firstErr =
                payload?.errors && Object.values(payload.errors)[0]
                    ? Object.values(payload.errors)[0][0]
                    : payload?.message || 'Transaksi dibatalkan (ROLLBACK). Periksa stok atau nominal.';
            setBanner('error', firstErr);
            return;
        }

        cart.value.forEach((cItem) => {
            const prod = localProdukList.value.find((p) => p.id_produk === cItem.id_produk);
            if (prod) {
                prod.stok = Math.max(0, prod.stok - cItem.jumlah);
            }
        });

        if (payload.data?.pelanggan) {
            const idx = localPelangganList.value.findIndex(
                (p) => p.id_pelanggan === payload.data.pelanggan.id_pelanggan,
            );
            if (idx !== -1) {
                localPelangganList.value[idx] = payload.data.pelanggan;
            }
            selectedPelanggan.value = payload.data.pelanggan;
        }

        latestReceipt.value = payload.data;
        directPrintBannerMessage.value = '';
        showReceiptModal.value = true;

        if (autoDirectPrint.value && payload.data?.id_transaksi) {
            const printRes = await printDirectToEpson(payload.data.id_transaksi, {
                tunai: Number(tunai.value),
                kembalian: kembalian.value,
                paperSize: '80mm',
            });
            directPrintBannerMessage.value = printRes.message;
        }
    } catch (err) {
        setBanner('error', err.message || 'Gagal menghubungi server.');
    } finally {
        processing.value = false;
    }
};

const resetTransaction = () => {
    showReceiptModal.value = false;
    latestReceipt.value = null;
    cart.value = [];
    activeRowIndex.value = 0;
    persenDiskon.value = 0;
    poinDitukar.value = 0;
    tunai.value = 0;
    selectedPelanggan.value = defaultCustomer.value;
    setBanner('info', 'Siap untuk transaksi baru.');
    router.reload({ only: ['nextKodeTransaksi', 'produkList', 'pelangganList'] });
    nextTick(() => barcodeInputRef.value?.focus());
};

const handleGlobalKeydown = (e) => {
    if (e.key === 'F1') {
        e.preventDefault();
        showProductModal.value = true;
    } else if (e.key === 'F2') {
        e.preventDefault();
        if (cart.value.length > 0) {
            const el = document.getElementById(`cart-qty-${activeRowIndex.value}`);
            if (el) {
                el.focus();
                el.select();
            }
        }
    } else if (e.key === 'F3') {
        e.preventDefault();
        showCustomerModal.value = true;
    } else if (e.key === 'F4') {
        e.preventDefault();
        if (cart.value.length > 0) {
            removeCartItem(activeRowIndex.value);
        }
    } else if (e.key === 'F5') {
        e.preventDefault();
        qrScanPurpose.value = 'PRODUCT';
        showQrModal.value = true;
    } else if (e.key === 'F8') {
        e.preventDefault();
        tunaiInputRef.value?.focus();
        tunaiInputRef.value?.select();
    } else if (e.key === 'F9') {
        e.preventDefault();
        openPaymentGateway('SCAN_CPM');
    } else if (e.key === 'F10') {
        e.preventDefault();
        openPaymentGateway('QRIS_MPM');
    } else if (
        e.key === 'ArrowDown' &&
        !showProductModal.value &&
        !showCustomerModal.value &&
        !showQrModal.value
    ) {
        if (cart.value.length > 0 && document.activeElement !== tunaiInputRef.value) {
            e.preventDefault();
            activeRowIndex.value = (activeRowIndex.value + 1) % cart.value.length;
        }
    } else if (
        e.key === 'ArrowUp' &&
        !showProductModal.value &&
        !showCustomerModal.value &&
        !showQrModal.value
    ) {
        if (cart.value.length > 0 && document.activeElement !== tunaiInputRef.value) {
            e.preventDefault();
            activeRowIndex.value = (activeRowIndex.value - 1 + cart.value.length) % cart.value.length;
        }
    } else if (e.key === 'Escape') {
        showProductModal.value = false;
        showCustomerModal.value = false;
        showQrModal.value = false;
        showPaymentGatewayModal.value = false;
        clearBanner();
        nextTick(() => barcodeInputRef.value?.focus());
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    barcodeInputRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <Head title="Kasir POS - Sistem Informasi Penjualan Toko" />

    <PosLayout :kode-transaksi="nextKodeTransaksi" :cart-count="cart.length">
        <!-- Banner Notifikasi (Tenang & Jelas) -->
        <div
            v-if="banner.message"
            class="mx-2 mt-1.5 px-3 py-1.5 rounded-md border text-xs font-medium flex items-center justify-between shrink-0 no-print"
            :class="{
                'bg-emerald-50 border-emerald-300 text-emerald-900': banner.type === 'success',
                'bg-red-50 border-red-300 text-red-800': banner.type === 'error',
                'bg-slate-50 border-slate-300 text-slate-800': banner.type === 'info',
            }"
        >
            <span>{{ banner.message }}</span>
            <button
                type="button"
                @click="clearBanner"
                class="ml-4 px-2 py-0.5 rounded bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-[11px] font-medium"
            >
                Tutup [ESC]
            </button>
        </div>

        <!-- Scan Bar -->
        <section
            class="px-4 py-2 bg-white dark:bg-slate-900 border-b border-slate-300 dark:border-slate-700 grid grid-cols-1 lg:grid-cols-12 gap-3 items-center text-xs shrink-0 no-print"
        >
            <!-- Input Scan Barcode / PLU / Tap Kartu RFID Member -->
            <form @submit.prevent="handleScanSubmit" class="lg:col-span-8 flex flex-wrap sm:flex-nowrap items-center gap-2">
                <label for="barcodeInput" class="font-semibold text-slate-700 dark:text-slate-200 uppercase shrink-0">
                    Barcode / RFID:
                </label>
                <input
                    id="barcodeInput"
                    ref="barcodeInputRef"
                    v-model="barcodeInput"
                    type="text"
                    placeholder="Scan Barcode (PRD-001) atau Tap Kartu RFID Member..."
                    class="flex-1 px-3 py-1.5 text-sm font-mono font-semibold bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 border border-slate-300 dark:border-slate-700 rounded-md focus:border-[#1E3A5F] focus:ring-2 focus:ring-[#1E3A5F]/20 outline-none"
                />
                <div class="flex items-center gap-1.5">
                    <label for="qtyInput" class="font-semibold text-slate-700 dark:text-slate-200">Qty:</label>
                    <input
                        id="qtyInput"
                        v-model.number="scanQty"
                        type="number"
                        min="1"
                        class="w-14 px-2 py-1.5 text-sm font-mono font-semibold bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 border border-slate-300 dark:border-slate-700 rounded-md text-center tabular-nums focus:border-[#1E3A5F] focus:ring-2 focus:ring-[#1E3A5F]/20 outline-none"
                    />
                </div>
                <button
                    type="submit"
                    class="px-3.5 py-1.5 font-medium text-white bg-[#1E3A5F] hover:bg-[#162C49] rounded-md shrink-0 transition"
                >
                    [Enter]
                </button>
                <button
                    type="button"
                    @click="showQrModal = true"
                    class="px-3 py-1.5 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md shrink-0 transition"
                    title="Scan QR Code Produk dengan Kamera"
                >
                    [F5] Scan QR
                </button>
                <button
                    type="button"
                    @click="showProductModal = true"
                    class="px-3 py-1.5 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md shrink-0 transition"
                >
                    [F1] Cari
                </button>
            </form>

            <!-- Info Pelanggan / Member RFID (Netral) -->
            <div class="lg:col-span-4 flex items-center justify-end gap-2">
                <div class="flex-1 px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex items-center justify-between truncate">
                    <div class="truncate text-slate-700 dark:text-slate-200">
                        <span class="text-slate-500 mr-1">Pembeli:</span>
                        <strong class="font-semibold text-slate-900 dark:text-white">
                            {{ selectedPelanggan ? selectedPelanggan.nama_pelanggan : 'Pelanggan Umum' }}
                        </strong>
                        <span
                            v-if="isActiveMember"
                            class="ml-1.5 text-[11px] font-mono font-semibold text-[#1E3A5F] dark:text-sky-300"
                        >
                            ({{ selectedPelanggan.tier || 'Reguler' }} {{ selectedPelanggan.diskon_tier_persen ?? 2 }}% • {{ selectedPelanggan.poin || 0 }} pts)
                        </span>
                    </div>
                    <button
                        v-if="selectedPelanggan && selectedPelanggan.id_pelanggan !== defaultCustomer?.id_pelanggan"
                        type="button"
                        @click="handleCustomerSelected(defaultCustomer)"
                        class="ml-2 px-1.5 py-0.5 text-[10px] font-medium bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 rounded"
                        title="Kembalikan ke Pelanggan Umum"
                    >
                        Umum
                    </button>
                </div>
                <button
                    type="button"
                    @click="showCustomerModal = true"
                    class="px-3 py-1.5 font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md shrink-0 transition"
                >
                    [F3] Member / RFID
                </button>
            </div>
        </section>

        <!-- Main Workspace 2-Kolom -->
        <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-2 p-2 overflow-hidden no-print">
            <!-- Panel Kiri: Tabel Keranjang Belanja -->
            <div class="flex flex-col min-h-0 overflow-hidden bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md">
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="uppercase text-[11px] font-semibold text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800 sticky top-0 z-10 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="p-2.5 w-12 text-center">No</th>
                                <th class="p-2.5 w-28">Kode PLU</th>
                                <th class="p-2.5">Nama Barang</th>
                                <th class="p-2.5 w-24 text-center">Qty</th>
                                <th class="p-2.5 w-28 text-right">Harga</th>
                                <th class="p-2.5 w-32 text-right">Subtotal</th>
                                <th class="p-2.5 w-16 text-center">Void</th>
                            </tr>
                        </thead>
                        <tbody class="tabular-nums font-mono text-xs divide-y divide-slate-200 dark:divide-slate-800">
                            <tr
                                v-for="(item, idx) in cart"
                                :key="item.id_produk"
                                @click="activeRowIndex = idx"
                                class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/70 transition"
                                :class="activeRowIndex === idx ? 'bg-slate-100 dark:bg-slate-800 font-semibold' : ''"
                            >
                                <td class="p-2 text-center text-slate-500">
                                    <span v-if="activeRowIndex === idx" class="text-[#1E3A5F] font-bold mr-0.5">&rsaquo;</span>{{ idx + 1 }}
                                </td>
                                <td class="p-2 font-semibold text-slate-800 dark:text-slate-200">{{ item.kode_produk }}</td>
                                <td class="p-2 font-sans text-slate-900 dark:text-white">{{ item.nama_produk }}</td>
                                <td class="p-2 text-center">
                                    <input
                                        :id="`cart-qty-${idx}`"
                                        type="number"
                                        min="1"
                                        :value="item.jumlah"
                                        @input="(e) => updateItemQty(idx, e.target.value)"
                                        class="w-16 px-1.5 py-0.5 text-center bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded font-semibold tabular-nums focus:border-[#1E3A5F] outline-none"
                                    />
                                </td>
                                <td class="p-2 text-right text-slate-700 dark:text-slate-300">{{ formatRupiah(item.harga) }}</td>
                                <td class="p-2 text-right font-bold text-slate-900 dark:text-white">
                                    {{ formatRupiah(item.subtotal_item) }}
                                </td>
                                <td class="p-2 text-center">
                                    <button
                                        type="button"
                                        @click.stop="removeCartItem(idx)"
                                        class="px-2 py-0.5 text-[11px] font-medium text-red-700 bg-white border border-red-300 hover:bg-red-50 rounded"
                                    >
                                        F4
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="cart.length === 0">
                                <td colspan="7" class="p-12 text-center font-sans text-slate-500">
                                    Keranjang kosong. Scan kode PLU (<code class="font-mono">PRD-001</code>), tap Kartu RFID Member, tekan <strong>[F1] Cari</strong>, atau <strong>[F5] Scan QR</strong>.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel Kanan: Fit 1 Layar 100vh Tanpa Scrollbar Vertikal -->
            <aside class="flex flex-col gap-2 min-h-0 overflow-hidden">
                <!-- 1. Product Image Preview Card (Ringkas & Harga Hitam Pekat) -->
                <div class="p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md flex items-center gap-2.5 shrink-0">
                    <div class="w-[70px] h-[70px] border border-slate-200 dark:border-slate-700 rounded flex items-center justify-center shrink-0 overflow-hidden bg-slate-50">
                        <img
                            :src="previewImageSrc"
                            alt="Preview Produk"
                            @error="handleImageError"
                            class="max-w-full max-h-full object-contain"
                        />
                    </div>

                    <div v-if="activeItem" class="min-w-0 flex-1 text-xs space-y-0.5">
                        <div class="font-bold text-slate-900 dark:text-white truncate">
                            {{ activeItem.nama_produk }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono">
                            PLU: {{ activeItem.kode_produk }} • Stok: {{ activeItem.stok }}
                        </div>
                        <div class="font-bold font-mono text-sm text-slate-900 dark:text-white tabular-nums pt-0.5">
                            {{ formatRupiah(activeItem.harga) }}
                        </div>
                    </div>
                    <div v-else class="text-xs space-y-0.5">
                        <div class="font-semibold text-slate-700 dark:text-slate-200">Belum Ada Item</div>
                        <div class="text-[11px] text-slate-500 font-mono">PLU: - • Stok: -</div>
                        <div class="font-bold font-mono text-sm text-slate-900 dark:text-white">Rp 0</div>
                    </div>
                </div>

                <!-- 2. Summary & Payment Panel (Compact, 100vh Fit) -->
                <div class="flex-1 min-h-0 p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md flex flex-col justify-between text-xs">
                    <div class="space-y-1.5">
                        <!-- Kotak GRAND TOTAL Tenang -->
                        <div class="px-3 py-2 bg-[#1E3A5F] text-white rounded-md text-right">
                            <div class="flex items-center justify-between text-[10px] text-slate-200 font-semibold uppercase tracking-wide">
                                <span>Grand Total</span>
                                <span class="font-mono text-slate-200">
                                    {{ kategoriBelanjaBadge.label }}
                                </span>
                            </div>
                            <div class="text-2xl font-bold font-mono tabular-nums tracking-tight mt-0.5 text-white">
                                {{ formatRupiah(totalAkhir) }}
                            </div>
                        </div>

                        <!-- Rincian Subtotal & Diskon -->
                        <div class="space-y-1 tabular-nums font-mono">
                            <div class="flex justify-between text-slate-700 dark:text-slate-300">
                                <span class="text-slate-500">Subtotal ({{ cart.length }} baris):</span>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ formatRupiah(subtotalBelanja) }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Diskon (%):</span>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model.number="persenDiskon"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-14 px-2 py-0.5 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded text-right font-semibold focus:border-[#1E3A5F] outline-none"
                                    />
                                    <span class="text-red-700 dark:text-red-400 font-semibold">
                                        - {{ formatRupiah(nominalDiskon) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Penukaran Poin & Saldo Member (Jika Member Aktif) -->
                            <div
                                v-if="isActiveMember"
                                class="p-1.5 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 space-y-1 text-[11px]"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">Poin ({{ selectedPelanggan.poin || 0 }}):</span>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model.number="poinDitukar"
                                            type="number"
                                            min="0"
                                            :max="maxRedeemablePoints"
                                            placeholder="0"
                                            class="w-14 px-1.5 py-0.5 bg-white border border-slate-300 rounded text-right font-semibold"
                                        />
                                        <button
                                            type="button"
                                            @click="poinDitukar = maxRedeemablePoints"
                                            class="px-1.5 py-0.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 rounded text-[10px] font-semibold"
                                        >
                                            Max
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">Saldo: <strong>{{ formatRupiah(selectedPelanggan.saldo || 0) }}</strong></span>
                                    <button
                                        type="button"
                                        :disabled="processing || cart.length === 0 || (selectedPelanggan.saldo || 0) < totalAkhir"
                                        @click="bayarDenganSaldoMember"
                                        class="px-2 py-0.5 rounded bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-semibold text-[10px] disabled:opacity-40"
                                    >
                                        Pakai Saldo
                                    </button>
                                </div>
                            </div>

                            <!-- Input Tunai [F8] -->
                            <div class="pt-1.5 border-t border-slate-200 dark:border-slate-700">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="font-sans font-semibold text-slate-700 dark:text-slate-200">Tunai [F8]:</label>
                                    <input
                                        ref="tunaiInputRef"
                                        v-model.number="tunai"
                                        type="number"
                                        min="0"
                                        step="500"
                                        class="w-36 px-2.5 py-1 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-md text-right font-bold text-sm text-slate-900 dark:text-white focus:border-[#1E3A5F] focus:ring-2 focus:ring-[#1E3A5F]/20 outline-none"
                                    />
                                </div>

                                <!-- Tombol Nominal Cepat (Netral) -->
                                <div class="grid grid-cols-3 gap-1.5 font-sans">
                                    <button
                                        type="button"
                                        @click="setQuickCash(totalAkhir)"
                                        class="py-1 rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 font-medium text-[11px]"
                                    >
                                        Uang Pas
                                    </button>
                                    <button
                                        type="button"
                                        @click="setQuickCash(50000)"
                                        class="py-1 rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 font-medium text-[11px]"
                                    >
                                        50.000
                                    </button>
                                    <button
                                        type="button"
                                        @click="setQuickCash(100000)"
                                        class="py-1 rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 font-medium text-[11px]"
                                    >
                                        100.000
                                    </button>
                                </div>
                            </div>

                            <!-- Kembalian -->
                            <div class="flex justify-between items-center pt-1.5 border-t border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-900 dark:text-white">
                                <span class="font-sans">Kembalian:</span>
                                <span>{{ formatRupiah(kembalian) }}</span>
                            </div>

                            <!-- Scan Langsung QR Pembayaran -->
                            <form
                                @submit.prevent="handleInlinePaymentQrSubmit"
                                class="pt-1.5 border-t border-slate-200 dark:border-slate-700 space-y-1 font-sans"
                            >
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-medium text-slate-600 dark:text-slate-300">Scan QR Bayar (BRI / Bank / E-Wallet):</span>
                                    <span
                                        v-if="inlineDetectedBank"
                                        class="font-mono font-semibold text-[#1E3A5F] text-[10px]"
                                    >
                                        {{ inlineDetectedBank }}
                                    </span>
                                </div>
                                <div class="flex gap-1.5">
                                    <input
                                        v-model="inlinePaymentQrInput"
                                        type="text"
                                        placeholder="Scan QR pembayaran lalu Enter..."
                                        class="flex-1 px-2 py-1 bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-md text-xs font-mono focus:border-[#1E3A5F] outline-none"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="processing || cart.length === 0 || !inlinePaymentQrInput.trim()"
                                        class="px-2.5 py-1 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-md font-medium text-[11px] disabled:opacity-40"
                                    >
                                        Bayar QR
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Area Tombol Bayar Utama (Navy #1E3A5F) & Utilitas Non-Tunai -->
                    <div class="pt-1.5 space-y-1.5 border-t border-slate-200 dark:border-slate-700">
                        <label class="flex items-center justify-between px-2 py-1 rounded bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer select-none text-[11px] font-medium text-slate-700 dark:text-slate-200">
                            <span>Auto-Print Langsung (Tanpa Dialog)</span>
                            <input
                                type="checkbox"
                                :checked="autoDirectPrint"
                                @change="toggleAutoDirectPrint"
                                class="w-3.5 h-3.5 accent-[#1E3A5F]"
                            />
                        </label>

                        <button
                            type="button"
                            :disabled="processing || cart.length === 0"
                            @click="prosesTransaksi()"
                            class="w-full py-2 text-xs font-semibold uppercase tracking-wider text-white bg-[#1E3A5F] hover:bg-[#162C49] rounded-md transition disabled:opacity-40"
                        >
                            {{ processing ? 'Memproses Transaksi...' : 'Bayar Tunai & Cetak Struk' }}
                        </button>

                        <div class="grid grid-cols-2 gap-1.5">
                            <button
                                type="button"
                                :disabled="processing || cart.length === 0"
                                @click="openPaymentGateway('SCAN_CPM')"
                                class="py-1.5 px-2 text-[11px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition disabled:opacity-40"
                                title="Kasir Scan Barcode/QR Kode Bayar di HP Pembeli"
                            >
                                [F9] Scan QR Bayar
                            </button>
                            <button
                                type="button"
                                :disabled="processing || cart.length === 0"
                                @click="openPaymentGateway('QRIS_MPM')"
                                class="py-1.5 px-2 text-[11px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md transition disabled:opacity-40"
                                title="Tampilkan QRIS Toko untuk di-scan Pembeli"
                            >
                                [F10] QRIS Toko
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Modals -->
        <CustomerSelectModal
            :show="showCustomerModal"
            :pelanggan-list="localPelangganList"
            @close="showCustomerModal = false"
            @selected="handleCustomerSelected"
            @created="handleCustomerCreated"
        />

        <ProductLookupModal
            :show="showProductModal"
            :produk-list="localProdukList"
            :kategori-list="kategoriList"
            @close="showProductModal = false"
            @select="(prod) => addProductToCart(prod, scanQty)"
        />

        <QrScannerModal
            :show="showQrModal"
            :produk-list="localProdukList"
            @close="showQrModal = false"
            @detected="handleQrDetected"
        />

        <PaymentGatewayModal
            ref="paymentGatewayModalRef"
            :show="showPaymentGatewayModal"
            :initial-tab="paymentGatewayInitialTab"
            :kode-transaksi="nextKodeTransaksi"
            :total-akhir="totalAkhir"
            :processing="processing"
            :gateway-config="gatewayConfig"
            @close="showPaymentGatewayModal = false"
            @confirm-payment="handlePaymentGatewayConfirmed"
            @open-camera-scan="openCameraForPaymentCpm"
        />

        <ReceiptModal
            :show="showReceiptModal"
            :receipt="latestReceipt"
            :tunai="Number(tunai)"
            :kembalian="kembalian"
            :initial-print-message="directPrintBannerMessage"
            @new-transaction="resetTransaction"
        />
    </PosLayout>
</template>
