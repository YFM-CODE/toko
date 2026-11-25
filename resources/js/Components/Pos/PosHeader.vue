<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useTheme } from '@/lib/useTheme';

const props = defineProps({
    kodeTransaksi: {
        type: String,
        default: 'TRX-0001',
    },
    cartCount: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const { themeState, toggleMode } = useTheme();
const currentDateTime = ref('');
const showLogoutModal = ref(false);
let clockTimer = null;

const updateClock = () => {
    const now = new Date();
    const tgl = now.toLocaleDateString('id-ID', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
    const jam = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    currentDateTime.value = `${tgl} | ${jam}`;
};

onMounted(() => {
    updateClock();
    clockTimer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (clockTimer) {
        clearInterval(clockTimer);
    }
});

const handleLogoutClick = () => {
    if (props.cartCount > 0) {
        showLogoutModal.value = true;
    } else {
        confirmLogout();
    }
};

const confirmLogout = () => {
    showLogoutModal.value = false;
    router.post('/logout');
};
</script>

<template>
    <header
        class="px-4 py-2 bg-[#1E3A5F] border-b border-[#162C49] flex flex-wrap items-center justify-between gap-3 text-xs select-none shrink-0 text-white no-print"
    >
        <!-- Kiri: Logo + Identitas Toko, Nomor Struk & Kasir -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <ApplicationLogo variant="color" :size="28" />
                <span class="font-bold tracking-tight uppercase text-sm">
                    TOKO RITEL MANDIRI
                </span>
            </div>

            <div class="px-2.5 py-1 bg-[#162C49] border border-white/20 rounded text-slate-200 font-mono">
                <span>NO. STRUK:</span>
                <span class="ml-1.5 font-bold text-white tabular-nums">
                    {{ kodeTransaksi }}
                </span>
            </div>

            <div class="hidden md:flex items-center gap-2 text-slate-200">
                <span>Kasir:</span>
                <Link
                    href="/profil"
                    class="font-semibold text-white hover:underline"
                    title="Lihat Profil Akun"
                >
                    {{ page.props.auth?.user?.nama_lengkap || page.props.auth?.user?.name || 'Kasir' }}
                </Link>
                <span class="text-slate-300 font-mono text-[11px]">• Shift Aktif</span>
            </div>
        </div>

        <!-- Kanan: Jam, Utilitas Netral, & Keluar -->
        <div class="flex items-center gap-2">
            <span class="tabular-nums font-mono text-slate-200 hidden lg:inline px-2.5 py-1 bg-[#162C49] border border-white/15 rounded">
                {{ currentDateTime }}
            </span>

            <Link
                href="/laporan"
                class="px-3 py-1.5 bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 rounded-md font-medium transition"
            >
                Rekap Harian
            </Link>

            <button
                type="button"
                @click="toggleMode"
                class="px-3 py-1.5 bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 rounded-md font-medium transition"
            >
                {{ themeState.mode === 'dark' ? 'Mode: Gelap' : 'Mode: Terang' }}
            </button>

            <button
                type="button"
                @click="handleLogoutClick"
                class="px-3 py-1.5 bg-[#B91C1C] hover:bg-red-800 text-white font-medium rounded-md transition"
            >
                Keluar
            </button>
        </div>

        <!-- Modal Konfirmasi Logout -->
        <div
            v-if="showLogoutModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        >
            <div class="w-full max-w-md p-5 rounded-md bg-white text-slate-900 border border-slate-300 shadow-xl">
                <h3 class="text-sm font-bold text-[#B91C1C] mb-2">
                    Konfirmasi Keluar Sesi Kasir
                </h3>
                <p class="text-xs text-slate-600 leading-relaxed mb-4">
                    Keranjang masih berisi <strong class="font-bold text-slate-900">{{ cartCount }} item</strong>. Yakin ingin keluar dan membatalkan transaksi berjalan?
                </p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="showLogoutModal = false"
                        class="px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-md"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmLogout"
                        class="px-3 py-1.5 text-xs font-medium bg-[#B91C1C] hover:bg-red-800 text-white rounded-md"
                    >
                        Ya, Keluar
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
