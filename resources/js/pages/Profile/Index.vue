<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useTheme } from '@/lib/useTheme';

const page = usePage();
const { themeState } = useTheme();

const currentUser = computed(() => page.props.auth?.user || {});

const profileForm = useForm({
    nama_lengkap: currentUser.value.nama_lengkap || currentUser.value.name || '',
    username: currentUser.value.username || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitProfile = () => {
    profileForm.put('/profil', {
        preserveScroll: true,
    });
};

const submitPassword = () => {
    passwordForm.put('/profil/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <Head title="Profil Akun" />

    <AdminLayout title="Profil & Keamanan Akun">
        <div class="max-w-4xl space-y-6">
            <div v-if="currentUser.role === 'kasir'" class="flex justify-end">
                <Link
                    href="/pos"
                    class="px-4 py-2 text-xs font-mono font-extrabold uppercase text-white rounded border-2 border-black shadow"
                    :style="{ backgroundColor: themeState.accentColor }"
                >
                    &larr; Kembali ke Layar Kasir (POS)
                </Link>
            </div>

            <!-- 1. Kartu Informasi Akun Solid -->
            <div
                class="p-6 rounded border-2"
                :class="
                    themeState.mode === 'dark'
                        ? 'bg-slate-900 border-slate-700'
                        : 'bg-white border-slate-400'
                "
            >
                <h2 class="text-base font-extrabold mb-4 pb-2 border-b-2 border-slate-400">
                    Informasi Akun Pengguna (tbl_users)
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs font-mono font-bold uppercase">Nama Lengkap</div>
                        <div class="font-extrabold mt-1">{{ currentUser.nama_lengkap || currentUser.name }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase">Username</div>
                        <div class="font-mono font-extrabold mt-1">@{{ currentUser.username }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase">Hak Akses (Read-Only)</div>
                        <div class="mt-1">
                            <span class="px-2.5 py-0.5 text-xs font-mono font-extrabold uppercase rounded-sm bg-[#0b3c6f] text-white border border-black">
                                {{ (currentUser.role || 'kasir').toUpperCase() }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase">Status Akun (Read-Only)</div>
                        <div class="mt-1">
                            <span class="px-2.5 py-0.5 text-xs font-mono font-extrabold uppercase rounded-sm bg-[#15803d] text-white border border-black">
                                AKTIF
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Form Ubah Data Diri Solid -->
            <div
                class="p-6 rounded border-2"
                :class="
                    themeState.mode === 'dark'
                        ? 'bg-slate-900 border-slate-700'
                        : 'bg-white border-slate-400'
                "
            >
                <h2 class="text-base font-extrabold mb-4 pb-2 border-b-2 border-slate-400">
                    Ubah Data Diri
                </h2>

                <form @submit.prevent="submitProfile" class="space-y-4 max-w-lg">
                    <div>
                        <label class="block text-xs font-mono font-bold uppercase mb-1">Nama Lengkap</label>
                        <input
                            v-model="profileForm.nama_lengkap"
                            type="text"
                            required
                            class="w-full px-3 py-2 text-sm rounded border-2 font-bold"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-600 text-white'
                                    : 'bg-white border-slate-400 text-slate-900'
                            "
                        />
                        <div v-if="profileForm.errors.nama_lengkap" class="text-xs text-[#b91c1c] font-bold mt-1">
                            {{ profileForm.errors.nama_lengkap }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono font-bold uppercase mb-1">Username</label>
                        <input
                            v-model="profileForm.username"
                            type="text"
                            required
                            class="w-full px-3 py-2 text-sm rounded border-2 font-mono font-bold"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-600 text-white'
                                    : 'bg-white border-slate-400 text-slate-900'
                            "
                        />
                        <div v-if="profileForm.errors.username" class="text-xs text-[#b91c1c] font-bold mt-1">
                            {{ profileForm.errors.username }}
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="px-4 py-2 text-xs font-mono font-extrabold uppercase text-white rounded border-2 border-black shadow transition disabled:opacity-50"
                        :style="{ backgroundColor: themeState.accentColor }"
                    >
                        {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </form>
            </div>

            <!-- 3. Form Ganti Password Solid -->
            <div
                class="p-6 rounded border-2"
                :class="
                    themeState.mode === 'dark'
                        ? 'bg-slate-900 border-slate-700'
                        : 'bg-white border-slate-400'
                "
            >
                <h2 class="text-base font-extrabold mb-4 pb-2 border-b-2 border-slate-400">
                    Ganti Password
                </h2>

                <form @submit.prevent="submitPassword" class="space-y-4 max-w-lg">
                    <div>
                        <label class="block text-xs font-mono font-bold uppercase mb-1">Password Saat Ini</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            class="w-full px-3 py-2 text-sm rounded border-2 font-bold"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-600 text-white'
                                    : 'bg-white border-slate-400 text-slate-900'
                            "
                        />
                        <div v-if="passwordForm.errors.current_password" class="text-xs text-[#b91c1c] font-bold mt-1">
                            {{ passwordForm.errors.current_password }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono font-bold uppercase mb-1">Password Baru (Min. 6 Karakter)</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            class="w-full px-3 py-2 text-sm rounded border-2 font-bold"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-600 text-white'
                                    : 'bg-white border-slate-400 text-slate-900'
                            "
                        />
                        <div v-if="passwordForm.errors.password" class="text-xs text-[#b91c1c] font-bold mt-1">
                            {{ passwordForm.errors.password }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono font-bold uppercase mb-1">Konfirmasi Password Baru</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            class="w-full px-3 py-2 text-sm rounded border-2 font-bold"
                            :class="
                                themeState.mode === 'dark'
                                    ? 'bg-slate-950 border-slate-600 text-white'
                                    : 'bg-white border-slate-400 text-slate-900'
                            "
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="px-4 py-2 text-xs font-mono font-extrabold uppercase text-white rounded border-2 border-black shadow transition disabled:opacity-50"
                        :style="{ backgroundColor: themeState.accentColor }"
                    >
                        {{ passwordForm.processing ? 'Memperbarui...' : 'Perbarui Password' }}
                    </button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
