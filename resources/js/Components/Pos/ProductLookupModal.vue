<script setup>
import { ref, computed } from 'vue';
import { useTheme, formatRupiah, getProductImage, handleProductImageError } from '@/lib/useTheme';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    produkList: {
        type: Array,
        default: () => [],
    },
    kategoriList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);
const { themeState } = useTheme();

const search = ref('');
const selectedKategori = ref('');

const filteredProduk = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.produkList.filter((item) => {
        const matchKategori =
            !selectedKategori.value || Number(item.id_kategori) === Number(selectedKategori.value);
        const matchSearch =
            !q ||
            (item.kode_produk || '').toLowerCase().includes(q) ||
            (item.nama_produk || '').toLowerCase().includes(q);

        return matchKategori && matchSearch;
    });
});

const chooseProduct = (produk) => {
    emit('select', produk);
    emit('close');
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4 font-mono"
    >
        <div
            class="w-full max-w-4xl rounded border-2 shadow-2xl overflow-hidden"
            :class="
                themeState.mode === 'dark'
                    ? 'bg-slate-900 border-slate-600 text-white'
                    : 'bg-white border-slate-500 text-slate-900'
            "
        >
            <!-- Header Solid -->
            <div
                class="px-4 py-3 flex items-center justify-between text-white font-bold"
                :style="{ backgroundColor: themeState.accentColor }"
            >
                <h3 class="text-sm uppercase tracking-wider">
                    [F1] Pencarian Barang / PLU (tbl_produk JOIN tbl_kategori)
                </h3>
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-2.5 py-0.5 text-xs font-bold bg-[#b91c1c] text-white rounded"
                >
                    TUTUP [ESC]
                </button>
            </div>

            <!-- Filter Bar Solid -->
            <div
                class="p-4 border-b-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs"
                :class="
                    themeState.mode === 'dark'
                        ? 'bg-slate-800 border-slate-700'
                        : 'bg-slate-100 border-slate-300'
                "
            >
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari kode PLU (PRD-001) atau nama produk..."
                    class="sm:col-span-2 px-3 py-2 rounded border-2 font-bold"
                    :class="
                        themeState.mode === 'dark'
                            ? 'bg-slate-950 border-slate-600 text-white'
                            : 'bg-[#fffde7] border-[#0b3c6f] text-slate-900'
                    "
                />
                <select
                    v-model="selectedKategori"
                    class="px-3 py-2 rounded border-2 font-bold"
                    :class="
                        themeState.mode === 'dark'
                            ? 'bg-slate-950 border-slate-600 text-white'
                            : 'bg-white border-slate-400 text-slate-900'
                    "
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

            <!-- Product Table with Image Thumbnail -->
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead
                        class="uppercase text-[11px] sticky top-0 border-b-2"
                        :class="
                            themeState.mode === 'dark'
                                ? 'bg-slate-800 border-slate-600 text-slate-200'
                                : 'bg-[#d0d7de] border-slate-400 text-slate-900'
                        "
                    >
                        <tr>
                            <th class="p-2.5 w-16 text-center">Foto</th>
                            <th class="p-2.5">Kode PLU</th>
                            <th class="p-2.5">Nama Barang</th>
                            <th class="p-2.5">Kategori</th>
                            <th class="p-2.5 text-right">Harga</th>
                            <th class="p-2.5 text-center">Stok</th>
                            <th class="p-2.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-400">
                        <tr
                            v-for="item in filteredProduk"
                            :key="item.id_produk"
                            class="transition"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'hover:bg-slate-800'
                                    : 'hover:bg-[#cce5ff]'
                            "
                        >
                            <td class="p-2 text-center">
                                <img
                                    :src="getProductImage(item)"
                                    :alt="item.nama_produk"
                                    @error="(e) => handleProductImageError(e, item)"
                                    class="w-11 h-11 object-contain border border-slate-400 bg-white mx-auto rounded-sm"
                                />
                            </td>
                            <td class="p-2.5 font-extrabold">{{ item.kode_produk }}</td>
                            <td class="p-2.5 font-bold">{{ item.nama_produk }}</td>
                            <td class="p-2.5">
                                {{ item.kategori?.nama_kategori || '-' }}
                            </td>
                            <td class="p-2.5 text-right tabular-nums font-extrabold">
                                {{ formatRupiah(item.harga) }}
                            </td>
                            <td class="p-2.5 text-center tabular-nums">
                                <span
                                    class="px-2 py-0.5 rounded-sm text-[11px] font-extrabold text-white"
                                    :class="
                                        item.stok <= 0
                                            ? 'bg-[#b91c1c]'
                                            : item.stok <= 5
                                              ? 'bg-[#c2410c]'
                                              : 'bg-[#15803d]'
                                    "
                                >
                                    {{ item.stok }}
                                </span>
                            </td>
                            <td class="p-2.5 text-center">
                                <button
                                    type="button"
                                    :disabled="item.stok <= 0"
                                    @click="chooseProduct(item)"
                                    class="px-3 py-1 text-[11px] font-extrabold text-white rounded-sm disabled:opacity-40"
                                    :style="{ backgroundColor: themeState.accentColor }"
                                >
                                    + PILIH
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredProduk.length === 0">
                            <td colspan="7" class="p-6 text-center font-bold">
                                Produk tidak ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
