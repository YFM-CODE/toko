<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ReceiptModal from '@/Components/Pos/ReceiptModal.vue';
import {
    useTheme,
    formatRupiah,
    getProductImage,
    handleProductImageError,
    generateQrSvgDataUrl,
    playBeepSound,
} from '@/lib/useTheme';

const props = defineProps({
    pelanggan: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    riwayatTransaksi: {
        type: Array,
        default: () => [],
    },
    produkKatalog: {
        type: Array,
        default: () => [],
    },
    kategoriList: {
        type: Array,
        default: () => [],
    },
    gatewayConfig: {
        type: Object,
        default: () => ({}),
    },
});

const { activeTokens } = useTheme();

const activeTab = ref('belanja');
const searchProduk = ref('');
const selectedKategoriId = ref('');
const cart = ref([]);
const usePoints = ref(false);
const metodePembayaran = ref('QRIS_MPM');
const paymentSubMode = ref('MANUAL_QRIS'); // 'MANUAL_QRIS' | 'DUITKU_AUTO'
const isProcessing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const selectedReceipt = ref(null);
const showReceiptModal = ref(false);

// Modal Tagihan Belum Dibayar (status = 'pending') — Struk dikunci sampai lunas di Duitku / diverifikasi Kasir!
const pendingInvoice = ref(null);
const showPendingPaymentModal = ref(false);
const paymentRefInput = ref('');
const proofFile = ref(null);
const proofPreviewUrl = ref(null);
const isConfirmingPayment = ref(false);
const paymentModalError = ref('');

const selectedDuitkuBank = ref('BR');
const duitkuInvoiceData = ref(null);
const loadingDuitkuInvoice = ref(false);
const copiedDuitkuLabel = ref('');
const checkingDuitkuStatus = ref(false);
const duitkuCheckMessage = ref('');
const showManualProofUpload = ref(false);

const duitkuBanks = [
    { id: 'BR', short: 'BRI VA', label: 'BRI Virtual Account (BRIVA)' },
    { id: 'BC', short: 'BCA VA', label: 'BCA Virtual Account' },
    { id: 'M2', short: 'Mandiri VA', label: 'Mandiri Virtual Account' },
    { id: 'I1', short: 'BNI VA', label: 'BNI Virtual Account' },
    { id: 'B1', short: 'CIMB VA', label: 'CIMB Niaga Virtual Account' },
    { id: 'SP', short: 'QRIS', label: 'QRIS Duitku (All Bank & E-Wallet)' },
    { id: 'DA', short: 'DANA', label: 'DANA E-Wallet' },
];

const copyDuitkuCode = async (text, label = 'Kode') => {
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(String(text));
        copiedDuitkuLabel.value = `${label} berhasil disalin!`;
        playBeepSound();
        setTimeout(() => {
            copiedDuitkuLabel.value = '';
        }, 2500);
    } catch {
        copiedDuitkuLabel.value = `Salin manual: ${text}`;
    }
};

const fetchMemberDuitkuInquiry = async (trx = null) => {
    const target = trx || pendingInvoice.value;
    if (!target) {
        return;
    }
    loadingDuitkuInvoice.value = true;
    duitkuCheckMessage.value = '';
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/payment/duitku-qris', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                kode_transaksi: target.kode_transaksi,
                amount: target.total_belanja,
                payment_method: selectedDuitkuBank.value,
            }),
        });
        const payload = await res.json();
        if (payload?.data) {
            duitkuInvoiceData.value = payload.data;
        }
    } catch {
        // Fallback if offline
    } finally {
        loadingDuitkuInvoice.value = false;
    }
};

const switchMemberDuitkuBank = (bankId) => {
    selectedDuitkuBank.value = bankId;
    fetchMemberDuitkuInquiry();
};

const checkMemberDuitkuStatus = async (silent = false, targetTrx = null) => {
    const trxToCheck = targetTrx || pendingInvoice.value;
    if (!trxToCheck || checkingDuitkuStatus.value) {
        return;
    }

    const isLocalManualCheck = modalPayTab.value === 'MANUAL_QRIS' || Boolean(targetTrx);
    if (!isLocalManualCheck && duitkuInvoiceData.value?.gateway_error) {
        return;
    }

    checkingDuitkuStatus.value = true;
    if (!silent) {
        duitkuCheckMessage.value = 'Mengecek status pembayaran...';
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
                id_transaksi: trxToCheck.id_transaksi,
                merchant_order_id: duitkuInvoiceData.value?.reference_id || trxToCheck.kode_transaksi,
                local_only: isLocalManualCheck,
            }),
        });
        const payload = await res.json();
        if (payload?.is_paid && payload?.data) {
            stopMemberAutoCheck();
            playBeepSound();
            showPendingPaymentModal.value = false;
            pendingInvoice.value = null;
            selectedReceipt.value = payload.data;
            showReceiptModal.value = true;
            successMessage.value = payload.message || 'Pembayaran terverifikasi LUNAS! Struk resmi siap dicetak.';
            router.reload({ only: ['pelanggan', 'stats', 'riwayatTransaksi', 'produkKatalog'] });
        } else if (!silent) {
            duitkuCheckMessage.value = `Status: ${payload?.message || 'BELUM LUNAS (PENDING)'} — Sistem tetap mengecek otomatis setiap 3 detik.`;
        }
    } catch {
        if (!silent) {
            duitkuCheckMessage.value = 'Gagal memeriksa status pembayaran.';
        }
    } finally {
        checkingDuitkuStatus.value = false;
    }
};

let memberAutoCheckTimer = null;

const stopMemberAutoCheck = () => {
    if (memberAutoCheckTimer) {
        clearInterval(memberAutoCheckTimer);
        memberAutoCheckTimer = null;
    }
};

const startMemberAutoCheck = () => {
    stopMemberAutoCheck();
    memberAutoCheckTimer = setInterval(() => {
        if (showPendingPaymentModal.value && pendingInvoice.value?.status === 'pending') {
            checkMemberDuitkuStatus(true);
            return;
        }
        // Jika modal ditutup tetapi ada pesanan 'pending' (misal QRIS GoPay yang menunggu verifikasi Kasir),
        // cek otomatis ke database agar begitu Kasir memverifikasi, Struk langsung terbuka otomatis di layar Member!
        const firstPending = (props.riwayatTransaksi || []).find((t) => t.status === 'pending');
        if (firstPending && !showReceiptModal.value) {
            checkMemberDuitkuStatus(true, firstPending);
        }
    }, 3500);
};

