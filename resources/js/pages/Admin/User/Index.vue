<script setup>
import { ref, nextTick } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme, playBeepSound } from '@/lib/useTheme';

defineProps({
    users: {
        type: Object,
        default: () => ({ data: [] }),
    },
    roles: {
        type: Array,
        default: () => ['admin', 'kasir', 'owner'],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const { activeTokens } = useTheme();
const showModal = ref(false);
const editingId = ref(null);
const rfidInputRef = ref(null);
const namaInputRef = ref(null);

const form = useForm({
    nama_lengkap: '',
    username: '',
    rfid_uid: '',
    email: '',
    role: 'kasir',
    is_active: true,
    password: '',
});

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.is_active = true;
    form.role = 'kasir';
    form.clearErrors();
    showModal.value = true;
    nextTick(() => rfidInputRef.value?.focus());
};

const openEdit = (u) => {
    editingId.value = u.id_user;
    form.nama_lengkap = u.nama_lengkap;
    form.username = u.username;
    form.rfid_uid = u.rfid_uid || '';
    form.email = u.email || '';
    form.role = u.role;
    form.is_active = Boolean(u.is_active);
    form.password = '';
    form.clearErrors();
    showModal.value = true;
};

const focusRfidInput = () => {
    nextTick(() => rfidInputRef.value?.focus());
};

const handleRfidEnter = () => {
    playBeepSound();
    nextTick(() => namaInputRef.value?.focus());
};

const submitForm = () => {
    if (editingId.value) {
        form.put(`/admin/users/${editingId.value}`, {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post('/admin/users', {
            onSuccess: () => (showModal.value = false),
        });
    }
};

const deleteUser = (u) => {
    if (confirm(`Hapus akun staf "${u.nama_lengkap}" (@${u.username})?`)) {
        router.delete(`/admin/users/${u.id_user}`);
    }
};
</script>

<template>
    <Head title="Manajemen Akun Staf & RFID Kasir" />

    <AdminLayout title="Manajemen Akun Staf & Kartu RFID Kasir (tbl_users)">
        <div
            v-if="page.props.errors?.user"
            class="mb-4 p-3 rounded border-2 text-xs font-mono font-extrabold toko-banner-error"
        >
            ⚠ {{ page.props.errors.user }}
        </div>

        <div class="flex justify-end mb-4">
            <button
                type="button"
                @click="openCreate"
                class="px-4 py-2 text-xs font-semibold uppercase text-white rounded-md bg-[#1E3A5F] hover:bg-[#162C49] transition"
            >
                + Tambah Akun Staf &amp; Kartu RFID
            </button>
        </div>

        <div class="rounded-md border overflow-hidden toko-panel">
            <table class="w-full text-xs text-left">
                <thead class="uppercase font-semibold text-[11px] border-b toko-thead">
                    <tr>
                        <th class="p-3">ID</th>
                        <th class="p-3">UID Kartu RFID</th>
                        <th class="p-3">Nama Lengkap</th>
                        <th class="p-3">Username</th>
                        <th class="p-3">Role</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-center w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="u in users.data || []" :key="u.id_user" class="hover:bg-slate-50/70">
                        <td class="p-3 font-mono font-semibold">#{{ u.id_user }}</td>
                        <td class="p-3 font-mono">
                            <span
                                v-if="u.rfid_uid"
                                class="font-medium text-slate-700"
                            >
                                {{ u.rfid_uid }}
                            </span>
                            <span v-else class="text-slate-400">-</span>
                        </td>
                        <td class="p-3 font-medium">{{ u.nama_lengkap }}</td>
                        <td class="p-3 font-mono text-slate-600">@{{ u.username }}</td>
                        <td class="p-3 font-mono uppercase font-semibold">{{ u.role }}</td>
                        <td class="p-3 text-center font-mono">
                            <span
                                :class="u.is_active ? 'text-slate-700 font-medium' : 'text-red-700 font-semibold'"
                            >
                                {{ u.is_active ? 'AKTIF' : 'NONAKTIF' }}
                            </span>
                        </td>
                        <td class="p-3 text-center space-x-1.5">
                            <button
                                type="button"
                                @click="openEdit(u)"
                                class="px-2.5 py-1 rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="deleteUser(u)"
                                class="px-2.5 py-1 rounded border border-red-200 bg-white text-red-700 hover:bg-red-50 font-medium"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah / Edit User + Tap Kartu RFID Kasir -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black p-4"
        >
            <div class="w-full max-w-md rounded border-2 p-5 shadow-2xl toko-panel">
                <h3 class="text-sm font-extrabold uppercase font-mono mb-4 pb-2 border-b-2" :style="{ borderColor: activeTokens.border }">
                    {{ editingId ? 'Edit Akun Staf & Kartu RFID' : 'Tambah Akun Staf & Kartu RFID' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-3 text-xs">
                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">
                            🪪 UID Kartu RFID Kasir / Staf (Tap Kartu ke Reader)
                        </label>
                        <div class="flex gap-1.5">
                            <input
                                ref="rfidInputRef"
                                v-model="form.rfid_uid"
                                type="text"
                                placeholder="Tempelkan kartu RFID kasir/staf..."
                                @keydown.enter.prevent="handleRfidEnter"
                                class="flex-1 px-3 py-2 rounded border-2 font-mono font-extrabold toko-input-scan"
                            />
                            <button
                                type="button"
                                @click="focusRfidInput"
                                class="px-3 py-2 rounded border-2 border-black font-mono font-extrabold text-[#111111]"
                                :style="{ backgroundColor: activeTokens.keycap }"
                            >
                                🪪 Tap
                            </button>
                        </div>
                        <div v-if="form.errors.rfid_uid" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.rfid_uid }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Nama Lengkap *</label>
                        <input
                            ref="namaInputRef"
                            v-model="form.nama_lengkap"
                            type="text"
                            required
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        />
                    </div>

                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">Username *</label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            class="w-full px-3 py-2 rounded border-2 font-mono font-bold toko-input-scan"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-mono font-bold uppercase mb-1">Role *</label>
                            <select
                                v-model="form.role"
                                class="w-full px-3 py-2 rounded border-2 font-mono font-bold uppercase toko-input-scan"
                            >
                                <option v-for="r in roles" :key="r" :value="r">{{ r.toUpperCase() }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-mono font-bold uppercase mb-1">Status Akun</label>
                            <select
                                v-model="form.is_active"
                                class="w-full px-3 py-2 rounded border-2 font-mono font-bold toko-input-scan"
                            >
                                <option :value="true">Aktif</option>
                                <option :value="false">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-mono font-bold uppercase mb-1">
                            Password {{ editingId ? '(Kosongkan jika tidak diubah)' : '*' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!editingId"
                            class="w-full px-3 py-2 rounded border-2 font-bold toko-input-scan"
                        />
                    </div>

                    <div class="pt-2 flex justify-end gap-2 font-mono">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-3 py-1.5 border-2 rounded font-bold"
                            :style="{ borderColor: activeTokens.border }"
                        >
                            BATAL
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-1.5 text-white rounded font-extrabold border-2 border-black"
                            :style="{ backgroundColor: activeTokens.primary }"
                        >
                            SIMPAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
