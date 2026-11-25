<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: Object,
});
</script>

<template>
    <Head title="Dashboard Admin" />
    <AuthenticatedLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold text-gray-800">Berhasil Masuk: Dashboard Admin</h1>
            <p class="mt-2 text-gray-600">Role Anda terdeteksi sebagai Administrator.</p>
        </div>
    </AuthenticatedLayout>
</template>