watch(
    [showPendingPaymentModal, () => props.riwayatTransaksi],
    () => {
        const hasPendingInList = (props.riwayatTransaksi || []).some((t) => t.status === 'pending');
        if (showPendingPaymentModal.value || hasPendingInList) {
            startMemberAutoCheck();
        } else {
            stopMemberAutoCheck();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopMemberAutoCheck();
});

const memberScanCode = computed(() => {
    return props.pelanggan?.rfid_uid || `MBR-${props.pelanggan?.id_pelanggan}`;
});

const tierBadgeStyle = computed(() => {
    const t = String(props.stats.tier || props.pelanggan?.tier || 'Reguler').toLowerCase();
    if (t === 'gold' || t === 'vip') {
        return { bg: '#F8FAFC', text: '#0F172A', label: 'GOLD MEMBER (DISKON 10%)' };
    }
    if (t === 'silver') {
        return { bg: '#F8FAFC', text: '#0F172A', label: 'SILVER MEMBER (DISKON 5%)' };
    }
    return { bg: '#F8FAFC', text: '#0F172A', label: 'REGULER MEMBER (DISKON 2%)' };
});

const filteredKatalog = computed(() => {
    const q = searchProduk.value.trim().toLowerCase();
    return (props.produkKatalog || []).filter((p) => {
        const matchKategori =
            !selectedKategoriId.value ||
            Number(p.id_kategori) === Number(selectedKategoriId.value);
        const matchSearch =
            !q ||
            (p.nama_produk || '').toLowerCase().includes(q) ||
            (p.kode_produk || '').toLowerCase().includes(q);
        return matchKategori && matchSearch;
    });
});

const addToCart = (produk) => {
    errorMessage.value = '';
    const existing = cart.value.find((item) => item.id_produk === produk.id_produk);
    if (existing) {
        if (existing.jumlah + 1 > produk.stok) {
            errorMessage.value = `Stok ${produk.nama_produk} tersisa ${produk.stok}.`;
            return;
        }
        existing.jumlah += 1;
    } else {
        if (produk.stok < 1) {
            errorMessage.value = `Stok ${produk.nama_produk} habis.`;
            return;
        }
        cart.value.push({
            id_produk: produk.id_produk,
            kode_produk: produk.kode_produk,
            nama_produk: produk.nama_produk,
            harga: Number(produk.harga),
            stok: Number(produk.stok),
            jumlah: 1,
        });
    }
    playBeepSound();
};

const updateCartQty = (item, delta) => {
    const nextQty = item.jumlah + delta;
    if (nextQty <= 0) {
        cart.value = cart.value.filter((i) => i.id_produk !== item.id_produk);
        return;
    }
    if (nextQty > item.stok) {
        errorMessage.value = `Maksimal stok tersedia untuk ${item.nama_produk} adalah ${item.stok}.`;
        return;
    }
    item.jumlah = nextQty;
};

const clearCart = () => {
    cart.value = [];
    usePoints.value = false;
    errorMessage.value = '';
};

const subtotalBelanja = computed(() => {
    return cart.value.reduce((acc, item) => acc + item.harga * item.jumlah, 0);
});

const diskonTierPersen = computed(() => {
    return Number(props.stats.diskon_tier_persen || props.pelanggan?.diskon_tier_persen || 2);
});

const nominalDiskonTier = computed(() => {
    return Math.round(subtotalBelanja.value * (diskonTierPersen.value / 100));
});

const maxRedeemablePoints = computed(() => {
    const availablePoints = Number(props.stats.poin || props.pelanggan?.poin || 0);
    const remainingAfterTier = Math.max(0, subtotalBelanja.value - nominalDiskonTier.value);
    const maxPointsForRemaining = Math.floor(remainingAfterTier / 100);
    return Math.max(0, Math.min(availablePoints, maxPointsForRemaining));
});

const poinDitukar = computed(() => {
    return usePoints.value ? maxRedeemablePoints.value : 0;
});

const potonganPoin = computed(() => {
    return poinDitukar.value * 100;
});

const totalAkhir = computed(() => {
    return Math.max(0, subtotalBelanja.value - nominalDiskonTier.value - potonganPoin.value);
});

const estimasiPoinDidapat = computed(() => {
    return totalAkhir.value >= 10000 ? Math.floor(totalAkhir.value / 10000) : 0;
});

const submitSelfCheckout = async () => {
    if (cart.value.length === 0 || isProcessing.value) {
        return;
    }
    errorMessage.value = '';
    successMessage.value = '';

    if (metodePembayaran.value === 'SALDO_MEMBER' && Number(props.stats.saldo || 0) < totalAkhir.value) {
        errorMessage.value = `Saldo Kartu RFID tidak mencukupi (Saldo Anda: ${formatRupiah(props.stats.saldo || 0)}). Silakan pilih QRIS atau Bayar Tunai di Kasir.`;
        return;
    }

    isProcessing.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/member/api/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                metode_pembayaran: metodePembayaran.value,
                payment_channel:
                    metodePembayaran.value === 'QRIS_MPM'
                        ? paymentSubMode.value === 'DUITKU_AUTO'
                            ? `DUITKU ${duitkuBanks.find((b) => b.id === selectedDuitkuBank.value)?.short || selectedDuitkuBank.value}`
                            : 'QRIS GOPAY MERCHANT (MANUAL)'
                        : metodePembayaran.value === 'SALDO_MEMBER'
                          ? 'SALDO MEMBER RFID'
                          : 'TUNAI DI KASIR',
                nominal_bayar: totalAkhir.value,
                poin_ditukar: poinDitukar.value,
                items: cart.value.map((i) => ({
                    id_produk: i.id_produk,
                    jumlah: i.jumlah,
                })),
            }),
        });

        const result = await response.json();
        if (!response.ok) {
            errorMessage.value = result.message || 'Gagal memproses pesanan.';
            return;
        }

        playBeepSound();
        clearCart();

        // HANYA JIKA SUDAH LUNAS (status === 'selesai') baru boleh buka Struk!
        if (result.is_paid && result.data?.status === 'selesai') {
            selectedReceipt.value = result.data;
            showReceiptModal.value = true;
            successMessage.value = result.message || 'Pembayaran berhasil! Struk siap dicetak.';
        } else {
            // Jika belum dibayar (status === 'pending'), BUKA MODAL TAGIHAN PEMBAYARAN DUITKU (Bukan Struk!)
            openPendingInvoiceModal(result.data);
            activeTab.value = 'riwayat';
            successMessage.value = result.message;
        }

        router.reload({ only: ['pelanggan', 'stats', 'riwayatTransaksi', 'produkKatalog'] });
    } catch {
        errorMessage.value = 'Terjadi gangguan jaringan saat memproses pesanan.';
    } finally {
        isProcessing.value = false;
    }
};

const modalPayTab = ref('MANUAL_QRIS'); // 'MANUAL_QRIS' | 'DUITKU_AUTO'

