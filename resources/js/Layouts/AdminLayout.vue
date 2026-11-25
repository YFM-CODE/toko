<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useTheme } from '@/lib/useTheme';

defineProps({
    title: {
        type: String,
        default: 'Panel Manajemen Toko',
    },
});

const page = usePage();
const { themeState, toggleMode } = useTheme();
const sidebarOpen = ref(false);

const user = computed(() => page.props.auth?.user || {});
const role = computed(() => user.value?.role || 'admin');

const navItems = computed(() => {
    const items = [];

    if (role.value === 'pelanggan') {
        items.push(
            { label: 'Portal & Belanja Member', href: '/member/dashboard', match: '/member' },
            { label: 'Profil & Password Akun', href: '/profil', match: '/profil' },
        );
        return items;
    }

    if (role.value === 'admin') {
        items.push(
            { label: 'Dashboard Admin', href: '/admin/dashboard', match: '/admin/dashboard' },
            { label: 'Master Kategori', href: '/admin/kategori', match: '/admin/kategori' },
            { label: 'Master Produk & Gambar', href: '/admin/produk', match: '/admin/produk' },
            { label: 'Data Member / Pelanggan', href: '/admin/pelanggan', match: '/admin/pelanggan' },
            { label: 'Manajemen Akun Staf', href: '/admin/users', match: '/admin/users' },
        );
    }

    if (role.value === 'kasir') {
        items.push({ label: 'Layar Kasir (POS)', href: '/pos', match: '/pos' });
    }

    items.push(
        { label: 'Riwayat & Struk Transaksi', href: '/transaksi', match: '/transaksi' },
        { label: 'Laporan & Analisis SQL', href: '/laporan', match: '/laporan' },
    );

    if (role.value === 'admin' || role.value === 'owner') {
        items.push({ label: 'Audit Log (tbl_log)', href: '/audit-log', match: '/audit-log' });
    }

    items.push({ label: 'Profil Akun', href: '/profil', match: '/profil' });

    return items;
});

const isActive = (matchPrefix) => {
    return page.url.startsWith(matchPrefix);
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div
        class="min-h-screen flex flex-col md:flex-row bg-[#F1F5F9] text-[#0F172A] dark:bg-slate-950 dark:text-slate-100 toko-workspace"
        :class="{ dark: themeState.mode === 'dark' }"
    >
        <!-- Sidebar Netral & Bersih -->
        <aside
            class="w-full md:w-64 shrink-0 bg-white dark:bg-slate-900 border-b md:border-b-0 md:border-r border-slate-300 dark:border-slate-800 flex flex-col justify-between no-print"
        >
            <div>
                <!-- Brand Header Navy (#1E3A5F) -->
                <div class="p-4 bg-[#1E3A5F] border-b border-[#162C49] flex items-center justify-between text-white">
                    <div class="flex items-center gap-2.5">
                        <ApplicationLogo variant="color" :size="30" />
                        <div>
                            <div class="font-bold text-sm leading-tight">SISTEM TOKO</div>
                            <div class="text-[11px] uppercase font-mono text-slate-200">
                                Role: {{ role }}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        class="md:hidden px-2.5 py-1 border border-white/30 rounded text-xs font-mono font-semibold"
                    >
                        Menu
                    </button>
                </div>

                <!-- Navigation Links -->
                <nav
                    class="p-3 space-y-1"
                    :class="{ 'hidden md:block': !sidebarOpen }"
                >
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="block px-3 py-2 rounded-md text-sm font-medium transition"
                        :class="
                            isActive(item.match)
                                ? 'bg-[#1E3A5F] text-white font-semibold'
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <!-- Sidebar Footer Logout -->
            <div
                class="p-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                :class="{ 'hidden md:block': !sidebarOpen }"
            >
                <div class="mb-3 text-xs">
                    <div class="font-semibold text-slate-900 dark:text-white truncate">
                        {{ user.nama_lengkap || user.name }}
                    </div>
                    <div class="font-mono text-slate-500 truncate">
                        @{{ user.username }}
                    </div>
                </div>
                <button
                    type="button"
                    @click="logout"
                    class="w-full px-3 py-2 text-xs font-medium text-white bg-[#B91C1C] hover:bg-red-800 rounded-md transition"
                >
                    Keluar Sesi
                </button>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <header
                class="px-6 py-3 bg-white dark:bg-slate-900 border-b border-slate-300 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4 no-print"
            >
                <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ title }}
                </h1>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="toggleMode"
                        class="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 rounded-md transition"
                    >
                        {{ themeState.mode === 'dark' ? 'Mode: Gelap' : 'Mode: Terang' }}
                    </button>

                    <Link
                        href="/profil"
                        class="px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 rounded-md transition"
                    >
                        {{ user.nama_lengkap || user.name }} ({{ role.toUpperCase() }})
                    </Link>
                </div>
            </header>

            <!-- Flash Banners Tenang -->
            <div
                v-if="page.props.flash?.success"
                class="mx-6 mt-4 p-3 rounded-md text-xs font-medium no-print toko-banner-success"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="mx-6 mt-4 p-3 rounded-md text-xs font-medium no-print toko-banner-error"
            >
                {{ page.props.flash.error }}
            </div>

            <!-- Page Content -->
            <main class="p-6 flex-1 overflow-x-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
