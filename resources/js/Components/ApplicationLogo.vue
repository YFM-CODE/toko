<script setup>
import { useTheme } from '@/lib/useTheme';

defineProps({
    variant: {
        type: String,
        default: 'color', // 'color' | 'mono'
    },
    size: {
        type: Number,
        default: 34,
    },
});

const { activeTokens } = useTheme();
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 40 40"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        class="shrink-0"
    >
        <!-- Bingkai / Latar Logo: mengikuti primaryDark / primary (atau #111111 pada versi monokrom struk) -->
        <rect
            x="1"
            y="1"
            width="38"
            height="38"
            rx="4"
            :fill="variant === 'mono' ? '#111111' : activeTokens.primaryDark"
            :stroke="variant === 'mono' ? '#111111' : activeTokens.keycap"
            stroke-width="2"
        />

        <!-- Garis Aksen Atas (keycap: #ffd54f / #fde047 / #facc15, atau #ffffff di mono) -->
        <rect
            x="6"
            y="6"
            width="28"
            height="4"
            rx="1"
            :fill="variant === 'mono' ? '#ffffff' : activeTokens.keycap"
        />

        <!-- Ikon Utama Mesin Kasir / Toko (#ffffff) -->
        <path
            d="M8 14H32V28C32 29.1046 31.1046 30 30 30H10C8.89543 30 8 29.1046 8 28V14Z"
            fill="#ffffff"
        />

        <!-- Layar LED & Barcode Garis di Dalam Ikon -->
        <rect
            x="11"
            y="17"
            width="10"
            height="5"
            rx="1"
            :fill="variant === 'mono' ? '#111111' : activeTokens.primary"
        />
        <rect
            x="24"
            y="17"
            width="2"
            height="9"
            :fill="variant === 'mono' ? '#111111' : activeTokens.primaryDark"
        />
        <rect
            x="27"
            y="17"
            width="3"
            height="9"
            :fill="variant === 'mono' ? '#111111' : activeTokens.primary"
        />

        <!-- Garis Aksen Bawah (keycap) -->
        <rect
            x="10"
            y="33"
            width="20"
            height="2.5"
            rx="1"
            :fill="variant === 'mono' ? '#ffffff' : activeTokens.keycap"
        />
    </svg>
</template>