const openPendingInvoiceModal = (trx) => {
    pendingInvoice.value = trx;
    paymentRefInput.value = trx.payment_ref && trx.payment_ref !== 'WAITING-PAYMENT' && trx.payment_ref !== 'MENUNGGU-VERIFIKASI-KASIR' ? trx.payment_ref : '';
    proofFile.value = null;
    proofPreviewUrl.value = trx.bukti_pembayaran_url || null;
    paymentModalError.value = '';
    duitkuCheckMessage.value = '';
    const isDuitkuChannel = String(trx.payment_channel || '').toUpperCase().includes('DUITKU');
    modalPayTab.value = isDuitkuChannel ? 'DUITKU_AUTO' : 'MANUAL_QRIS';
    showPendingPaymentModal.value = true;
    if (trx.metode_pembayaran === 'QRIS_MPM' && isDuitkuChannel) {
        fetchMemberDuitkuInquiry(trx);
    }
};

const switchModalPayTab = (tab) => {
    modalPayTab.value = tab;
    if (tab === 'DUITKU_AUTO' && pendingInvoice.value) {
        fetchMemberDuitkuInquiry(pendingInvoice.value);
    }
};

const handleProofFileChange = (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        return;
    }
    proofFile.value = file;
    proofPreviewUrl.value = URL.createObjectURL(file);
};

const uploadProofPayment = async () => {
    if (!pendingInvoice.value || isConfirmingPayment.value) {
        return;
    }
    paymentModalError.value = '';

    if (!proofFile.value) {
        paymentModalError.value = 'Silakan pilih foto/screenshot bukti transfer QRIS terlebih dahulu.';
        return;
    }

    isConfirmingPayment.value = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('bukti_pembayaran', proofFile.value);
        if (paymentRefInput.value.trim()) {
            formData.append('payment_ref', paymentRefInput.value.trim());
        }

        const response = await fetch(`/member/api/transaksi/${pendingInvoice.value.id_transaksi}/upload-bukti`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        const result = await response.json();
        if (!response.ok) {
            paymentModalError.value = result.message || 'Gagal mengunggah bukti pembayaran.';
            return;
        }

        playBeepSound();
        showPendingPaymentModal.value = false;
        pendingInvoice.value = null;
        successMessage.value = result.message;
        router.reload({ only: ['pelanggan', 'stats', 'riwayatTransaksi', 'produkKatalog'] });
    } catch {
        paymentModalError.value = 'Terjadi gangguan jaringan saat mengunggah bukti pembayaran.';
    } finally {
        isConfirmingPayment.value = false;
    }
};

const openReceipt = (trx) => {
    if (trx.status !== 'selesai') {
        openPendingInvoiceModal(trx);
        return;
    }
    selectedReceipt.value = trx;
    showReceiptModal.value = true;
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'riwayat') {
        activeTab.value = 'riwayat';
    }
    const buyFromQuery = params.get('buy');
    const buyFromSession = window.sessionStorage.getItem('toko_pending_buy_product');
    const targetProductId = buyFromQuery || buyFromSession;

    if (targetProductId) {
        window.sessionStorage.removeItem('toko_pending_buy_product');
        const found = (props.produkKatalog || []).find(
            (p) => Number(p.id_produk) === Number(targetProductId),
        );
        if (found) {
            activeTab.value = 'belanja';
            addToCart(found);
        }
    }
});
</script>

