<script setup>
import PosHeader from '@/Components/Pos/PosHeader.vue';
import { useTheme } from '@/lib/useTheme';

defineProps({
    kodeTransaksi: {
        type: String,
        default: 'TRX-0001',
    },
    cartCount: {
        type: Number,
        default: 0,
    },
});

const { themeState } = useTheme();
</script>

<template>
    <div
        :class="{ dark: themeState.mode === 'dark' }"
        class="h-screen w-screen flex flex-col overflow-hidden select-none bg-[#F1F5F9] text-[#0F172A] dark:bg-slate-950 dark:text-slate-100 font-sans"
    >
        <PosHeader :kode-transaksi="kodeTransaksi" :cart-count="cartCount" />

        <main class="flex-1 min-h-0 flex flex-col overflow-hidden">
            <slot />
        </main>

        <!-- Footer Pintasan Keyboard (Referensi Pasif Netral Tanpa Warna Mencolok) -->
        <footer
            class="px-4 py-1.5 border-t border-slate-700 bg-slate-800 text-slate-200 text-[11px] font-mono flex flex-wrap items-center justify-between gap-2 shrink-0 no-print"
        >
            <div class="flex flex-wrap items-center gap-3">
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F1</kbd>
                    Cari Barang
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F2</kbd>
                    Ubah Qty
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F3</kbd>
                    Pelanggan
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F4</kbd>
                    Hapus Item
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F5</kbd>
                    Scan QR
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F8</kbd>
                    Tunai
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F9</kbd>
                    Scan QR Bayar
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">F10</kbd>
                    QRIS Toko
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">↑/↓</kbd>
                    Pilih Baris
                </span>
                <span>
                    <kbd class="px-1.5 py-0.5 rounded bg-slate-700 text-slate-100 border border-slate-500 font-semibold">ESC</kbd>
                    Reset Fokus
                </span>
            </div>
            <div class="font-semibold text-slate-400 hidden sm:block">
                TOKOKU • POS MINIMARKET
            </div>
        </footer>
    </div>
</template>
