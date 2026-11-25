<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemePicker from '@/Components/Pos/ThemePicker.vue';
import { useTheme, playBeepSound } from '@/lib/useTheme';

const { themeState, activeTokens } = useTheme();
const rfidInputRef = ref(null);
const namaInputRef = ref(null);

// Simpan produk yang diklik dari katalog depan agar otomatis masuk keranjang setelah selesai register
if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    const buyProductId = params.get('buy');
    if (buyProductId) {
        window.sessionStorage.setItem('toko_pending_buy_product', buyProductId);
    }
}

const form = useForm({
    nama_lengkap: '',
    username: '',
    email: '',
    rfid_uid: '',
    kota: '',
    no_telepon: '',
    password: '',
    password_confirmation: '',
});

const handleRfidEnter = () => {
    playBeepSound();
    namaInputRef.value?.focus();
};

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Daftar Member Baru - Sistem Informasi Penjualan Toko" />

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

        <div class="w-full max-w-lg rounded border-2 p-6 shadow-2xl toko-panel">
            <!-- Header -->
            <div
                class="text-center mb-4 pb-3 border-b-2 flex flex-col items-center"
                :style="{ borderColor: activeTokens.border }"
            >
                <ApplicationLogo variant="color" :size="44" class="mb-2" />
                <div
                    class="inline-block px-3 py-1 text-xs font-extrabold text-white rounded-sm mb-1.5 tracking-widest border border-black"
                    :style="{ backgroundColor: activeTokens.primary }"
                >
                    REGISTRASI MEMBER RESMI (+10 POIN BONUS)
                </div>
                <h1 class="text-base font-extrabold uppercase">
                    DAFTAR AKUN MEMBER / PELANGGAN TOKO
                </h1>
                <p class="text-[11px] font-bold mt-0.5" :style="{ color: activeTokens.textMuted }">
                    Dapatkan diskon otomatis member, poin belanja, kartu QR/RFID digital &amp; riwayat struk
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-3 text-xs">
                <div>
                    <label class="block font-extrabold uppercase mb-1">
                        🪪 UID Kartu RFID Member (Opsional — Tap Kartu Jika Punya)
                    </label>
                    <input
                        ref="rfidInputRef"
                        v-model="form.rfid_uid"
                        type="text"
                        placeholder="Tap kartu RFID Anda atau kosongkan..."
                        @keydown.enter.prevent="handleRfidEnter"
                        class="w-full px-3 py-2 rounded border-2 font-extrabold outline-none toko-input-scan"
                    />
                    <div v-if="form.errors.rfid_uid" class="text-[#b91c1c] font-bold mt-1">
                        {{ form.errors.rfid_uid }}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase mb-1">Nama Lengkap *</label>
                        <input
                            ref="namaInputRef"
                            v-model="form.nama_lengkap"
                            type="text"
                            required
                            placeholder="Misal: Andi Wijaya"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <div v-if="form.errors.nama_lengkap" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.nama_lengkap }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold uppercase mb-1">Username Login *</label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            placeholder="Misal: andi_member"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <div v-if="form.errors.username" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.username }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase mb-1">Kota Asal *</label>
                        <input
                            v-model="form.kota"
                            type="text"
                            required
                            placeholder="Misal: Ponorogo"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <div v-if="form.errors.kota" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.kota }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold uppercase mb-1">No. Telepon / WA</label>
                        <input
                            v-model="form.no_telepon"
                            type="text"
                            placeholder="08123456789"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <div v-if="form.errors.no_telepon" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.no_telepon }}
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-extrabold uppercase mb-1">Email (Opsional)</label>
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="andi@email.com"
                        class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                    />
                    <div v-if="form.errors.email" class="text-[#b91c1c] font-bold mt-1">
                        {{ form.errors.email }}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase mb-1">Password *</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="Minimal 6 karakter"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                        <div v-if="form.errors.password" class="text-[#b91c1c] font-bold mt-1">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold uppercase mb-1">Ulangi Password *</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            placeholder="Ulangi password"
                            class="w-full px-3 py-2 rounded border-2 font-bold outline-none toko-input-scan"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 mt-2 text-sm font-extrabold uppercase tracking-wider rounded border-2 border-black shadow transition disabled:opacity-50 toko-btn-pay"
                >
                    {{ form.processing ? 'MENDAFTARKAN MEMBER...' : '★ DAFTAR MEMBER SEKARANG' }}
                </button>

                <div class="pt-3 border-t-2 text-center" :style="{ borderColor: activeTokens.border }">
                    <Link
                        href="/login"
                        class="font-extrabold underline"
                        :style="{ color: activeTokens.primary }"
                    >
                        Sudah punya akun Member / Staf? Masuk di sini &rarr;
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
