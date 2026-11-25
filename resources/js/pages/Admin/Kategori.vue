<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme } from '@/lib/useTheme';

const props = defineProps({
    kategori: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const { themeState } = useTheme();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const search = ref(props.filters.search || '');
const showModal = ref(false);
const editingId = ref(null);

const form = useForm({
    nama_kategori: '',
    deskripsi: '',
});

const applySearch = () => {
    router.get('/admin/kategori', { search: search.value }, { preserveState: true, replace: true });
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (item) => {
    editingId.value = item.id_kategori;
    form.nama_kategori = item.nama_kategori;
    form.deskripsi = item.deskripsi || '';
    form.clearErrors();
    showModal.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put(`/admin/kategori/${editingId.value}`, {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post('/admin/kategori', {
            onSuccess: () => (showModal.value = false),
        });
    }
};

const deleteItem = (item) => {
    if (confirm(`Hapus kategori "${item.nama_kategori}"?`)) {
        router.delete(`/admin/kategori/${item.id_kategori}`);
    }
};
</script>

<template>
    <Head title="Manajemen Kategori" />

    <AdminLayout title="Manajemen Master Kategori (tbl_kategori)">
        <div
            v-if="page.props.errors?.kategori"
            class="mb-4 p-3 rounded bg-[#b91c1c] border-2 border-black text-white text-xs font-mono font-bold"
        >
            {{ page.props.errors.kategori }}
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <form @submit.prevent="applySearch" class="flex items-center gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari nama atau deskripsi kategori..."
                    class="px-3 py-2 text-xs rounded-md border font-medium w-64"
                    :class="
                        themeState.mode === 'dark'
                            ? 'bg-slate-900 border-slate-700 text-white'
                            : 'bg-white border-slate-300 text-slate-900'
                    "
                />
                <button
                    type="submit"
                    class="px-3 py-2 text-xs font-medium border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 rounded-md transition"
                >
                    Cari
                </button>
            </form>

            <button
                v-if="isAdmin"
                type="button"
                @click="openCreate"
                class="px-4 py-2 text-xs font-semibold uppercase text-white rounded-md bg-[#1E3A5F] hover:bg-[#162C49] transition"
            >
                + Tambah Kategori
            </button>
        </div>

        <div
            class="rounded-md border overflow-hidden"
            :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-700' : 'bg-white border-slate-300'"
        >
            <table class="w-full text-xs text-left">
                <thead
                    class="uppercase font-semibold text-[11px] border-b"
                    :class="themeState.mode === 'dark' ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-600'"
                >
                    <tr>
                        <th class="p-3 w-20">ID</th>
                        <th class="p-3">Nama Kategori</th>
                        <th class="p-3">Deskripsi</th>
                        <th class="p-3 text-right">Jumlah Produk</th>
                        <th v-if="isAdmin" class="p-3 text-center w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="item in kategori.data || []" :key="item.id_kategori" class="hover:bg-slate-50/70">
                        <td class="p-3 font-mono font-semibold">#{{ item.id_kategori }}</td>
                        <td class="p-3 font-medium">{{ item.nama_kategori }}</td>
                        <td class="p-3 text-slate-600">{{ item.deskripsi || '-' }}</td>
                        <td class="p-3 text-right font-mono font-semibold tabular-nums">{{ item.produk_count ?? 0 }}</td>
                        <td v-if="isAdmin" class="p-3 text-center space-x-1.5">
                            <button
                                type="button"
                                @click="openEdit(item)"
                                class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="deleteItem(item)"
                                class="px-2.5 py-1 rounded border border-red-200 bg-white text-red-700 hover:bg-red-50 font-medium"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="(kategori.data || []).length === 0">
                        <td colspan="5" class="p-6 text-center font-bold">
                            Belum ada data kategori.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah / Edit Kategori Solid -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4"
        >
            <div
                class="w-full max-w-md rounded border-2 p-5 shadow-2xl"
                :class="themeState.mode === 'dark' ? 'bg-slate-900 border-slate-600 text-white' : 'bg-white border-slate-500 text-slate-900'"
            >
                <h3 class="text-sm font-extrabold uppercase font-mono mb-4 pb-2 border-b-2 border-slate-400">
                    {{ editingId ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Nama Kategori *</label>
                        <input
                            v-model="form.nama_kategori"
                            type="text"
                            required
                            class="w-full px-3 py-2 rounded border-2 font-bold"
                            :class="themeState.mode === 'dark' ? 'bg-slate-950 border-slate-600' : 'bg-white border-slate-400'"
                        />
                        <div v-if="form.errors.nama_kategori" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.nama_kategori }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Deskripsi</label>
                        <textarea
                            v-model="form.deskripsi"
                            rows="3"
                            class="w-full px-3 py-2 rounded border-2 font-bold"
                            :class="themeState.mode === 'dark' ? 'bg-slate-950 border-slate-600' : 'bg-white border-slate-400'"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex justify-end gap-2 font-mono">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-3 py-1.5 border-2 border-slate-500 rounded font-bold"
                        >
                            BATAL
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-1.5 text-white rounded font-extrabold border-2 border-black"
                            :style="{ backgroundColor: themeState.accentColor }"
                        >
                            SIMPAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
