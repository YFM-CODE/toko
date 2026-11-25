<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    useTheme,
    formatRupiah,
    getProductImage,
    handleProductImageError,
    generateQrSvgDataUrl,
} from '@/lib/useTheme';

const props = defineProps({
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    userRole: {
        type: String,
        default: null,
    },
    kategoriList: {
        type: Array,
        default: () => [],
    },
    produkUnggulan: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_produk: 0,
            total_kategori: 0,
            total_member: 0,
        }),
    },
});

const { themeState, toggleMode } = useTheme();
const isDark = computed(() => themeState.mode === 'dark');

const mobileMenuOpen = ref(false);
const selectedKategoriId = ref('all');
const searchProduk = ref('');
const openFaqIndex = ref(0);

const filteredProduk = computed(() => {
    const q = searchProduk.value.trim().toLowerCase();
    return props.produkUnggulan.filter((p) => {
        const matchKategori =
            selectedKategoriId.value === 'all' ||
            Number(p.id_kategori) === Number(selectedKategoriId.value);
        const matchSearch =
            !q ||
            (p.nama_produk || '').toLowerCase().includes(q) ||
            (p.kode_produk || '').toLowerCase().includes(q);
        return matchKategori && matchSearch;
    });
});

const dashboardHref = computed(() => {
    if (props.userRole === 'kasir') {
        return '/pos';
    }
    if (props.userRole === 'admin') {
        return '/admin/dashboard';
    }
    if (props.userRole === 'owner') {
        return '/laporan';
    }
    if (props.userRole === 'pelanggan') {
        return '/member/dashboard';
    }
    return '/dashboard';
});

const tierCards = [
    {
        name: 'Member Reguler',
        subtitle: 'Untuk pelanggan baru yang ingin langsung hemat setiap hari.',
        badge: 'GRATIS SELAMANYA',
        discount: '2%',
        requirement: 'Langsung aktif setelah daftar akun',
        featured: false,
        features: [
            'Bonus langsung +10 Poin Member Baru',
            'Diskon otomatis 2% di setiap transaksi POS',
            'Kartu Member QR Digital & Tap Kartu RFID',
            'Akses Portal Riwayat & Struk Digital',
        ],
    },
    {
        name: 'Member Silver',
        subtitle: 'Pilihan favorit pelanggan rutin dengan potongan lebih besar.',
        badge: 'PALING POPULER',
        discount: '5%',
        requirement: 'Akumulasi belanja ≥ Rp 500.000',
        featured: true,
        features: [
            'Semua keunggulan Member Reguler',
            'Diskon otomatis 5% di setiap transaksi POS',
            'Kenaikan Tier otomatis tanpa daftar ulang',
            'Tukar Poin (1 Poin = Rp 100) & Saldo RFID',
        ],
    },
    {
        name: 'Member Gold',
        subtitle: 'Keuntungan maksimal untuk pelanggan setia & belanja elektronik.',
        badge: 'PRIORITAS VIP',
        discount: '10%',
        requirement: 'Akumulasi belanja ≥ Rp 1.000.000',
        featured: false,
        features: [
            'Semua keunggulan Member Silver',
            'Diskon maksimal 10% di setiap transaksi',
            'Prioritas promo produk elektronik & ritel',
            'Arsip struk digital & laporan poin real-time',
        ],
    },
];

const faqs = [
    {
        q: 'Bagaimana cara mendaftar menjadi Member dan mendapatkan diskon langsung?',
        a: 'Klik tombol "Daftar Member" di bagian atas, isi data singkat Anda, dan akun langsung aktif dengan bonus +10 Poin. Jika memiliki kartu RFID fisik, Anda juga dapat langsung menautkannya saat registrasi.',
    },
    {
        q: 'Metode pembayaran apa saja yang didukung di kasir?',
        a: 'Kasir kami menerima Tunai (Cash), QRIS Resmi GoPay Merchant (YAZID, ELEKTRONIK) dari seluruh aplikasi bank & e-wallet (BRI, BCA, Mandiri, BNI, DANA, GoPay, OVO, ShopeePay), Scan QR HP Pembeli, serta Saldo Kartu RFID Member.',
    },
    {
        q: 'Bagaimana cara kerja Poin Loyalitas dan kenaikan Tier?',
        a: 'Setiap kelipatan belanja Rp 10.000 sebagai Member otomatis menambah +1 Poin (senilai Rp 100 potongan belanja). Akumulasi belanja Rp 500.000 otomatis naik ke Tier Silver (5%) dan Rp 1.000.000 naik ke Tier Gold (10%).',
    },
    {
        q: 'Apakah transaksi kasir langsung tercatat di Portal Member?',
        a: 'Ya. Setiap transaksi yang selesai langsung mencetak struk thermal 80mm/58mm dan otomatis tersimpan ke riwayat transaksi pada Portal Member Anda untuk dicek atau dicetak ulang kapan saja.',
    },
];

