<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import {
    formatRupiah,
    generateQrSvgDataUrl,
    printThermalElement,
    printDirectToEpson,
} from '@/lib/useTheme';

const props = defineProps({
    transaksi: {
        type: Object,
        required: true,
    },
});

const paperSize = ref('80mm');
const isPrintingDirect = ref(false);
const printBanner = ref({ type: '', message: '' });

const printStrukDirect = async () => {
    isPrintingDirect.value = true;
    printBanner.value = { type: 'info', message: 'Mengirim struk langsung ke printer thermal...' };

    const result = await printDirectToEpson(props.transaksi.id_transaksi, {
        tunai: props.transaksi.total_belanja,
        kembalian: 0,
        paperSize: paperSize.value,
    });

    isPrintingDirect.value = false;

    if (result.ok) {
        printBanner.value = { type: 'success', message: result.message };
    } else {
        printBanner.value = {
            type: 'error',
            message: `${result.message} Membuka dialog cetak browser...`,
        };
        printThermalElement('printable-receipt', paperSize.value);
    }
};

const printStrukBrowser = () => {
    printThermalElement('printable-receipt', paperSize.value);
};
</script>

<template>
    <Head :title="`Detail Struk ${transaksi.kode_transaksi}`" />

    <AdminLayout :title="`Rincian Nota Transaksi: ${transaksi.kode_transaksi}`">
        <div class="max-w-xl space-y-4 font-mono text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 no-print">
                <Link href="/transaksi" class="px-3 py-1.5 rounded border-2 border-[#94a3b8] bg-white text-[#0f172a] font-bold">
                    &larr; Kembali ke Riwayat Transaksi
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="paperSize"
                        class="px-2.5 py-2 rounded border-2 border-[#111111] bg-white text-[#111111] font-extrabold"
                    >
                        <option value="80mm">Kertas 80mm</option>
                        <option value="58mm">Kertas 58mm</option>
                    </select>
                    <button
                        type="button"
                        :disabled="isPrintingDirect"
                        @click="printStrukDirect"
                        class="px-3.5 py-2 rounded border-2 border-[#111111] font-extrabold uppercase toko-btn-pay disabled:opacity-50"
                    >
                        {{
                            isPrintingDirect
                                ? '⚡ MENCETAK...'
                                : `⚡ CETAK LANGSUNG (${paperSize})`
                        }}
                    </button>
                    <button
                        type="button"
                        @click="printStrukBrowser"
                        class="px-3 py-2 rounded border-2 border-[#111111] bg-white text-[#111111] font-extrabold uppercase"
                    >
                        🖨 PREVIEW BROWSER
                    </button>
                </div>
            </div>

            <div
                v-if="printBanner.message"
                class="px-3 py-2 rounded border-2 font-extrabold no-print"
                :class="{
                    'toko-banner-success': printBanner.type === 'success',
                    'toko-banner-error': printBanner.type === 'error',
                    'toko-banner-info': printBanner.type === 'info',
                }"
            >
                {{ printBanner.message }}
            </div>

            <!-- Kertas Struk Digital (#fffef8 / #0f172a) dengan Lebar Menyesuaikan Kertas Struk -->
            <div
                id="printable-receipt"
                class="p-5 rounded border-2 border-[#94a3b8] space-y-3 toko-receipt-paper mx-auto"
                :style="{ maxWidth: paperSize === '58mm' ? '58mm' : '80mm' }"
            >
                <div class="text-center border-b-2 border-dashed border-[#111111] pb-3 flex flex-col items-center">
                    <ApplicationLogo variant="mono" :size="36" class="mb-1.5" />
                    <div class="text-base font-extrabold uppercase text-[#111111]">TOKO RITEL MANDIRI</div>
                    <div class="text-[11px] font-bold text-[#0f172a]">
                        Jl. Raya Ponorogo No. 10 • Telp: (0352) 481234
                    </div>
                    <div class="text-[10px] mt-0.5 text-[#475569]">STRUK PENJUALAN RESMI</div>
                </div>

                <div class="text-xs space-y-1 border-b-2 border-dashed border-[#111111] pb-3">
                    <div class="flex justify-between">
                        <span class="font-bold">NO. STRUK:</span>
                        <span class="font-extrabold">{{ transaksi.kode_transaksi }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">TANGGAL:</span>
                        <span>{{ transaksi.tanggal }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">PELANGGAN:</span>
                        <span class="font-bold">{{ transaksi.pelanggan?.nama_pelanggan || 'Pelanggan Umum' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">METODE BAYAR:</span>
                        <span class="font-extrabold uppercase">
                            {{
                                transaksi.metode_pembayaran && transaksi.metode_pembayaran !== 'TUNAI'
                                    ? transaksi.payment_channel || transaksi.metode_pembayaran
                                    : 'TUNAI (CASH)'
                            }}
                        </span>
                    </div>
                    <div
                        v-if="transaksi.payment_ref && transaksi.metode_pembayaran !== 'TUNAI'"
                        class="flex justify-between"
                    >
                        <span class="font-bold">REF GATEWAY:</span>
                        <span class="font-extrabold">{{ transaksi.payment_ref }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-bold">STATUS:</span>
                        <span class="font-extrabold uppercase">{{ transaksi.status }}</span>
                    </div>
                </div>

                <div class="border-b-2 border-dashed border-[#111111] pb-3 space-y-2 text-xs">
                    <div
                        v-for="d in transaksi.detail || []"
                        :key="d.id_detail"
                        class="flex justify-between items-start gap-2"
                    >
                        <div>
                            <div class="font-extrabold">{{ d.produk?.nama_produk || '-' }}</div>
                            <div class="text-[11px] tabular-nums">
                                {{ d.jumlah }} x {{ formatRupiah(d.harga) }}
                            </div>
                        </div>
                        <div class="font-extrabold tabular-nums">
                            {{ formatRupiah(d.subtotal_item) }}
                        </div>
                    </div>
                </div>

                <div class="space-y-1 text-right tabular-nums border-b-2 border-dashed border-[#111111] pb-3">
                    <div class="flex justify-between">
                        <span class="font-bold">SUBTOTAL:</span>
                        <strong>{{ formatRupiah(transaksi.subtotal) }}</strong>
                    </div>
                    <div class="flex justify-between text-[#b91c1c]">
                        <span class="font-bold">DISKON:</span>
                        <strong>- {{ formatRupiah(transaksi.diskon) }}</strong>
                    </div>
                    <div class="flex justify-between text-sm font-extrabold pt-1 border-t border-[#111111]">
                        <span>GRAND TOTAL:</span>
                        <span>{{ formatRupiah(transaksi.total_belanja) }}</span>
                    </div>
                </div>

                <div class="flex flex-col items-center text-center pt-1">
                    <img
                        :src="generateQrSvgDataUrl(transaksi.kode_transaksi)"
                        :alt="transaksi.kode_transaksi"
                        class="w-24 h-24 border border-[#111111] p-1 bg-white mb-1"
                    />
                    <div class="text-[10px] font-bold">{{ transaksi.kode_transaksi }}</div>
                    <div class="text-[11px] font-extrabold mt-1">TERIMA KASIH ATAS KUNJUNGAN ANDA</div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