<template>
    <Head title="Portal & Belanja Member" />

    <AdminLayout title="Portal Member — Belanja Mandiri, Kartu RFID/QR & Poin Loyalitas">
        <div class="space-y-5 text-xs">
            <!-- Baris Atas: Kartu Member Digital + 4 Kotak Ringkasan Loyalitas -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 font-mono">
                <!-- Kartu Member Digital (QR & RFID) -->
                <div
                    class="lg:col-span-5 rounded-md border p-5 text-white shadow-sm flex flex-col justify-between"
                    :style="{ backgroundColor: activeTokens.primary, borderColor: activeTokens.primary }"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-[11px] font-semibold uppercase opacity-85">
                                KARTU MEMBER RESMI • YAZID ELEKTRONIK
                            </div>
                            <div class="text-lg font-bold uppercase tracking-wide mt-0.5">
                                {{ pelanggan.nama_pelanggan }}
                            </div>
                            <div class="text-[11px] opacity-90">
                                Kota: {{ pelanggan.kota }} • Telp: {{ pelanggan.no_telepon || '-' }}
                            </div>
                        </div>
                        <span
                            class="px-2.5 py-1 rounded text-[10px] font-bold shrink-0"
                            :style="{
                                backgroundColor: tierBadgeStyle.bg,
                                color: tierBadgeStyle.text,
                            }"
                        >
                            {{ tierBadgeStyle.label }}
                        </span>
                    </div>

                    <div class="my-4 p-3 rounded-md border border-slate-300 bg-white text-slate-900 flex items-center gap-4">
                        <img
                            :src="generateQrSvgDataUrl(memberScanCode)"
                            :alt="memberScanCode"
                            class="w-20 h-20 border border-slate-300 p-1 shrink-0 rounded"
                        />
                        <div class="space-y-1">
                            <div class="text-[10px] font-semibold text-slate-500 uppercase">
                                UID RFID / KODE SCAN KASIR:
                            </div>
                            <div class="text-sm font-bold px-2 py-0.5 bg-slate-100 text-slate-900 border border-slate-300 rounded inline-block">
                                {{ memberScanCode }}
                            </div>
                            <div class="text-[11px] text-slate-600 font-sans">
                                Belanja langsung dari katalog di bawah atau tunjukkan QR/RFID ini di kasir.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] pt-2 border-t border-white/25">
                        <span>ID Member: #{{ pelanggan.id_pelanggan }}</span>
                        <span class="font-semibold">
                            Diskon Otomatis {{ stats.diskon_tier_persen }}% • 1 Poin = Rp 100
                        </span>
                    </div>
                </div>

                <!-- 4 Kotak Ringkasan Loyalitas Member (Putih Polos & Seragam) -->
                <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-4 rounded-md border flex flex-col justify-between toko-panel">
                        <div>
                            <div class="text-xs font-semibold uppercase text-slate-500 font-sans">
                                Poin Loyalitas Terkumpul
                            </div>
                            <div class="text-2xl font-bold mt-2 text-slate-900 tabular-nums">
                                {{ stats.poin || 0 }} POIN
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-600 font-sans">
                            Setara Potongan: <strong class="font-mono text-slate-900">{{ formatRupiah(stats.nilai_rupiah_poin || 0) }}</strong>
                            (+1 Poin tiap Rp 10.000 belanja lunas)
                        </div>
                    </div>

                    <div class="p-4 rounded-md border flex flex-col justify-between toko-panel">
                        <div>
                            <div class="text-xs font-semibold uppercase text-slate-500 font-sans">
                                Saldo Kartu RFID Member
                            </div>
                            <div class="text-2xl font-bold mt-2 text-slate-900 tabular-nums">
                                {{ formatRupiah(stats.saldo || 0) }}
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-600 font-sans">
                            Bayar pakai Saldo RFID langsung lunas &amp; langsung cetak struk.
                        </div>
                    </div>

                    <div class="p-4 rounded-md border flex flex-col justify-between toko-panel">
                        <div>
                            <div class="text-xs font-semibold uppercase text-slate-500 font-sans">
                                Total Akumulasi Belanja Lunas
                            </div>
                            <div class="text-2xl font-bold mt-2 text-slate-900 tabular-nums">
                                {{ formatRupiah(stats.total_belanja || 0) }}
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-600 font-sans">
                            <span v-if="stats.kurang_ke_next_tier > 0">
                                Belanja <strong class="font-mono text-slate-900">{{ formatRupiah(stats.kurang_ke_next_tier) }}</strong> lagi untuk naik ke Tier
                                <strong>{{ stats.next_tier }}</strong>.
                            </span>
                            <span v-else class="font-semibold text-slate-900">
                                Anda berada di Tier Tertinggi (GOLD - Diskon 10%).
                            </span>
                        </div>
                    </div>

                    <div class="p-4 rounded-md border flex flex-col justify-between toko-panel">
                        <div>
                            <div class="text-xs font-semibold uppercase text-slate-500 font-sans">
                                Total Penghematan Member
                            </div>
                            <div class="text-2xl font-bold mt-2 text-slate-900 tabular-nums">
                                {{ formatRupiah(stats.total_hemat || 0) }}
                            </div>
                        </div>
                        <div class="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-600 font-sans">
                            Transaksi Lunas: <strong class="font-mono text-slate-900">{{ stats.total_transaksi || 0 }} Struk</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigasi: Belanja Mandiri vs Riwayat Transaksi -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-300 pb-2">
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="activeTab = 'belanja'"
                        class="px-4 py-2 rounded-md text-xs font-semibold transition"
                        :class="
                            activeTab === 'belanja'
                                ? 'bg-[#1E3A5F] text-white'
                                : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'
                        "
                    >
                        Katalog &amp; Belanja Mandiri ({{ produkKatalog.length }} Produk)
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'riwayat'"
                        class="px-4 py-2 rounded-md text-xs font-semibold transition"
                        :class="
                            activeTab === 'riwayat'
                                ? 'bg-[#1E3A5F] text-white'
                                : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'
                        "
                    >
                        Riwayat Pesanan &amp; Struk Digital ({{ riwayatTransaksi.length }})
                    </button>
                </div>

                <div class="text-xs text-slate-600">
                    Diskon Member Aktif: <strong class="font-mono text-slate-900">{{ diskonTierPersen }}% ({{ stats.tier || 'Reguler' }})</strong>
                </div>
            </div>

            <!-- Notifikasi Error / Sukses -->
            <div
                v-if="errorMessage"
                class="p-3 rounded-md border border-red-300 bg-red-50 text-red-700 text-xs font-medium"
            >
                {{ errorMessage }}
            </div>
            <div
                v-if="successMessage"
                class="p-3 rounded-md border border-slate-300 bg-white text-slate-900 text-xs font-medium"
            >
                {{ successMessage }}
            </div>

            <!-- TAB 1: KATALOG & BELANJA MANDIRI MEMBER -->
            <div v-if="activeTab === 'belanja'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Kiri: Daftar Produk (7 Kolom) -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="p-3 rounded-md border bg-white border-slate-300 flex flex-wrap items-center gap-2">
                        <input
                            v-model="searchProduk"
                            type="text"
                            placeholder="Cari nama produk atau kode PLU..."
                            class="flex-1 min-w-[200px] px-3 py-2 rounded-md border border-slate-300 text-xs font-medium"
                        />
                        <select
                            v-model="selectedKategoriId"
                            class="px-3 py-2 rounded-md border border-slate-300 text-xs font-medium bg-white"
                        >
                            <option value="">Semua Kategori</option>
                            <option
                                v-for="kat in kategoriList"
                                :key="kat.id_kategori"
                                :value="kat.id_kategori"
                            >
                                {{ kat.nama_kategori }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <div
                            v-for="item in filteredKatalog"
                            :key="item.id_produk"
                            class="rounded-md border border-slate-300 bg-white p-3 flex flex-col justify-between hover:border-slate-400 transition"
                        >
                            <div>
                                <div class="h-28 rounded bg-slate-50 border border-slate-200 flex items-center justify-center p-2">
                                    <img
                                        :src="getProductImage(item)"
                                        :alt="item.nama_produk"
                                        @error="(e) => handleProductImageError(e, item)"
                                        class="max-h-full max-w-full object-contain"
                                    />
                                </div>
                                <div class="mt-2.5 flex items-center justify-between text-[11px] font-mono text-slate-500">
                                    <span>{{ item.kode_produk }}</span>
                                    <span>Stok: {{ item.stok }}</span>
                                </div>
                                <div class="font-semibold text-slate-900 mt-0.5 line-clamp-2">
                                    {{ item.nama_produk }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ item.kategori?.nama_kategori || 'Umum' }}
                                </div>
                            </div>

                            <div class="mt-3 pt-2.5 border-t border-slate-200 flex items-center justify-between gap-2">
                                <span class="font-mono font-bold text-sm text-slate-900 tabular-nums">
                                    {{ formatRupiah(item.harga) }}
                                </span>
                                <button
                                    type="button"
                                    @click="addToCart(item)"
                                    class="px-3 py-1.5 rounded-md text-xs font-semibold text-white bg-[#1E3A5F] hover:bg-[#162C49] transition"
                                >
                                    + Keranjang
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="filteredKatalog.length === 0"
                            class="col-span-full p-8 rounded-md border border-slate-300 bg-white text-center text-slate-500"
                        >
                            Tidak ada produk yang sesuai filter pencarian.
                        </div>
                    </div>
                </div>

                <!-- Kanan: Keranjang Belanja & Checkout Member (5 Kolom) -->
                <div class="lg:col-span-5 rounded-md border border-slate-300 bg-white overflow-hidden sticky top-4">
                    <div class="px-4 py-3 bg-slate-50 border-b border-slate-300 flex items-center justify-between">
                        <h3 class="font-semibold uppercase text-xs text-slate-800">
                            Keranjang Belanja Member ({{ cart.reduce((n, i) => n + i.jumlah, 0) }} Item)
                        </h3>
                        <button
                            v-if="cart.length > 0"
                            type="button"
                            @click="clearCart"
                            class="text-xs font-medium text-red-700 hover:underline"
                        >
                            Kosongkan
                        </button>
                    </div>

                    <div class="p-4 space-y-4">
                        <div v-if="cart.length > 0" class="divide-y divide-slate-200 max-h-64 overflow-y-auto">
                            <div
                                v-for="item in cart"
                                :key="item.id_produk"
                                class="py-2.5 flex items-center justify-between gap-2"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-slate-900 truncate">
                                        {{ item.nama_produk }}
                                    </div>
                                    <div class="font-mono text-[11px] text-slate-500">
                                        {{ formatRupiah(item.harga) }} &times; {{ item.jumlah }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 font-mono">
                                    <button
                                        type="button"
                                        @click="updateCartQty(item, -1)"
                                        class="w-6 h-6 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-bold"
                                    >
                                        -
                                    </button>
                                    <span class="w-7 text-center font-semibold tabular-nums">
                                        {{ item.jumlah }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="updateCartQty(item, 1)"
                                        class="w-6 h-6 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-bold"
                                    >
                                        +
                                    </button>
                                </div>

                                <div class="w-24 text-right font-mono font-semibold text-slate-900 tabular-nums">
                                    {{ formatRupiah(item.harga * item.jumlah) }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="py-8 text-center text-slate-500 border border-dashed border-slate-300 rounded-md"
                        >
                            Keranjang belanja masih kosong. Klik tombol <strong>+ Keranjang</strong> pada produk di sebelah kiri.
                        </div>

                        <div
                            v-if="cart.length > 0 && (stats.poin || 0) > 0"
                            class="p-3 rounded-md bg-slate-50 border border-slate-300 flex items-center justify-between gap-2"
                        >
                            <div>
                                <div class="font-semibold text-slate-800">
                                    Gunakan Poin Member ({{ stats.poin }} Poin)
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Potongan tambahan {{ formatRupiah(maxRedeemablePoints * 100) }}
                                </div>
                            </div>
                            <input
                                v-model="usePoints"
                                type="checkbox"
                                class="w-4 h-4 rounded border-slate-300 text-[#1E3A5F]"
                            />
                        </div>

                        <div v-if="cart.length > 0" class="space-y-2">
                            <label class="block text-xs font-semibold uppercase text-slate-600">
                                Metode Pembayaran
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="
                                        metodePembayaran = 'QRIS_MPM';
                                        paymentSubMode = 'MANUAL_QRIS';
                                    "
                                    class="py-2 px-2 rounded-md border text-xs font-semibold text-center transition"
                                    :class="
                                        metodePembayaran === 'QRIS_MPM' && paymentSubMode === 'MANUAL_QRIS'
                                            ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    "
                                >
                                    QRIS Toko (Manual)
                                </button>
                                <button
                                    type="button"
                                    @click="
                                        metodePembayaran = 'QRIS_MPM';
                                        paymentSubMode = 'DUITKU_AUTO';
                                    "
                                    class="py-2 px-2 rounded-md border text-xs font-semibold text-center transition"
                                    :class="
                                        metodePembayaran === 'QRIS_MPM' && paymentSubMode === 'DUITKU_AUTO'
                                            ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    "
                                >
                                    Duitku VA / QRIS (Otomatis)
                                </button>
                                <button
                                    type="button"
                                    @click="metodePembayaran = 'SALDO_MEMBER'"
                                    class="py-2 px-2 rounded-md border text-xs font-semibold text-center transition"
                                    :class="
                                        metodePembayaran === 'SALDO_MEMBER'
                                            ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    "
                                >
                                    Saldo RFID
                                </button>
                                <button
                                    type="button"
                                    @click="metodePembayaran = 'TUNAI'"
                                    class="py-2 px-2 rounded-md border text-xs font-semibold text-center transition"
                                    :class="
                                        metodePembayaran === 'TUNAI'
                                            ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    "
                                >
                                    Bayar di Kasir
                                </button>
                            </div>

                            <!-- Pilihan Bank Virtual Account / QRIS Duitku (Hanya muncul jika pilih Duitku Otomatis) -->
                            <div
                                v-if="metodePembayaran === 'QRIS_MPM' && paymentSubMode === 'DUITKU_AUTO'"
                                class="pt-1 space-y-1.5"
                            >
                                <div class="text-[11px] font-semibold text-slate-600">
                                    Pilih Bank Virtual Account / Channel Duitku:
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="b in duitkuBanks"
                                        :key="b.id"
                                        type="button"
                                        @click="selectedDuitkuBank = b.id"
                                        class="px-2.5 py-1 rounded border text-[11px] font-semibold transition"
                                        :class="
                                            selectedDuitkuBank === b.id
                                                ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                                : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                        "
                                    >
                                        {{ b.short }}
                                    </button>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-md border border-slate-300 bg-slate-50 text-[11px] text-slate-600">
                                <template v-if="metodePembayaran === 'SALDO_MEMBER'">
                                    Saldo Anda: <strong class="font-mono text-slate-900">{{ formatRupiah(stats.saldo || 0) }}</strong>.
                                    Pembayaran langsung memotong saldo dan <strong>langsung Lunas &amp; Cetak Struk</strong>.
                                </template>
                                <template v-else-if="metodePembayaran === 'QRIS_MPM' && paymentSubMode === 'MANUAL_QRIS'">
                                    <strong>QRIS Resmi Toko (Manual — {{ gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK' }}):</strong>
                                    Bebas nominal berapa pun (sesuai harga asli). Setelah scan QRIS, unggah foto bukti transfer untuk <strong>diverifikasi manual oleh Kasir</strong>.
                                </template>
                                <template v-else-if="metodePembayaran === 'QRIS_MPM' && paymentSubMode === 'DUITKU_AUTO'">
                                    <strong>Duitku Sandbox (Otomatis — {{ duitkuBanks.find((b) => b.id === selectedDuitkuBank)?.label }}):</strong>
                                    Dapatkan <strong>Nomor Virtual Account / Kode Bayar</strong> untuk di-<strong>Copy</strong> &amp; dicoba di Simulator Duitku Sandbox (minimal transaksi VA di Duitku Rp 10.000).
                                </template>
                                <template v-else>
                                    Pesanan akan berstatus <strong>MENUNGGU PEMBAYARAN DI KASIR</strong>. Struk dikunci sampai Anda membayar tunai dan dikonfirmasi lunas oleh Kasir.
                                </template>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 space-y-1.5 font-mono text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span class="font-sans">Subtotal</span>
                                <span class="tabular-nums">{{ formatRupiah(subtotalBelanja) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span class="font-sans">Diskon Member ({{ stats.tier }} {{ diskonTierPersen }}%)</span>
                                <span class="tabular-nums">- {{ formatRupiah(nominalDiskonTier) }}</span>
                            </div>
                            <div v-if="potonganPoin > 0" class="flex justify-between text-slate-600">
                                <span class="font-sans">Tukar Poin ({{ poinDitukar }} pts)</span>
                                <span class="tabular-nums">- {{ formatRupiah(potonganPoin) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-300 flex justify-between text-sm font-bold text-slate-900">
                                <span class="font-sans">TOTAL TAGIHAN</span>
                                <span class="tabular-nums">{{ formatRupiah(totalAkhir) }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500 font-sans text-right">
                                Poin setelah Lunas: <strong class="font-mono">+{{ estimasiPoinDidapat }} Poin</strong>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="cart.length === 0 || isProcessing"
                            @click="submitSelfCheckout"
                            class="w-full py-2.5 rounded-md font-semibold uppercase text-white bg-[#1E3A5F] hover:bg-[#162C49] disabled:opacity-50 transition"
                        >
                            {{
                                isProcessing
                                    ? 'MEMPROSES PESANAN...'
                                    : metodePembayaran === 'SALDO_MEMBER'
                                      ? 'BAYAR SALDO RFID & CETAK STRUK'
                                      : 'BUAT PESANAN & LANJUT PEMBAYARAN'
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TABEL RIWAYAT PESANAN & STRUK DIGITAL MEMBER -->
            <div v-else class="rounded-md border overflow-hidden toko-panel">
                <div class="px-4 py-3 border-b flex items-center justify-between toko-thead">
                    <h2 class="font-semibold uppercase text-xs">
                        Riwayat Pesanan &amp; Struk Belanja Anda ({{ riwayatTransaksi.length }})
                    </h2>
                    <span class="text-[11px] text-slate-500">
                        * Struk hanya dapat dibuka/dicetak untuk pesanan yang sudah LUNAS (SELESAI)
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left font-mono">
                        <thead class="uppercase text-[11px] font-semibold border-b toko-thead">
                            <tr>
                                <th class="p-3">No. Pesanan</th>
                                <th class="p-3">Tanggal</th>
                                <th class="p-3">Metode Bayar</th>
                                <th class="p-3 text-right">Subtotal</th>
                                <th class="p-3 text-right">Diskon</th>
                                <th class="p-3 text-right">Total Tagihan</th>
                                <th class="p-3 text-center">Status Bayar</th>
                                <th class="p-3 text-center">Poin</th>
                                <th class="p-3 text-center">Aksi / Struk</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 tabular-nums">
                            <tr v-for="trx in riwayatTransaksi" :key="trx.id_transaksi" class="hover:bg-slate-50/70">
                                <td class="p-3 font-semibold">{{ trx.kode_transaksi }}</td>
                                <td class="p-3">{{ trx.tanggal }}</td>
                                <td class="p-3 font-medium uppercase">
                                    {{ trx.payment_channel || trx.metode_pembayaran || 'TUNAI' }}
                                </td>
                                <td class="p-3 text-right">{{ formatRupiah(trx.subtotal) }}</td>
                                <td class="p-3 text-right text-slate-600">- {{ formatRupiah(trx.diskon) }}</td>
                                <td class="p-3 text-right font-semibold">{{ formatRupiah(trx.total_belanja) }}</td>
                                <td class="p-3 text-center font-mono">
                                    <span
                                        v-if="trx.status === 'selesai'"
                                        class="text-slate-700 font-medium"
                                    >
                                        LUNAS (SELESAI)
                                    </span>
                                    <span
                                        v-else-if="trx.status === 'pending' && trx.bukti_pembayaran"
                                        class="text-amber-700 font-semibold"
                                    >
                                        MENUNGGU VERIFIKASI KASIR
                                    </span>
                                    <span
                                        v-else-if="trx.status === 'pending'"
                                        class="text-red-700 font-semibold"
                                    >
                                        BELUM DIBAYAR
                                    </span>
                                    <span v-else class="text-red-700 font-semibold">
                                        BATAL (VOID)
                                    </span>
                                </td>
                                <td class="p-3 text-center font-mono">
                                    <template v-if="trx.status === 'selesai'">
                                        <span
                                            v-if="trx.poin_didapat > 0"
                                            class="text-slate-700 font-medium"
                                        >
                                            +{{ trx.poin_didapat }}
                                        </span>
                                        <span
                                            v-if="trx.poin_ditukar > 0"
                                            class="ml-1 text-red-700 font-semibold"
                                        >
                                            -{{ trx.poin_ditukar }}
                                        </span>
                                        <span v-if="!trx.poin_didapat && !trx.poin_ditukar" class="text-slate-400">-</span>
                                    </template>
                                    <span v-else class="text-slate-400 text-[11px]">Menunggu Lunas</span>
                                </td>
                                <td class="p-3 text-center font-sans">
                                    <button
                                        v-if="trx.status === 'selesai'"
                                        type="button"
                                        @click="openReceipt(trx)"
                                        class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                                    >
                                        Lihat / Cetak Struk
                                    </button>
                                    <button
                                        v-else-if="trx.status === 'pending'"
                                        type="button"
                                        @click="openPendingInvoiceModal(trx)"
                                        class="px-2.5 py-1 rounded text-white bg-[#1E3A5F] hover:bg-[#162C49] font-medium"
                                    >
                                        {{ trx.bukti_pembayaran ? 'Lihat Status / Bukti' : 'Bayar / Copy VA Duitku' }}
                                    </button>
                                    <span v-else class="text-slate-400 text-[11px]">-</span>
                                </td>
                            </tr>
                            <tr v-if="riwayatTransaksi.length === 0">
                                <td colspan="9" class="p-8 text-center text-slate-500 font-sans">
                                    Belum ada riwayat pesanan pada akun Member ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL TAGIHAN BELUM DIBAYAR (STRUK TERKUNCI SAMPAI LUNAS) -->
        <div
            v-if="showPendingPaymentModal && pendingInvoice"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto"
        >
            <div class="w-full max-w-md rounded-md border border-slate-300 bg-white p-5 shadow-xl space-y-4 text-xs">
                <div class="flex items-start justify-between gap-2 pb-3 border-b border-slate-200">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-mono font-semibold text-[10px] uppercase">
                            STATUS: BELUM DIBAYAR (STRUK TERKUNCI)
                        </span>
                        <h3 class="text-sm font-bold text-slate-900 mt-1.5 font-mono">
                            Tagihan Pesanan {{ pendingInvoice.kode_transaksi }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="showPendingPaymentModal = false"
                        class="text-slate-500 hover:text-slate-800 font-bold"
                    >
                        ✕
                    </button>
                </div>

                <div class="p-3 rounded-md bg-slate-50 border border-slate-300 space-y-1.5 font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Metode Pembayaran:</span>
                        <span class="font-semibold text-slate-900">{{ pendingInvoice.payment_channel || pendingInvoice.metode_pembayaran }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Total Tagihan:</span>
                        <span class="text-sm font-bold text-slate-900">{{ formatRupiah(pendingInvoice.total_belanja) }}</span>
                    </div>
                </div>

                <!-- Jika Metode Pembayaran adalah QRIS Toko (Manual) atau Duitku (Otomatis) -->
                <div v-if="pendingInvoice.metode_pembayaran === 'QRIS_MPM'" class="space-y-3">
                    <!-- Tab Switcher: QRIS Toko (Manual) vs Duitku Sandbox (Otomatis) -->
                    <div class="grid grid-cols-2 gap-1.5 p-1 rounded-md bg-slate-100 border border-slate-300">
                        <button
                            type="button"
                            @click="switchModalPayTab('MANUAL_QRIS')"
                            class="py-1.5 px-2 rounded text-xs font-semibold transition"
                            :class="
                                modalPayTab === 'MANUAL_QRIS'
                                    ? 'bg-[#1E3A5F] text-white'
                                    : 'text-slate-700 hover:bg-white'
                            "
                        >
                            QRIS Toko (Manual)
                        </button>
                        <button
                            type="button"
                            @click="switchModalPayTab('DUITKU_AUTO')"
                            class="py-1.5 px-2 rounded text-xs font-semibold transition"
                            :class="
                                modalPayTab === 'DUITKU_AUTO'
                                    ? 'bg-[#1E3A5F] text-white'
                                    : 'text-slate-700 hover:bg-white'
                            "
                        >
                            Duitku VA/QR (Otomatis)
                        </button>
                    </div>

                    <!-- MODE 1: QRIS RESMI TOKO (MANUAL — UPLOAD BUKTI & AUTO-CHECK VERIFIKASI KASIR) -->
                    <div v-if="modalPayTab === 'MANUAL_QRIS'" class="space-y-3">
                        <div class="flex items-center justify-center gap-1.5 py-1.5 px-2.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                            <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                            <span>AUTO-CHECK AKTIF: Struk akan terbuka otomatis begitu diverifikasi Kasir</span>
                        </div>

                        <div class="p-3 rounded-md border border-slate-300 bg-white text-center space-y-2">
                            <div class="text-xs font-semibold text-slate-800">
                                Scan QRIS Resmi: {{ gatewayConfig.merchant_name || 'YAZID, ELEKTRONIK' }}
                            </div>
                            <img
                                :src="gatewayConfig.qris_image_url || '/images/qris-toko.jpg'"
                                alt="QRIS GoPay Merchant"
                                class="w-44 mx-auto rounded border border-slate-300 p-1 bg-white"
                            />
                            <div class="text-[11px] text-slate-600">
                                Transfer sesuai tagihan asli <strong class="font-mono text-slate-900">{{ formatRupiah(pendingInvoice.total_belanja) }}</strong>, lalu unggah foto/screenshot bukti transfer di bawah ini agar <strong>diverifikasi manual oleh Kasir</strong>.
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Upload Foto / Screenshot Bukti Transfer QRIS *
                            </label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleProofFileChange"
                                class="w-full text-xs border border-slate-300 rounded-md p-1.5 bg-white"
                            />
                            <div v-if="proofPreviewUrl" class="mt-2 p-2 border border-slate-200 rounded bg-slate-50 text-center">
                                <img :src="proofPreviewUrl" alt="Preview Bukti Bayar" class="max-h-36 mx-auto rounded" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                Nomor Referensi / Catatan (Opsional)
                            </label>
                            <input
                                v-model="paymentRefInput"
                                type="text"
                                placeholder="Contoh: GOPAY-99283123 atau nama pengirim..."
                                class="w-full px-3 py-2 rounded-md border border-slate-300 font-mono font-semibold text-slate-900"
                            />
                            <div v-if="paymentModalError" class="text-red-700 font-medium mt-1">
                                {{ paymentModalError }}
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-1">
                            <button
                                type="button"
                                @click="showPendingPaymentModal = false"
                                class="px-3 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Tutup
                            </button>
                            <button
                                type="button"
                                :disabled="isConfirmingPayment"
                                @click="uploadProofPayment"
                                class="px-4 py-2 rounded-md font-semibold text-white bg-[#1E3A5F] hover:bg-[#162C49] disabled:opacity-50"
                            >
                                {{ isConfirmingPayment ? 'Mengunggah...' : 'Kirim Bukti ke Kasir' }}
                            </button>
                        </div>
                    </div>

                    <!-- MODE 2: DUITKU SANDBOX (OTOMATIS — PILIH BANK VA, COPY KODE, SIMULATOR SANDBOX) -->
                    <div v-else class="space-y-3">
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-semibold text-slate-700">
                                Pilih Bank Virtual Account / QRIS Duitku Sandbox:
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="b in duitkuBanks"
                                    :key="b.id"
                                    type="button"
                                    @click="switchMemberDuitkuBank(b.id)"
                                    class="px-2.5 py-1 rounded border text-[11px] font-semibold transition"
                                    :class="
                                        selectedDuitkuBank === b.id
                                            ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                                            : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                                    "
                                >
                                    {{ b.short }}
                                </button>
                            </div>
                        </div>

                        <div class="p-3 rounded-md border border-slate-300 bg-white text-center space-y-2.5">
                            <div v-if="loadingDuitkuInvoice" class="py-6 text-slate-500 font-semibold">
                                Membuat Kode Pembayaran di Duitku Sandbox ({{ selectedDuitkuBank }})...
                            </div>

                            <!-- Jika Nominal di bawah Rp 10.000 (Batas Minimal VA Duitku) -->
                            <div
                                v-else-if="duitkuInvoiceData?.gateway_error"
                                class="p-3 rounded-md bg-amber-50 border border-amber-300 text-amber-900 text-left space-y-2"
                            >
                                <div class="font-bold text-xs">
                                    ⚠ Informasi Batas Minimal Duitku Sandbox:
                                </div>
                                <p class="text-[11px] leading-relaxed">
                                    {{ duitkuInvoiceData.gateway_error }}
                                </p>
                                <button
                                    type="button"
                                    @click="switchModalPayTab('MANUAL_QRIS')"
                                    class="w-full py-2 px-3 rounded-md bg-[#1E3A5F] text-white text-xs font-semibold hover:bg-[#162C49]"
                                >
                                    Gunakan QRIS Toko (Manual — Bebas Nominal) &rarr;
                                </button>
                            </div>

                            <template v-else>
                                <!-- Nomor Virtual Account + Tombol Copy -->
                                <div
                                    v-if="duitkuInvoiceData?.va_number"
                                    class="p-3 rounded-md bg-slate-50 border border-slate-300 space-y-2"
                                >
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase">
                                        NOMOR VIRTUAL ACCOUNT ({{ duitkuBanks.find((x) => x.id === selectedDuitkuBank)?.label }}):
                                    </div>
                                    <div class="text-lg sm:text-xl font-extrabold font-mono tracking-wider text-slate-900 select-all bg-white py-2 px-3 rounded border border-slate-300">
                                        {{ duitkuInvoiceData.va_number }}
                                    </div>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="copyDuitkuCode(duitkuInvoiceData.va_number, 'Nomor Virtual Account')"
                                            class="px-3 py-1.5 rounded-md bg-[#1E3A5F] text-white text-xs font-semibold hover:bg-[#162C49]"
                                        >
                                            📋 Salin / Copy Nomor VA
                                        </button>
                                        <button
                                            v-if="duitkuInvoiceData?.qr_id"
                                            type="button"
                                            @click="copyDuitkuCode(duitkuInvoiceData.qr_id, 'Reference Duitku')"
                                            class="px-3 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 text-xs font-semibold hover:bg-slate-50"
                                        >
                                            📋 Copy Reference
                                        </button>
                                    </div>
                                </div>

                                <!-- QRIS Dinamis Duitku jika memilih SP -->
                                <div v-else-if="duitkuInvoiceData?.qr_string" class="space-y-2">
                                    <div class="text-xs font-semibold text-slate-800">
                                        QRIS Dinamis Duitku Sandbox ({{ gatewayConfig.merchant_code || 'DS38621' }})
                                    </div>
                                    <img
                                        :src="generateQrSvgDataUrl(duitkuInvoiceData.qr_string)"
                                        alt="QRIS Duitku Sandbox"
                                        class="w-44 h-44 mx-auto rounded border border-slate-300 p-1 bg-white"
                                    />
                                    <button
                                        type="button"
                                        @click="copyDuitkuCode(duitkuInvoiceData.qr_id, 'Reference QRIS')"
                                        class="px-3 py-1 rounded border border-slate-300 bg-white text-slate-700 text-[11px] font-semibold"
                                    >
                                        📋 Copy Reference: {{ duitkuInvoiceData.qr_id }}
                                    </button>
                                </div>

                                <div
                                    v-if="duitkuInvoiceData?.qr_id"
                                    class="text-[11px] font-mono text-slate-600 bg-slate-50 p-2 rounded border border-slate-200 text-left space-y-0.5"
                                >
                                    <div class="flex justify-between">
                                        <span>Reference Duitku:</span>
                                        <strong class="text-slate-900 select-all">{{ duitkuInvoiceData.qr_id }}</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Merchant Order ID:</span>
                                        <strong class="text-slate-900 select-all">{{ duitkuInvoiceData.reference_id }}</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Tagihan Asli:</span>
                                        <strong class="text-slate-900">{{ formatRupiah(pendingInvoice.total_belanja) }}</strong>
                                    </div>
                                </div>

                                <div
                                    v-if="copiedDuitkuLabel"
                                    class="py-1.5 px-3 rounded bg-emerald-50 border border-emerald-300 text-emerald-800 text-xs font-semibold"
                                >
                                    ✓ {{ copiedDuitkuLabel }}
                                </div>

                                <div v-if="duitkuInvoiceData?.payment_url" class="space-y-2 pt-1">
                                    <div class="flex items-center justify-center gap-1.5 py-1.5 px-2.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-ping"></span>
                                        <span>AUTO-CHECK SANDBOX AKTIF (Otomatis Lunas &amp; Buka Struk tiap 3 detik)</span>
                                    </div>
                                    <a
                                        :href="duitkuInvoiceData.payment_url"
                                        target="_blank"
                                        class="block w-full py-2 px-3 rounded-md bg-[#1E3A5F] text-white text-xs font-semibold hover:bg-[#162C49]"
                                    >
                                        ↗ 1. Buka Simulator Pembayaran Duitku Sandbox
                                    </a>
                                    <button
                                        type="button"
                                        :disabled="checkingDuitkuStatus"
                                        @click="checkMemberDuitkuStatus(false)"
                                        class="w-full py-2 px-3 rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 text-xs font-semibold"
                                    >
                                        {{ checkingDuitkuStatus ? 'Mengecek ke Server Duitku...' : '🔄 Cek Manual Sekarang' }}
                                    </button>
                                </div>

                                <div
                                    v-if="duitkuCheckMessage"
                                    class="p-2 rounded border border-slate-300 bg-slate-50 text-[11px] font-medium text-slate-700"
                                >
                                    {{ duitkuCheckMessage }}
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end pt-1">
                            <button
                                type="button"
                                @click="showPendingPaymentModal = false"
                                class="px-3 py-1.5 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Jika Metode Pembayaran adalah Bayar Tunai di Kasir -->
                <div v-else class="space-y-3">
                    <div class="p-4 rounded-md border border-slate-300 bg-white text-center space-y-2">
                        <div class="text-xs font-semibold text-slate-800">
                            Tunjukkan Kode Booking Ini ke Kasir Toko
                        </div>
                        <img
                            :src="generateQrSvgDataUrl(pendingInvoice.kode_transaksi)"
                            :alt="pendingInvoice.kode_transaksi"
                            class="w-32 h-32 mx-auto border border-slate-300 p-1 rounded bg-white"
                        />
                        <div class="font-mono text-base font-bold text-slate-900">
                            {{ pendingInvoice.kode_transaksi }}
                        </div>
                        <p class="text-[11px] text-slate-600">
                            Pesanan Anda belum lunas. Silakan lakukan pembayaran tunai sebesar
                            <strong class="font-mono text-slate-900">{{ formatRupiah(pendingInvoice.total_belanja) }}</strong>
                            di meja Kasir. Setelah Kasir menekan tombol <strong>Konfirmasi Lunas</strong>, poin member akan masuk dan struk dapat dicetak.
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            @click="showPendingPaymentModal = false"
                            class="px-4 py-2 rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-semibold"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Struk Resmi HANYA Bisa Dibuka Jika Status === 'selesai' -->
        <ReceiptModal
            :show="showReceiptModal"
            :receipt="selectedReceipt"
            :tunai="Number(selectedReceipt?.nominal_bayar || selectedReceipt?.total_belanja || 0)"
            :kembalian="Number(selectedReceipt?.kembalian || 0)"
            @new-transaction="showReceiptModal = false"
            @close="showReceiptModal = false"
        />
    </AdminLayout>
</template>