const toggleFaq = (idx) => {
    openFaqIndex.value = openFaqIndex.value === idx ? -1 : idx;
};
</script>

<template>
    <Head title="YAZID ELEKTRONIK • Sistem Ritel Modern, Member RFID & QRIS" />

    <div
        :class="{ dark: isDark }"
        class="min-h-screen flex flex-col overflow-x-hidden bg-white text-[#1D1E20] dark:bg-[#0F111A] dark:text-[#F8FAFC] transition-colors duration-200 font-sans antialiased"
    >
        <!-- 1. STICKY GLASSMORPHISM NAVBAR -->
        <header
            class="sticky top-0 z-50 h-16 sm:h-20 bg-white/85 dark:bg-[#0F111A]/85 backdrop-blur-md border-b border-[#E2E5F1] dark:border-[#2A2F45]"
        >
            <div class="max-w-7xl mx-auto h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
                <!-- Left Brand -->
                <Link href="/" class="flex items-center gap-3 shrink-0 group">
                    <div class="w-10 h-10 rounded-xl bg-[#673DE6] dark:bg-[#7C5CFF] flex items-center justify-center text-white shadow-md shadow-[#673DE6]/25">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <div class="font-bold text-base sm:text-lg tracking-tight text-[#2F1C6A] dark:text-white leading-none">
                            YAZID ELEKTRONIK
                        </div>
                        <div class="text-[11px] font-medium text-[#525866] dark:text-[#94A3B8] mt-0.5">
                            Toko Ritel Mandiri • POS &amp; Member
                        </div>
                    </div>
                </Link>

                <!-- Center Desktop Nav -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-[#1D1E20] dark:text-[#F8FAFC]">
                    <a href="#tier-member" class="hover:text-[#673DE6] dark:hover:text-[#7C5CFF] transition-colors">
                        Tier Member
                    </a>
                    <a href="#katalog" class="hover:text-[#673DE6] dark:hover:text-[#7C5CFF] transition-colors">
                        Katalog Produk
                    </a>
                    <a href="#keunggulan" class="hover:text-[#673DE6] dark:hover:text-[#7C5CFF] transition-colors">
                        Metode Bayar
                    </a>
                    <a href="#faq" class="hover:text-[#673DE6] dark:hover:text-[#7C5CFF] transition-colors">
                        FAQ
                    </a>
                </nav>

                <!-- Right Actions -->
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <button
                        type="button"
                        @click="toggleMode"
                        class="group inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-[#E2E5F1] dark:border-[#2A2F45] bg-[#F8F9FC] dark:bg-[#1B1F2E] text-[#1D1E20] dark:text-[#F8FAFC] hover:border-[#673DE6] dark:hover:border-[#7C5CFF] transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6]"
                        :title="isDark ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap'"
                    >
                        <!-- Ikon SVG Monokrom Matahari (Light) / Bulan (Dark) dengan animasi rotasi -->
                        <svg
                            v-if="isDark"
                            class="w-4 h-4 transition-transform duration-300 group-hover:rotate-45"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364l-1.414-1.414M7.05 7.05L5.636 5.636m12.728 0l-1.414 1.414M7.05 16.95l-1.414 1.414M12 8a4 4 0 100 8 4 4 0 000-8z" />
                        </svg>
                        <svg
                            v-else
                            class="w-4 h-4 transition-transform duration-300 group-hover:-rotate-12"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                        </svg>
                        <span class="hidden lg:inline text-xs font-semibold">
                            {{ isDark ? 'Terang' : 'Gelap' }}
                        </span>
                    </button>

                    <template v-if="isAuthenticated">
                        <Link
                            :href="dashboardHref"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#673DE6] hover:bg-[#5025D1] shadow-md shadow-[#673DE6]/25 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6] focus-visible:ring-offset-2"
                        >
                            Buka Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="hidden sm:inline-flex px-4 py-2.5 rounded-xl text-sm font-semibold border border-[#E2E5F1] dark:border-[#2A2F45] hover:border-[#673DE6] dark:hover:border-[#7C5CFF] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6]"
                        >
                            Masuk
                        </Link>
                        <Link
                            href="/register"
                            class="hidden sm:inline-flex px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#673DE6] hover:bg-[#5025D1] shadow-md shadow-[#673DE6]/25 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6] focus-visible:ring-offset-2"
                        >
                            Daftar Member
                        </Link>
                    </template>

                    <button
                        type="button"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="md:hidden p-2.5 rounded-xl border border-[#E2E5F1] dark:border-[#2A2F45] text-sm font-semibold"
                        aria-label="Toggle Menu"
                    >
                        {{ mobileMenuOpen ? '✕' : '☰' }}
                    </button>
                </div>
            </div>

            <!-- Mobile Drawer -->
            <div
                v-if="mobileMenuOpen"
                class="md:hidden bg-white dark:bg-[#0F111A] border-b border-[#E2E5F1] dark:border-[#2A2F45] px-4 py-4 space-y-3 shadow-lg"
            >
                <a href="#tier-member" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-medium">
                    Tier Member
                </a>
                <a href="#katalog" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-medium">
                    Katalog Produk
                </a>
                <a href="#keunggulan" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-medium">
                    Metode Bayar
                </a>
                <a href="#faq" @click="mobileMenuOpen = false" class="block py-1.5 text-sm font-medium">
                    FAQ
                </a>
                <div v-if="!isAuthenticated" class="pt-3 border-t border-[#E2E5F1] dark:border-[#2A2F45] grid grid-cols-2 gap-2.5">
                    <Link
                        href="/login"
                        class="py-2.5 text-center rounded-xl text-sm font-semibold border border-[#E2E5F1] dark:border-[#2A2F45]"
                    >
                        Masuk
                    </Link>
                    <Link
                        href="/register"
                        class="py-2.5 text-center rounded-xl text-sm font-semibold text-white bg-[#673DE6]"
                    >
                        Daftar Member
                    </Link>
                </div>
            </div>
        </header>

        <!-- 2. HERO SECTION (SPLIT 2 KOLOM ALA HOSTINGER) -->
        <section class="py-12 sm:py-16 lg:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <!-- Left Copy -->
                <div class="lg:col-span-6 space-y-6 animate-fade-in-up">
                    <div
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider bg-[#F4F5FF] text-[#673DE6] dark:bg-[#673DE6]/15 dark:text-[#A78BFA]"
                    >
                        <span class="w-2 h-2 rounded-full bg-[#FC5185]"></span>
                        <span>Bonus Member Baru +10 Poin Langsung Aktif</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] text-[#2F1C6A] dark:text-[#E2DCFF]">
                        Belanja Lebih Hemat dengan Kartu Member &amp; QRIS Instan.
                    </h1>

                    <p class="text-base sm:text-lg leading-relaxed text-[#525866] dark:text-[#94A3B8]">
                        Solusi belanja ritel &amp; elektronik modern di <strong>Yazid Elektronik</strong>. Dapatkan diskon otomatis hingga <strong>10%</strong>, kumpulkan poin di setiap transaksi, dan bayar cepat dengan QRIS atau Tap Kartu RFID.
                    </p>

                    <ul class="space-y-3 text-sm sm:text-[15px] font-medium">
                        <li class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-[#00B090]/15 text-[#00B090] dark:text-[#10B981] flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span>Diskon otomatis Member hingga <strong>10%</strong> tanpa kode promo</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-[#00B090]/15 text-[#00B090] dark:text-[#10B981] flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span>Bayar mudah via <strong>QRIS GoPay Merchant</strong>, BRI, BCA, Mandiri, DANA, &amp; Saldo RFID</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-[#00B090]/15 text-[#00B090] dark:text-[#10B981] flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span>Pantau poin loyalitas &amp; arsip struk digital langsung dari Portal Member</span>
                        </li>
                    </ul>

                    <div class="flex flex-wrap gap-3.5 pt-2">
                        <Link
                            :href="isAuthenticated ? dashboardHref : '/register'"
                            class="w-full sm:w-auto text-center px-7 py-3.5 rounded-xl font-semibold text-white bg-[#673DE6] hover:bg-[#5025D1] shadow-md shadow-[#673DE6]/25 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6] focus-visible:ring-offset-2"
                        >
                            {{ isAuthenticated ? 'Masuk ke Dashboard Saya' : 'Daftar Member Gratis' }}
                        </Link>
                        <Link
                            href="/login"
                            class="w-full sm:w-auto text-center px-7 py-3.5 rounded-xl font-semibold bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] hover:border-[#673DE6] dark:hover:border-[#7C5CFF] transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6]"
                        >
                            Masuk / Tap Kartu RFID
                        </Link>
                    </div>

                    <p class="text-xs text-[#525866] dark:text-[#94A3B8]">
                        Gratis biaya pendaftaran • Bonus +10 Poin langsung aktif saat transaksi pertama
                    </p>
                </div>

                <!-- Right Showcase Visual -->
                <div class="lg:col-span-6 relative">
                    <div
                        class="rounded-3xl p-6 sm:p-8 bg-gradient-to-br from-[#673DE6]/10 via-[#F4F5FF] to-transparent dark:from-[#673DE6]/20 dark:via-[#1C1830] dark:to-transparent border border-[#673DE6]/15 relative transition-all duration-300"
                    >
                        <!-- Floating Stat Pill Top -->
                        <div
                            class="hidden sm:inline-flex items-center gap-2 px-3.5 py-2 rounded-full bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] shadow-lg text-xs font-semibold mb-5 animate-float-slow"
                        >
                            <span class="w-2.5 h-2.5 rounded-full bg-[#00B090] animate-pulse"></span>
                            <span>1-Tap RFID &amp; QRIS GoPay Merchant Aktif</span>
                        </div>

                        <!-- Digital Member Card Showcase -->
                        <div class="rounded-2xl bg-gradient-to-br from-[#2F1C6A] to-[#673DE6] text-white p-6 shadow-xl shadow-[#673DE6]/20 space-y-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] uppercase tracking-wider opacity-80 font-semibold">
                                        Kartu Member Digital
                                    </div>
                                    <div class="text-xl font-bold mt-0.5">YAZID ELEKTRONIK</div>
                                </div>
                                <span class="px-3 py-1 rounded-full bg-white/15 backdrop-blur-sm text-xs font-semibold">
                                    GOLD • 10% OFF
                                </span>
                            </div>

                            <div class="bg-white text-[#1D1E20] rounded-xl p-4 flex items-center gap-4">
                                <img
                                    :src="generateQrSvgDataUrl('RFID-PEL-001')"
                                    alt="QR Member Digital"
                                    class="w-20 h-20 rounded-lg border border-[#E2E5F1] p-1 shrink-0"
                                />
                                <div class="min-w-0 space-y-1">
                                    <div class="text-[11px] font-semibold uppercase text-[#525866]">
                                        UID Kartu RFID Member
                                    </div>
                                    <div class="font-mono text-sm font-bold text-[#673DE6]">
                                        RFID-PEL-001
                                    </div>
                                    <div class="text-xs text-[#525866]">
                                        Poin: <strong class="text-[#1D1E20]">120 pts</strong> • Saldo: <strong class="text-[#00B090]">Rp 250.000</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs opacity-90 pt-1">
                                <span>NMID QRIS: ID1026647000219</span>
                                <span class="font-semibold">1 Poin = Rp 100</span>
                            </div>
                        </div>

                        <!-- Floating Bottom Metrics -->
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-4 shadow-sm">
                                <div class="text-xs text-[#525866] dark:text-[#94A3B8]">Metode Pembayaran</div>
                                <div class="text-sm font-bold mt-1">QRIS &amp; Kartu RFID</div>
                            </div>
                            <div class="rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-4 shadow-sm">
                                <div class="text-xs text-[#525866] dark:text-[#94A3B8]">Cetak Struk Kasir</div>
                                <div class="text-sm font-bold mt-1 text-[#00B090]">Thermal 80mm / Digital</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. TRUST & METRICS BAR -->
        <section class="bg-[#F8F9FC] dark:bg-[#161925] border-y border-[#E2E5F1] dark:border-[#2A2F45]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-1 sm:grid-cols-3 gap-6 text-center sm:text-left">
                <div>
                    <div class="text-3xl font-extrabold tracking-tight text-[#2F1C6A] dark:text-white font-mono">
                        {{ stats.total_produk }}+
                    </div>
                    <div class="text-sm text-[#525866] dark:text-[#94A3B8] mt-1">
                        Produk Ritel &amp; Elektronik Terdaftar
                    </div>
                </div>
                <div>
                    <div class="text-3xl font-extrabold tracking-tight text-[#2F1C6A] dark:text-white font-mono">
                        {{ stats.total_member }}+
                    </div>
                    <div class="text-sm text-[#525866] dark:text-[#94A3B8] mt-1">
                        Pelanggan Member Aktif &amp; Loyal
                    </div>
                </div>
                <div>
                    <div class="text-3xl font-extrabold tracking-tight text-[#673DE6] dark:text-[#7C5CFF] font-mono">
                        10% OFF
                    </div>
                    <div class="text-sm text-[#525866] dark:text-[#94A3B8] mt-1">
                        Hemat Otomatis untuk Member Gold
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. PRICING / TIER CARDS (CIRI KHAS HOSTINGER) -->
        <section id="tier-member" class="py-16 lg:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-12 space-y-3">
                    <span class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-[#F4F5FF] text-[#673DE6] dark:bg-[#1C1830] dark:text-[#A78BFA]">
                        Program Loyalitas Member
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight leading-tight text-[#2F1C6A] dark:text-[#E2DCFF]">
                        Pilih Tingkat Keuntungan Member Anda
                    </h2>
                    <p class="text-sm sm:text-[15px] leading-relaxed text-[#525866] dark:text-[#94A3B8]">
                        Pendaftaran gratis selamanya. Tingkatkan akumulasi belanja untuk membuka diskon otomatis yang lebih besar.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
                    <div
                        v-for="tier in tierCards"
                        :key="tier.name"
                        class="rounded-2xl flex flex-col overflow-hidden transition-all duration-200"
                        :class="
                            tier.featured
                                ? 'bg-white dark:bg-[#1B1F2E] border-2 border-[#673DE6] dark:border-[#7C5CFF] shadow-xl shadow-[#673DE6]/10 relative lg:-my-3'
                                : 'bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] shadow-sm hover:shadow-lg hover:-translate-y-1'
                        "
                    >
                        <!-- Top Banner for Featured Card -->
                        <div
                            v-if="tier.featured"
                            class="bg-[#673DE6] dark:bg-[#7C5CFF] text-white text-xs font-bold uppercase tracking-wider py-2 text-center"
                        >
                            {{ tier.badge }}
                        </div>

                        <div class="p-8 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-lg sm:text-xl font-bold text-[#2F1C6A] dark:text-white">
                                    {{ tier.name }}
                                </h3>
                                <span
                                    v-if="!tier.featured"
                                    class="px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wider bg-[#F4F5FF] text-[#673DE6] dark:bg-[#1C1830] dark:text-[#A78BFA]"
                                >
                                    {{ tier.badge }}
                                </span>
                            </div>

                            <p class="text-sm text-[#525866] dark:text-[#94A3B8] mt-2">
                                {{ tier.subtitle }}
                            </p>

                            <div class="mt-6">
                                <div class="text-xs font-semibold uppercase tracking-wider text-[#525866] dark:text-[#94A3B8]">
                                    Diskon Otomatis
                                </div>
                                <div class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#2F1C6A] dark:text-white mt-1">
                                    {{ tier.discount }}
                                    <span class="text-sm font-medium text-[#525866] dark:text-[#94A3B8]">/ transaksi</span>
                                </div>
                                <div class="text-xs font-medium text-[#673DE6] dark:text-[#A78BFA] mt-1.5">
                                    {{ tier.requirement }}
                                </div>
                            </div>

                            <div class="mt-6">
                                <Link
                                    :href="isAuthenticated ? dashboardHref : '/register'"
                                    class="block w-full py-3 rounded-xl text-center text-sm font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6]"
                                    :class="
                                        tier.featured
                                            ? 'bg-[#673DE6] hover:bg-[#5025D1] text-white shadow-md shadow-[#673DE6]/25'
                                            : 'bg-[#F4F5FF] hover:bg-[#673DE6] text-[#673DE6] hover:text-white dark:bg-[#1C1830] dark:text-[#A78BFA] dark:hover:bg-[#7C5CFF] dark:hover:text-white'
                                    "
                                >
                                    {{ isAuthenticated ? 'Lihat Status Saya' : 'Pilih ' + tier.name }}
                                </Link>
                            </div>

                            <div class="border-t border-[#E2E5F1] dark:border-[#2A2F45] my-6"></div>

                            <ul class="space-y-3 text-sm text-[#1D1E20] dark:text-[#F8FAFC]">
                                <li
                                    v-for="feat in tier.features"
                                    :key="feat"
                                    class="flex items-start gap-2.5"
                                >
                                    <span class="text-[#00B090] dark:text-[#10B981] font-bold shrink-0">✓</span>
                                    <span>{{ feat }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. KATALOG PRODUK INTERAKTIF -->
        <section id="katalog" class="py-16 lg:py-24 bg-[#F8F9FC] dark:bg-[#161925] border-y border-[#E2E5F1] dark:border-[#2A2F45]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                    <div class="space-y-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[#673DE6] dark:text-[#7C5CFF]">
                            Katalog Produk Live
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#2F1C6A] dark:text-[#E2DCFF]">
                            Stok Tersedia di Toko Hari Ini
                        </h2>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full lg:w-80">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#525866] dark:text-[#94A3B8]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="searchProduk"
                            type="text"
                            placeholder="Cari nama produk atau kode PLU..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] focus:outline-none focus:ring-2 focus:ring-[#673DE6] transition-all duration-200"
                        />
                    </div>
                </div>

                <!-- Scrollable Category Filter Pills -->
                <div class="flex gap-2 overflow-x-auto pb-2">
                    <button
                        type="button"
                        @click="selectedKategoriId = 'all'"
                        class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-200"
                        :class="
                            selectedKategoriId === 'all'
                                ? 'bg-[#673DE6] text-white shadow-sm'
                                : 'bg-white dark:bg-[#1B1F2E] text-[#525866] dark:text-[#94A3B8] border border-[#E2E5F1] dark:border-[#2A2F45] hover:border-[#673DE6]'
                        "
                    >
                        Semua Kategori ({{ stats.total_kategori }})
                    </button>
                    <button
                        v-for="kat in kategoriList"
                        :key="kat.id_kategori"
                        type="button"
                        @click="selectedKategoriId = kat.id_kategori"
                        class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-200"
                        :class="
                            Number(selectedKategoriId) === Number(kat.id_kategori)
                                ? 'bg-[#673DE6] text-white shadow-sm'
                                : 'bg-white dark:bg-[#1B1F2E] text-[#525866] dark:text-[#94A3B8] border border-[#E2E5F1] dark:border-[#2A2F45] hover:border-[#673DE6]'
                        "
                    >
                        {{ kat.nama_kategori }}
                    </button>
                </div>

                <!-- Product Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <div
                        v-for="p in filteredProduk"
                        :key="p.id_produk"
                        class="group rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-4 flex flex-col shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200"
                    >
                        <div class="aspect-[4/3] overflow-hidden rounded-xl bg-[#F4F5FF] dark:bg-[#161925] flex items-center justify-center">
                            <img
                                :src="getProductImage(p)"
                                :alt="p.nama_produk"
                                @error="(e) => handleProductImageError(e, p)"
                                class="max-w-full max-h-full object-contain p-3 group-hover:scale-105 transition-transform duration-300"
                            />
                        </div>

                        <div class="mt-4 flex-1 flex flex-col">
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#F4F5FF] text-[#673DE6] dark:bg-[#1C1830] dark:text-[#A78BFA]">
                                    {{ p.nama_kategori }}
                                </span>
                                <span class="text-[11px] font-semibold text-[#00B090] dark:text-[#10B981]">
                                    Stok: {{ p.stok }}
                                </span>
                            </div>

                            <h3 class="font-bold text-base text-[#1D1E20] dark:text-white mt-2.5 line-clamp-2">
                                {{ p.nama_produk }}
                            </h3>

                            <div class="text-xs font-mono text-[#525866] dark:text-[#94A3B8] mt-1">
                                SKU: {{ p.kode_produk }}
                            </div>

                            <div class="mt-auto pt-4 flex items-center justify-between gap-2 border-t border-[#E2E5F1] dark:border-[#2A2F45]">
                                <div>
                                    <span class="block text-lg font-extrabold text-[#2F1C6A] dark:text-[#E2DCFF]">
                                        {{ formatRupiah(p.harga) }}
                                    </span>
                                    <span class="text-[11px] font-medium text-[#525866] dark:text-[#94A3B8]">
                                        Hemat Member s/d 10%
                                    </span>
                                </div>
                                <Link
                                    :href="
                                        isAuthenticated
                                            ? userRole === 'pelanggan'
                                                ? `/member/dashboard?tab=belanja&buy=${p.id_produk}`
                                                : dashboardHref
                                            : `/register?buy=${p.id_produk}`
                                    "
                                    class="px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-[#673DE6] hover:bg-[#5025D1] shadow-sm shadow-[#673DE6]/20 transition-all duration-200 shrink-0"
                                >
                                    {{
                                        isAuthenticated
                                            ? userRole === 'pelanggan'
                                                ? '+ Beli Sekarang'
                                                : 'Buka POS'
                                            : 'Daftar & Beli'
                                    }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="filteredProduk.length === 0"
                        class="col-span-full rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-12 text-center text-sm text-[#525866] dark:text-[#94A3B8]"
                    >
                        Tidak ada produk yang cocok dengan pencarian Anda.
                    </div>
                </div>
            </div>
        </section>

        <!-- KEUNGGULAN PEMBAYARAN & TEKNOLOGI -->
        <section id="keunggulan" class="py-16 lg:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                <div class="text-center max-w-2xl mx-auto space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#673DE6] dark:text-[#7C5CFF]">
                        Pengalaman Kasir Modern
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#2F1C6A] dark:text-[#E2DCFF]">
                        Transaksi Cepat, Akurat, &amp; Transparan
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="group rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-7 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200 space-y-3">
                        <div class="w-11 h-11 rounded-xl bg-[#F4F5FF] dark:bg-[#1C1830] text-[#1D1E20] dark:text-[#F8FAFC] flex items-center justify-center group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 3h2m4 0h-2m0-3v6" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold">QRIS GoPay Merchant</h3>
                        <p class="text-sm leading-relaxed text-[#525866] dark:text-[#94A3B8]">
                            Terima pembayaran QRIS resmi <strong>YAZID, ELEKTRONIK</strong> dari seluruh aplikasi Bank dan E-Wallet maupun scan QR HP pembeli secara otomatis.
                        </p>
                    </div>

                    <div class="group rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-7 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200 space-y-3">
                        <div class="w-11 h-11 rounded-xl bg-[#F4F5FF] dark:bg-[#1C1830] text-[#1D1E20] dark:text-[#F8FAFC] flex items-center justify-center group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 3h18M7 15h4" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold">Kartu Member RFID 1-Tap</h3>
                        <p class="text-sm leading-relaxed text-[#525866] dark:text-[#94A3B8]">
                            Cukup tempelkan kartu RFID di kasir untuk langsung mengaktifkan diskon Tier Member, menukar poin, atau membayar menggunakan saldo deposit.
                        </p>
                    </div>

                    <div class="group rounded-2xl bg-white dark:bg-[#1B1F2E] border border-[#E2E5F1] dark:border-[#2A2F45] p-7 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200 space-y-3">
                        <div class="w-11 h-11 rounded-xl bg-[#F4F5FF] dark:bg-[#1C1830] text-[#1D1E20] dark:text-[#F8FAFC] flex items-center justify-center group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold">Struk Thermal &amp; Digital</h3>
                        <p class="text-sm leading-relaxed text-[#525866] dark:text-[#94A3B8]">
                            Setiap belanja langsung tercetak rapi pada kertas thermal 80mm/58mm dan arsip struk digitalnya selalu tersedia di Portal Member Anda.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. FAQ ACCORDION -->
        <section id="faq" class="py-16 lg:py-24 bg-[#F8F9FC] dark:bg-[#161925] border-t border-[#E2E5F1] dark:border-[#2A2F45]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <div class="text-center mb-10 space-y-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#673DE6] dark:text-[#7C5CFF]">
                        Pusat Bantuan
                    </span>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-[#2F1C6A] dark:text-[#E2DCFF]">
                        Pertanyaan yang Sering Diajukan
                    </h2>
                </div>

                <div
                    v-for="(item, idx) in faqs"
                    :key="idx"
                    class="border border-[#E2E5F1] dark:border-[#2A2F45] rounded-xl bg-white dark:bg-[#1B1F2E] overflow-hidden"
                >
                    <button
                        type="button"
                        @click="toggleFaq(idx)"
                        class="w-full flex items-center justify-between p-5 text-left font-semibold text-sm sm:text-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#673DE6]"
                    >
                        <span>{{ item.q }}</span>
                        <svg
                            class="w-5 h-5 shrink-0 text-[#525866] dark:text-[#94A3B8] transition-transform duration-200"
                            :class="{ 'rotate-180 text-[#673DE6] dark:text-[#7C5CFF]': openFaqIndex === idx }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div
                        v-if="openFaqIndex === idx"
                        class="px-5 pb-5 text-sm leading-relaxed text-[#525866] dark:text-[#94A3B8]"
                    >
                        {{ item.a }}
                    </div>
                </div>
            </div>
        </section>

        <!-- 7. FOOTER TERSTRUKTUR -->
        <footer class="mt-auto bg-[#F4F5FF] dark:bg-[#0B0D14] border-t border-[#E2E5F1] dark:border-[#2A2F45]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 py-14">
                    <div class="lg:col-span-2 space-y-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-[#673DE6] flex items-center justify-center text-white font-bold">
                                Y
                            </div>
                            <span class="font-bold text-lg text-[#2F1C6A] dark:text-white">
                                YAZID ELEKTRONIK • TOKO RITEL MANDIRI
                            </span>
                        </div>
                        <p class="text-sm text-[#525866] dark:text-[#94A3B8] max-w-md leading-relaxed">
                            Jl. Raya Ponorogo No. 10 • Telp: (0352) 481234. Sistem informasi penjualan ritel modern dengan dukungan POS Kasir, Member RFID, Poin Loyalitas, dan QRIS.
                        </p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="font-bold text-[#2F1C6A] dark:text-white">Navigasi</div>
                        <ul class="space-y-2 text-[#525866] dark:text-[#94A3B8]">
                            <li><a href="#tier-member" class="hover:text-[#673DE6] transition-colors">Tier Member</a></li>
                            <li><a href="#katalog" class="hover:text-[#673DE6] transition-colors">Katalog Produk</a></li>
                            <li><a href="#keunggulan" class="hover:text-[#673DE6] transition-colors">Metode Pembayaran</a></li>
                            <li><a href="#faq" class="hover:text-[#673DE6] transition-colors">FAQ</a></li>
                        </ul>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="font-bold text-[#2F1C6A] dark:text-white">Akses Akun</div>
                        <ul class="space-y-2 text-[#525866] dark:text-[#94A3B8]">
                            <li><Link href="/register" class="hover:text-[#673DE6] transition-colors">Daftar Member Baru</Link></li>
                            <li><Link href="/login" class="hover:text-[#673DE6] transition-colors">Login / Tap RFID</Link></li>
                            <li v-if="isAuthenticated">
                                <Link :href="dashboardHref" class="hover:text-[#673DE6] transition-colors">Dashboard Saya</Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-[#E2E5F1] dark:border-[#2A2F45] py-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[#525866] dark:text-[#94A3B8]">
                    <div>&copy; {{ new Date().getFullYear() }} Yazid Elektronik • Toko Ritel Mandiri.</div>
                    <div>Dibangun dengan arsitektur bersih Laravel + Inertia + Vue 3 (/yz-1)</div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes floatSlow {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-6px);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-float-slow {
    animation: floatSlow 4s ease-in-out infinite;
}

.animate-fade-in-up {
    animation: fadeInUp 0.55s ease-out both;
}
</style>
