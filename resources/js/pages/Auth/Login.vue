<script setup>
import { ref, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemePicker from '@/Components/Pos/ThemePicker.vue';
import { useTheme, playBeepSound } from '@/lib/useTheme';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const { themeState, activeTokens } = useTheme();
const loginMode = ref('password'); // 'password' | 'rfid'
const showPassword = ref(false);
const rfidInputRef = ref(null);

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const rfidForm = useForm({
    rfid_uid: '',
});

const switchMode = (mode) => {
    loginMode.value = mode;
    form.clearErrors();
    rfidForm.clearErrors();
    if (mode === 'rfid') {
        nextTick(() => rfidInputRef.value?.focus());
    }
};

const handleUsernameEnter = (event) => {
    const val = String(form.username || '').trim();
    const looksLikeRfidCard = /^\d{6,}$/.test(val) || /^RFID-/i.test(val);

    if (!form.password && looksLikeRfidCard) {
        event.preventDefault();
        loginMode.value = 'rfid';
        submitRfidLogin(val);
    }
};

const submitPasswordLogin = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const submitRfidLogin = (customUid = null) => {
    if (customUid) {
        rfidForm.rfid_uid = customUid;
    }
    if (!rfidForm.rfid_uid.trim()) {
        return;
    }
    playBeepSound();
    rfidForm.post('/login/rfid', {
        onFinish: () => rfidForm.reset('rfid_uid'),
    });
};
</script>

<template>
    <Head title="Login - Sistem Informasi Penjualan Toko" />

    <div
        class="min-h-screen flex flex-col items-center justify-center p-4 font-mono toko-workspace"
        :style="{
            backgroundColor: activeTokens.workspaceBgActive,
            color: activeTokens.textMain,
            filter: `brightness(${themeState.brightness}%)`,
        }"
    >
        <div class="fixed top-4 right-4">
            <ThemePicker />
        </div>

        <div class="w-full max-w-md rounded border-2 p-6 shadow-2xl toko-panel">
            <!-- Header Logo & Judul -->
            <div
                class="text-center mb-5 pb-4 border-b-2 flex flex-col items-center"
                :style="{ borderColor: activeTokens.border }"
            >
                <ApplicationLogo variant="color" :size="48" class="mb-2" />
                <div
                    class="inline-block px-3 py-1 text-xs font-extrabold text-white rounded-sm mb-2 tracking-widest border border-black"
                    :style="{ backgroundColor: activeTokens.primary }"
                >
                    DB_TOKO • POS &amp; RFID AUTH
                </div>
                <h1 class="text-base font-extrabold tracking-wide uppercase">
                    SISTEM INFORMASI PENJUALAN TOKO
                </h1>
                <p class="text-xs font-bold mt-1" :style="{ color: activeTokens.textMuted }">
                    Masuk dengan Username/Password atau Tap Kartu RFID Staf
                </p>
            </div>

            <!-- Tab Pemilih Metode Login (Password vs Kartu RFID) -->
            <div class="grid grid-cols-2 gap-2 mb-5 text-xs font-extrabold uppercase">
                <button
                    type="button"
                    @click="switchMode('password')"
                    class="py-2 px-3 rounded border-2 transition"
                    :style="
                        loginMode === 'password'
                            ? { backgroundColor: activeTokens.primary, color: '#ffffff', borderColor: '#111111' }
                            : { backgroundColor: activeTokens.theadBg, color: activeTokens.textMain, borderColor: activeTokens.border }
                    "
                >
                    🔑 Username
                </button>
                <button
                    type="button"
                    @click="switchMode('rfid')"
                    class="py-2 px-3 rounded border-2 transition"
                    :style="
                        loginMode === 'rfid'
                            ? { backgroundColor: '#047857', color: '#ffffff', borderColor: '#111111' }
                            : { backgroundColor: activeTokens.theadBg, color: activeTokens.textMain, borderColor: activeTokens.border }
                    "
                >
                    🪪 Tap Kartu RFID
                </button>
            </div>

            <!-- Banner Sukses (#d1fae5 / #064e3b) -->
            <div
                v-if="status"
                class="mb-4 p-3 rounded border-2 text-xs font-bold toko-banner-success"
            >
                ✓ {{ status }}
            </div>

            <!-- Banner Error (#fee2e2 / #991b1b) -->
            <div
                v-if="form.errors.username || form.errors.password || rfidForm.errors.rfid_uid"
                class="mb-4 p-3 rounded border-2 text-xs font-extrabold toko-banner-error"
            >
                ⚠ {{ form.errors.username || form.errors.password || rfidForm.errors.rfid_uid }}
            </div>

            <!-- MODE 1: FORM USERNAME & PASSWORD -->
            <form v-if="loginMode === 'password'" @submit.prevent="submitPasswordLogin" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-extrabold uppercase mb-1.5">
                        Username
                    </label>
                    <input
                        id="username"
                        v-model="form.username"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        @keydown.enter="handleUsernameEnter"
                        placeholder="Masukkan username (admin / kasir1 / owner)"
                        class="w-full px-3 py-2 text-sm rounded border-2 font-bold outline-none toko-input-scan"
                    />
                </div>

                <div>
                    <label for="password" class="block text-xs font-extrabold uppercase mb-1.5">
                        Password
                    </label>
                    <div class="relative flex items-center">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                            class="w-full pl-3 pr-20 py-2 text-sm rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-2 px-2 py-1 text-[10px] font-extrabold uppercase rounded-sm border border-black text-[#111111]"
                            :style="{ backgroundColor: activeTokens.keycap }"
                        >
                            {{ showPassword ? 'SEMBUNYI' : 'TAMPIL' }}
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 text-sm font-extrabold uppercase tracking-wider text-white rounded border-2 border-black shadow transition disabled:opacity-50"
                    :style="{ backgroundColor: activeTokens.primary }"
                >
                    {{ form.processing ? 'MEMPROSES...' : 'MASUK' }}
                </button>
            </form>

            <!-- MODE 2: TAP KARTU RFID KASIR / STAF -->
            <form v-else @submit.prevent="submitRfidLogin()" class="space-y-4">
                <div class="p-3 rounded border-2 text-xs font-bold toko-banner-info">
                    🪪 Pastikan kursor berada pada kolom di bawah, lalu <strong>tempelkan (tap) Kartu RFID Kasir/Staf</strong> pada RFID Reader USB Anda.
                </div>

                <div>
                    <label for="rfid_uid" class="block text-xs font-extrabold uppercase mb-1.5">
                        UID Kartu RFID Kasir / Staf
                    </label>
                    <input
                        id="rfid_uid"
                        ref="rfidInputRef"
                        v-model="rfidForm.rfid_uid"
                        type="text"
                        required
                        autofocus
                        placeholder="Tempelkan kartu RFID di sini..."
                        class="w-full px-3 py-2.5 text-base rounded border-2 font-extrabold text-center tracking-wider outline-none toko-input-scan"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="rfidForm.processing"
                    class="w-full py-2.5 text-sm font-extrabold uppercase tracking-wider rounded border-2 border-black shadow transition disabled:opacity-50 toko-btn-pay"
                >
                    {{ rfidForm.processing ? 'MEMBACA KARTU RFID...' : 'LOGIN DENGAN KARTU RFID' }}
                </button>

                <!-- Tombol Simulasi Tap Kartu RFID (Memudahkan Uji Coba Tanpa Alat Reader) -->
                <div class="pt-3 border-t-2" :style="{ borderColor: activeTokens.border }">
                    <div class="text-[11px] font-bold uppercase mb-2" :style="{ color: activeTokens.textMuted }">
                        Simulasi Tap Kartu RFID (Klik untuk Uji Coba):
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 text-[11px] font-extrabold">
                        <button
                            type="button"
                            @click="submitRfidLogin('RFID-KASIR-001')"
                            class="py-1.5 px-2 rounded border-2 border-black text-[#111111]"
                            :style="{ backgroundColor: activeTokens.keycap }"
                        >
                            🪪 Kasir
                        </button>
                        <button
                            type="button"
                            @click="submitRfidLogin('RFID-ADMIN-001')"
                            class="py-1.5 px-2 rounded border-2 border-black text-white"
                            :style="{ backgroundColor: activeTokens.primary }"
                        >
                            🪪 Admin
                        </button>
                        <button
                            type="button"
                            @click="submitRfidLogin('RFID-OWNER-001')"
                            class="py-1.5 px-2 rounded border-2 border-black bg-[#1e293b] text-white"
                        >
                            🪪 Owner
                        </button>
                        <button
                            type="button"
                            @click="submitRfidLogin('RFID-PEL-001')"
                            class="py-1.5 px-2 rounded border-2 border-black bg-[#047857] text-white"
                        >
                            🪪 Member
                        </button>
                    </div>
                </div>
            </form>

            <!-- Tautan Pendaftaran Member Baru -->
            <div
                class="mt-4 pt-3 border-t-2 text-center text-xs"
                :style="{ borderColor: activeTokens.border }"
            >
                <span class="font-bold" :style="{ color: activeTokens.textMuted }">
                    Ingin jadi Member Toko?
                </span>
                <Link
                    href="/register"
                    class="ml-1 font-extrabold underline"
                    :style="{ color: activeTokens.primary }"
                >
                    Daftar Member Baru (+10 Poin) &rarr;
                </Link>
            </div>
        </div>
    </div>
</template>
