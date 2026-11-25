<script setup>
import { ref } from 'vue';
import { useTheme } from '@/lib/useTheme';

const { themeState, activeTokens, palettes, toggleMode, setAccentColor, setBrightness } = useTheme();
const open = ref(false);
</script>

<template>
    <div class="relative flex items-center gap-2 no-print">
        <!-- 1-Click Contrast Toggle menggunakan keycap & primaryDark -->
        <button
            type="button"
            @click="toggleMode"
            class="px-2.5 py-1 text-xs font-mono font-extrabold uppercase tracking-wider border-2 transition"
            :style="
                themeState.mode === 'dark'
                    ? { backgroundColor: '#162033', color: activeTokens.keycap, borderColor: '#334155' }
                    : { backgroundColor: activeTokens.keycap, color: '#111111', borderColor: '#111111' }
            "
            title="Ubah Kontras Terang / Gelap"
        >
            {{ themeState.mode === 'dark' ? 'MODE: GELAP' : 'MODE: TERANG' }}
        </button>

        <!-- Theme & Brightness Trigger -->
        <button
            type="button"
            @click="open = !open"
            class="px-2.5 py-1 text-xs font-mono font-extrabold uppercase tracking-wider border-2 flex items-center gap-1.5 transition"
            :style="{
                backgroundColor: activeTokens.panelBg,
                color: activeTokens.textMain,
                borderColor: activeTokens.border,
            }"
        >
            <span
                class="inline-block w-3.5 h-3.5 border border-black"
                :style="{ backgroundColor: activeTokens.primary }"
            ></span>
            <span>TEMA</span>
        </button>

        <!-- Dropdown Popover -->
        <div
            v-if="open"
            class="absolute right-0 top-full mt-2 w-72 p-4 rounded border-2 shadow-2xl z-50 text-xs font-mono"
            :style="{
                backgroundColor: activeTokens.panelBg,
                color: activeTokens.textMain,
                borderColor: activeTokens.border,
            }"
        >
            <div
                class="flex items-center justify-between mb-3 pb-2 border-b-2"
                :style="{ borderColor: activeTokens.border }"
            >
                <span class="font-extrabold uppercase tracking-wider">4 Palet Tema &amp; Kontras</span>
                <button
                    type="button"
                    @click="open = false"
                    class="px-1.5 py-0.5 bg-[#b91c1c] text-white font-bold rounded"
                >
                    X
                </button>
            </div>

            <!-- 4 Preset Palettes -->
            <div class="mb-3">
                <label class="block text-[11px] font-bold uppercase mb-1.5" :style="{ color: activeTokens.textMuted }">
                    Palet Tema Utama
                </label>
                <div class="grid grid-cols-2 gap-1.5">
                    <button
                        v-for="p in palettes"
                        :key="p.id"
                        type="button"
                        @click="setAccentColor(p.primary)"
                        class="flex items-center gap-2 px-2 py-1.5 border-2 rounded text-left transition"
                        :style="{
                            borderColor:
                                activeTokens.primary.toLowerCase() === p.primary.toLowerCase()
                                    ? p.keycap
                                    : activeTokens.border,
                            backgroundColor:
                                activeTokens.primary.toLowerCase() === p.primary.toLowerCase()
                                    ? p.primary
                                    : 'transparent',
                            color:
                                activeTokens.primary.toLowerCase() === p.primary.toLowerCase()
                                    ? '#ffffff'
                                    : activeTokens.textMain,
                        }"
                    >
                        <span
                            class="w-3.5 h-3.5 shrink-0 border border-black"
                            :style="{ backgroundColor: p.primary }"
                        ></span>
                        <span class="truncate font-bold">{{ p.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Custom Color Picker -->
            <div class="mb-3 flex items-center justify-between">
                <label class="text-[11px] font-bold uppercase" :style="{ color: activeTokens.textMuted }">
                    Warna Kustom
                </label>
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        :value="themeState.accentColor"
                        @input="(e) => setAccentColor(e.target.value)"
                        class="w-7 h-7 cursor-pointer bg-transparent border-0"
                    />
                    <span class="uppercase font-bold">{{ themeState.accentColor }}</span>
                </div>
            </div>

            <!-- Brightness Slider (65% - 115%) -->
            <div>
                <div class="flex justify-between text-[11px] font-bold uppercase mb-1" :style="{ color: activeTokens.textMuted }">
                    <span>Kecerahan Layar</span>
                    <span>{{ themeState.brightness }}%</span>
                </div>
                <input
                    type="range"
                    min="65"
                    max="115"
                    step="1"
                    :value="themeState.brightness"
                    @input="(e) => setBrightness(e.target.value)"
                    class="w-full cursor-pointer"
                />
                <div class="flex justify-between text-[10px] mt-1">
                    <span>65%</span>
                    <button
                        type="button"
                        @click="setBrightness(100)"
                        class="underline font-bold"
                    >
                        Reset 100%
                    </button>
                    <span>115%</span>
                </div>
            </div>
        </div>
    </div>
</template>
