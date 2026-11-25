import { reactive, computed, watch } from 'vue';

export const THEME_PALETTES = [
    {
        id: 'blue',
        label: 'Navy Korporat',
        color: '#1E3A5F',
        primary: '#1E3A5F',
        primaryDark: '#162C49',
        rowHighlight: '#F1F5F9',
        workspaceBg: '#F1F5F9',
        ledText: '#FFFFFF',
        keycap: '#F8FAFC',
    },
    {
        id: 'slate',
        label: 'Slate Profesional',
        color: '#1e293b',
        primary: '#1e293b',
        primaryDark: '#0f172a',
        rowHighlight: '#F1F5F9',
        workspaceBg: '#F1F5F9',
        ledText: '#FFFFFF',
        keycap: '#F8FAFC',
    },
    {
        id: 'green',
        label: 'Hijau Ritel',
        color: '#166534',
        primary: '#166534',
        primaryDark: '#14532d',
        rowHighlight: '#F1F5F9',
        workspaceBg: '#F1F5F9',
        ledText: '#FFFFFF',
        keycap: '#F8FAFC',
    },
    {
        id: 'red',
        label: 'Merah Ritel',
        color: '#991b1b',
        primary: '#991b1b',
        primaryDark: '#7f1d1d',
        rowHighlight: '#F1F5F9',
        workspaceBg: '#F1F5F9',
        ledText: '#FFFFFF',
        keycap: '#F8FAFC',
    },
];

export function hexToRgba(hex, alpha = 1) {
    const clean = String(hex || '#0b3c6f').replace('#', '');
    const num = parseInt(clean.length === 3 ? clean.replace(/(.)/g, '$1$1') : clean, 16);
    const r = (num >> 16) & 255;
    const g = (num >> 8) & 255;
    const b = num & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function darkenHex(hex, factor = 0.7) {
    const clean = String(hex || '#0b3c6f').replace('#', '');
    const num = parseInt(clean.length === 3 ? clean.replace(/(.)/g, '$1$1') : clean, 16);
    const r = Math.max(0, Math.floor(((num >> 16) & 255) * factor));
    const g = Math.max(0, Math.floor(((num >> 8) & 255) * factor));
    const b = Math.max(0, Math.floor((num & 255) * factor));
    return '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
}

export function resolvePaletteTokens(accentColor) {
    const found = THEME_PALETTES.find(
        (p) => p.primary.toLowerCase() === String(accentColor || '').toLowerCase(),
    );
    if (found) {
        return found;
    }

    const primary = accentColor || '#1E3A5F';
    return {
        id: 'custom',
        label: 'Navy Korporat',
        color: '#1E3A5F',
        primary: '#1E3A5F',
        primaryDark: '#162C49',
        rowHighlight: '#F1F5F9',
        workspaceBg: '#F1F5F9',
        ledText: '#FFFFFF',
        keycap: '#F8FAFC',
    };
}

const loadInitialState = () => {
    if (typeof window === 'undefined') {
        return { mode: 'light', accentColor: '#1E3A5F', brightness: 100 };
    }

    return {
        mode: localStorage.getItem('toko_theme_mode') || 'light',
        accentColor: '#1E3A5F',
        brightness: Number(localStorage.getItem('toko_theme_brightness') || 100),
    };
};

export const themeState = reactive(loadInitialState());

export const activeTokens = computed(() => {
    const palette = resolvePaletteTokens(themeState.accentColor);
    const isDark = themeState.mode === 'dark';

    return {
        ...palette,
        isDark,
        workspaceBgActive: isDark ? '#0b1220' : '#F1F5F9',
        panelBg: isDark ? '#162033' : '#FFFFFF',
        theadBg: isDark ? '#1e293b' : '#F8FAFC',
        inputScanBg: isDark ? '#0b1120' : '#FFFFFF',
        inputScanText: isDark ? '#f8fafc' : '#0F172A',
        textMain: isDark ? '#f1f5f9' : '#0F172A',
        textMuted: isDark ? '#94a3b8' : '#475569',
        border: isDark ? '#334155' : '#CBD5E1',
        rowHighlightActive: isDark ? hexToRgba(palette.primary, 0.25) : '#F1F5F9',
        priceText: isDark ? '#f1f5f9' : '#0F172A',
    };
});

export const applyThemeToDocument = () => {
    if (typeof document === 'undefined') {
        return;
    }

    const t = activeTokens.value;
    const root = document.documentElement;
    root.style.setProperty('--theme-accent', t.primary);
    root.style.setProperty('--theme-brightness', `${themeState.brightness}%`);
    root.style.setProperty('--toko-primary', t.primary);
    root.style.setProperty('--toko-primary-dark', t.primaryDark);
    root.style.setProperty('--toko-keycap', t.keycap);
    root.style.setProperty('--toko-led-text', t.ledText);
    root.style.setProperty('--toko-workspace-bg', t.workspaceBgActive);
    root.style.setProperty('--toko-panel-bg', t.panelBg);
    root.style.setProperty('--toko-thead-bg', t.theadBg);
    root.style.setProperty('--toko-input-scan-bg', t.inputScanBg);
    root.style.setProperty('--toko-input-scan-text', t.inputScanText);
    root.style.setProperty('--toko-text-main', t.textMain);
    root.style.setProperty('--toko-text-muted', t.textMuted);
    root.style.setProperty('--toko-border', t.border);
    root.style.setProperty('--toko-row-highlight', t.rowHighlightActive);
    root.style.setProperty('--toko-price-text', t.priceText);

    if (themeState.mode === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
};

if (typeof window !== 'undefined') {
    applyThemeToDocument();

    watch(
        () => ({ ...themeState }),
        (val) => {
            localStorage.setItem('toko_theme_mode', val.mode);
            localStorage.setItem('toko_theme_color', val.accentColor);
            localStorage.setItem('toko_theme_brightness', String(val.brightness));
            applyThemeToDocument();
        },
        { deep: true },
    );
}

export function useTheme() {
    const toggleMode = () => {
        themeState.mode = themeState.mode === 'dark' ? 'light' : 'dark';
    };

    const setAccentColor = (color) => {
        themeState.accentColor = color;
    };

    const setBrightness = (value) => {
        const clamped = Math.min(115, Math.max(65, Number(value) || 100));
        themeState.brightness = clamped;
    };

    return {
        themeState,
        activeTokens,
        palettes: THEME_PALETTES,
        toggleMode,
        setAccentColor,
        setBrightness,
    };
}

export function formatRupiah(value) {
    const number = Number(value) || 0;
    return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(number);
}

/**
 * Generates an inline SVG product illustration using the specification's Hex palette.
 */
export function createProductSvg(code = 'POS', name = 'BELUM ADA ITEM') {
    const solidColors = ['#0b3c6f', '#b91c1c', '#166534', '#1e293b', '#047857', '#7f1d1d'];
    const str = String(code || name);
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const bgColor = code === 'POS' ? '#1e293b' : solidColors[Math.abs(hash) % solidColors.length];
    const safeCode = String(code || 'ITEM')
        .replace(/[<>&"']/g, '')
        .slice(0, 10);
    const safeName = String(name || '')
        .replace(/[<>&"']/g, '')
        .toUpperCase()
        .slice(0, 12);

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">
        <rect width="120" height="120" fill="${bgColor}" rx="4"/>
        <rect x="6" y="6" width="108" height="108" fill="none" stroke="#ffd54f" stroke-width="2" rx="2"/>
        <text x="50%" y="44%" dominant-baseline="middle" text-anchor="middle" fill="#ffd54f" font-family="Consolas, monospace" font-weight="bold" font-size="15">${safeCode}</text>
        <text x="50%" y="66%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="Consolas, monospace" font-weight="bold" font-size="11">${safeName}</text>
    </svg>`;

    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
}

export function getProductImage(item) {
    if (!item) {
        return createProductSvg('POS', 'SIAP SCAN');
    }
    return item.gambar_url || item.img || createProductSvg(item.kode_produk, item.nama_produk);
}

export function handleProductImageError(event, item) {
    if (!event?.target) {
        return;
    }
    const fallback = item
        ? createProductSvg(item.kode_produk, item.nama_produk)
        : createProductSvg('POS', 'SIAP SCAN');
    if (event.target.src !== fallback) {
        event.target.src = fallback;
    }
}

/**
 * Generates a scannable Version 1-L ISO/IEC 18004 QR Code SVG Data URI locally (offline, 0 dependencies).
 */
export function generateQrSvgDataUrl(text) {
    const raw = String(text || 'PRD-001').slice(0, 17);
    const bytes = Array.from(raw).map((c) => c.charCodeAt(0) & 0xff);

    const bits = [];
    const pushBits = (val, len) => {
        for (let i = len - 1; i >= 0; i--) {
            bits.push((val >> i) & 1);
        }
    };

    pushBits(0b0100, 4);
    pushBits(bytes.length, 8);
    bytes.forEach((b) => pushBits(b, 8));

    const maxDataBits = 19 * 8;
    const termLen = Math.min(4, maxDataBits - bits.length);
    pushBits(0, termLen);
    while (bits.length % 8 !== 0) {
        bits.push(0);
    }

    const dataCodewords = [];
    for (let i = 0; i < bits.length; i += 8) {
        let byte = 0;
        for (let b = 0; b < 8; b++) {
            byte = (byte << 1) | bits[i + b];
        }
        dataCodewords.push(byte);
    }

    const padBytes = [0xec, 0x11];
    let padIdx = 0;
    while (dataCodewords.length < 19) {
        dataCodewords.push(padBytes[padIdx % 2]);
        padIdx++;
    }

    const gfExp = new Uint8Array(512);
    const gfLog = new Uint8Array(256);
    let x = 1;
    for (let i = 0; i < 255; i++) {
        gfExp[i] = x;
        gfLog[x] = i;
        x <<= 1;
        if (x & 0x100) {
            x ^= 0x11d;
        }
    }
    for (let i = 255; i < 512; i++) {
        gfExp[i] = gfExp[i - 255];
    }

    const gen = [1, 127, 122, 154, 164, 11, 68, 117];
    const ec = new Uint8Array(7);
    for (let i = 0; i < dataCodewords.length; i++) {
        const factor = dataCodewords[i] ^ ec[0];
        for (let j = 0; j < 6; j++) {
            ec[j] = ec[j + 1];
        }
        ec[6] = 0;
        if (factor !== 0) {
            const logF = gfLog[factor];
            for (let j = 0; j < 7; j++) {
                ec[j] ^= gfExp[logF + gfLog[gen[j + 1]]];
            }
        }
    }

    const allCodewords = [...dataCodewords, ...Array.from(ec)];
    const streamBits = [];
    allCodewords.forEach((cw) => {
        for (let b = 7; b >= 0; b--) {
            streamBits.push((cw >> b) & 1);
        }
    });

    const size = 21;
    const matrix = Array.from({ length: size }, () => Array(size).fill(null));
    const reserved = Array.from({ length: size }, () => Array(size).fill(false));

    const placeFinder = (row, col) => {
        for (let r = -1; r <= 7; r++) {
            for (let c = -1; c <= 7; c++) {
                const rr = row + r;
                const cc = col + c;
                if (rr >= 0 && rr < size && cc >= 0 && cc < size) {
                    const isBorder = r === 0 || r === 6 || c === 0 || c === 6;
                    const isInner = r >= 2 && r <= 4 && c >= 2 && c <= 4;
                    const isSep = r === -1 || r === 7 || c === -1 || c === 7;
                    matrix[rr][cc] = !isSep && (isBorder || isInner) ? 1 : 0;
                    reserved[rr][cc] = true;
                }
            }
        }
    };

    placeFinder(0, 0);
    placeFinder(0, size - 7);
    placeFinder(size - 7, 0);

    for (let i = 8; i < size - 8; i++) {
        matrix[6][i] = i % 2 === 0 ? 1 : 0;
        reserved[6][i] = true;
        matrix[i][6] = i % 2 === 0 ? 1 : 0;
        reserved[i][6] = true;
    }

    for (let i = 0; i < 9; i++) {
        if (i < size) {
            reserved[8][i] = true;
            reserved[i][8] = true;
        }
    }
    for (let i = 0; i < 8; i++) {
        reserved[8][size - 1 - i] = true;
        reserved[size - 1 - i][8] = true;
    }
    matrix[size - 8][8] = 1;

    let bitIdx = 0;
    let upward = true;
    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right = 5;
        }
        for (let vert = 0; vert < size; vert++) {
            const r = upward ? size - 1 - vert : vert;
            for (let j = 0; j < 2; j++) {
                const c = right - j;
                if (!reserved[r][c]) {
                    const bit = bitIdx < streamBits.length ? streamBits[bitIdx++] : 0;
                    const mask = (r + c) % 2 === 0 ? 1 : 0;
                    matrix[r][c] = bit ^ mask;
                }
            }
        }
        upward = !upward;
    }

    const formatBits = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];
    const fCoords1 = [
        [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
        [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
    ];
    const fCoords2 = [
        [20, 8], [19, 8], [18, 8], [17, 8], [16, 8], [15, 8], [14, 8],
        [8, 13], [8, 14], [8, 15], [8, 16], [8, 17], [8, 18], [8, 19], [8, 20],
    ];
    for (let i = 0; i < 15; i++) {
        matrix[fCoords1[i][0]][fCoords1[i][1]] = formatBits[i];
        matrix[fCoords2[i][0]][fCoords2[i][1]] = formatBits[i];
    }

    const scale = 6;
    const quiet = 3;
    const total = (size + quiet * 2) * scale;
    let rects = '';
    for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
            if (matrix[r][c]) {
                rects += `<rect x="${(c + quiet) * scale}" y="${(r + quiet) * scale}" width="${scale}" height="${scale}" fill="#111111"/>`;
            }
        }
    }

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${total}" height="${total}" viewBox="0 0 ${total} ${total}">
        <rect width="${total}" height="${total}" fill="#ffffff"/>
        ${rects}
    </svg>`;

    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
}

export function playBeepSound() {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return;
        }
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'square';
        osc.frequency.setValueAtTime(1450, ctx.currentTime);
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.08);
    } catch {
        // Ignore audio context restriction
    }
}

/**
 *Daftar Kode NNS (National Numbering System) Standar QRIS Bank Indonesia (8 digit awal PAN / Tag 26-51 / EMVCo CPM).
 */
export const INDONESIAN_QR_NNS_MAP = {
    93600002: 'QR BANK BRI (BRIMO)',
    93600008: 'QR BANK MANDIRI (LIVIN)',
    93600009: 'QR BANK BNI',
    93600011: 'QR BANK DANAMON',
    93600013: 'QR BANK PERMATA',
    93600014: 'QR BANK BCA',
    93600016: 'QR BANK MAYBANK',
    93600022: 'QR BANK CIMB NIAGA',
    93600110: 'QR BANK BJB',
    93600114: 'QR BANK JATIM',
    93600200: 'QR BANK BTN',
    93600451: 'QR BANK BSI (SYARIAH)',
    93600503: 'QR BANK NOBU',
    93600898: 'QR SHOPEEPAY',
    93600911: 'QR LINKAJA',
    93600912: 'QR OVO',
    93600914: 'QR GOPAY',
    93600915: 'QR DANA',
    93600918: 'QR SHOPEEPAY',
};

/**
 * Mendeteksi apakah kode yang di-scan kasir adalah QR Code / Barcode Pembayaran (contoh: QR BRI / BRImo, BCA, Mandiri, DANA, GoPay, ShopeePay, OVO).
 * Mengembalikan string nama metode pembayaran (misal "QR BANK BRI (BRIMO)") atau null jika bukan kode pembayaran.
 */
export function detectPaymentQrProvider(rawCode) {
    const raw = String(rawCode || '').trim();
    if (raw.length < 6) {
        return null;
    }

    const upper = raw.toUpperCase();
    let inspected = upper;

    // Jika Base64 EMVCo QRIS CPM (diawali hQVDUFY / hQVD), decode Base64 untuk membaca kode NNS Bank di dalamnya
    if (upper.startsWith('HQVD') || /^[A-Za-z0-9+/=]{16,}$/.test(raw)) {
        try {
            const bin = atob(raw);
            let hex = '';
            for (let i = 0; i < bin.length; i++) {
                hex += bin.charCodeAt(i).toString(16).padStart(2, '0');
            }
            inspected += ' ' + bin.toUpperCase() + ' ' + hex.toUpperCase();
        } catch {
            // Ignore base64 decode error
        }
    }

    for (const [nns, bankName] of Object.entries(INDONESIAN_QR_NNS_MAP)) {
        if (inspected.includes(nns)) {
            return bankName;
        }
    }

    if (
        inspected.includes('ID.CO.BRI') ||
        inspected.includes('BRIMO') ||
        upper.startsWith('BRI-') ||
        upper.startsWith('QRBRI') ||
        /^002\d{6,}$/.test(upper)
    ) {
        return 'QR BANK BRI (BRIMO)';
    }

    if (
        inspected.includes('BCA') ||
        inspected.includes('MYBCA') ||
        upper.startsWith('BCA-') ||
        /^(014|880)\d{5,}$/.test(upper)
    ) {
        return 'QR BANK BCA';
    }

    if (
        inspected.includes('MANDIRI') ||
        inspected.includes('LIVIN') ||
        upper.startsWith('MANDIRI-') ||
        /^008\d{6,}$/.test(upper)
    ) {
        return 'QR BANK MANDIRI (LIVIN)';
    }

    if (inspected.includes('BNI') || upper.startsWith('BNI-') || /^009\d{6,}$/.test(upper)) {
        return 'QR BANK BNI';
    }

    if (inspected.includes('BSI') || upper.startsWith('BSI-') || /^451\d{6,}$/.test(upper)) {
        return 'QR BANK BSI (SYARIAH)';
    }

    if (inspected.includes('GOPAY') || upper.startsWith('GP-') || /^2810\d{4,}$/.test(upper)) {
        return 'QR GOPAY';
    }

    if (inspected.includes('DANA') || upper.startsWith('DN-') || /^2811\d{4,}$/.test(upper)) {
        return 'QR DANA';
    }

    if (inspected.includes('OVO') || upper.startsWith('OV-') || /^2812\d{4,}$/.test(upper)) {
        return 'QR OVO';
    }

    if (
        inspected.includes('SHOPEE') ||
        inspected.includes('SPAY') ||
        inspected.includes('AIRPAY') ||
        /^2813\d{4,}$/.test(upper)
    ) {
        return 'QR SHOPEEPAY';
    }

    if (upper.startsWith('HQVDUFY') || upper.startsWith('000201') || upper.startsWith('QRIS')) {
        return 'QRIS NASIONAL (CPM)';
    }

    return null;
}

const loadPrintSettings = () => {
    const defaults = {
        paperSize: '80mm',
        contentWidthMm: 68,
        offsetLeftMm: 3,
        offsetRightMm: 3,
        offsetTopMm: -1,
        escPosCols: 38,
        escPosLeftSpace: 0,
    };

    if (typeof window === 'undefined') {
        return defaults;
    }

    try {
        const saved = JSON.parse(localStorage.getItem('toko_print_settings') || '{}');
        return { ...defaults, ...saved };
    } catch {
        return defaults;
    }
};

export const printSettingsState = reactive(loadPrintSettings());

if (typeof window !== 'undefined') {
    watch(
        () => ({ ...printSettingsState }),
        (val) => {
            localStorage.setItem('toko_print_settings', JSON.stringify(val));
        },
        { deep: true },
    );
}

export function resetPrintSettings(paperSize = '80mm') {
    if (paperSize === '58mm') {
        printSettingsState.paperSize = '58mm';
        printSettingsState.contentWidthMm = 48;
        printSettingsState.offsetLeftMm = 2;
        printSettingsState.offsetRightMm = 2;
        printSettingsState.offsetTopMm = -1;
        printSettingsState.escPosCols = 30;
        printSettingsState.escPosLeftSpace = 0;
        return;
    }

    printSettingsState.paperSize = '80mm';
    printSettingsState.contentWidthMm = 68;
    printSettingsState.offsetLeftMm = 3;
    printSettingsState.offsetRightMm = 3;
    printSettingsState.offsetTopMm = -1;
    printSettingsState.escPosCols = 38;
    printSettingsState.escPosLeftSpace = 0;
}

/**
 * Mengirim perintah cetak struk RAW ESC/POS langsung ke printer thermal (misal: EPSON TM-T82X Receipt)
 * melalui backend Windows Print Spooler (1-Klik tanpa popup dialog browser + Auto-Cut kertas pas).
 */
export async function printDirectToEpson(idTransaksi, options = {}) {
    if (!idTransaksi) {
        return { ok: false, message: 'ID Transaksi tidak ditemukan.' };
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/api/transaksi/${idTransaksi}/print-direct`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                tunai: Number(options.tunai) || 0,
                kembalian: Number(options.kembalian) || 0,
                paper_size: options.paperSize || printSettingsState.paperSize || '80mm',
                cols: Number(options.cols ?? printSettingsState.escPosCols) || 38,
                left_space: Number(options.leftSpace ?? printSettingsState.escPosLeftSpace) || 0,
            }),
        });

        const payload = await response.json();
        return {
            ok: response.ok && Boolean(payload?.ok),
            message: payload?.message || (response.ok ? 'Struk berhasil dicetak.' : 'Gagal mencetak langsung.'),
            printer: payload?.printer || 'EPSON TM-T82X Receipt',
        };
    } catch (error) {
        return {
            ok: false,
            message: error?.message || 'Gagal menghubungi server cetak langsung.',
        };
    }
}

/**
 * Mencetak elemen struk/label via browser dengan ukuran kertas pas mengikuti kalibrasi posisi & warna hitam pekat.
 */
export function printThermalElement(elementId = 'printable-receipt', paperWidth = '80mm') {
    if (typeof document === 'undefined') {
        return;
    }

    const source = document.getElementById(elementId);
    if (!source) {
        window.print();
        return;
    }

    const oldIframe = document.getElementById('thermal-print-iframe');
    if (oldIframe) {
        oldIframe.remove();
    }

    const isMini = paperWidth === '58mm';
    const pageWidthMm = isMini ? '56mm' : '78mm';
    const contentWidthMm = isMini
        ? `${Math.min(54, Math.max(40, Number(printSettingsState.contentWidthMm) || 48))}mm`
        : `${Math.min(76, Math.max(58, Number(printSettingsState.contentWidthMm) || 68))}mm`;
    const padLeft = `${Math.max(0, Number(printSettingsState.offsetLeftMm) || 0)}mm`;
    const padRight = `${Math.max(0, Number(printSettingsState.offsetRightMm) || 0)}mm`;
    const marginTop = `${Number(printSettingsState.offsetTopMm) || 0}mm`;

    // Hitung tinggi konten struk secara akurat (1px ≈ 0.264583mm) tanpa sisa kertas panjang
    const contentHeightPx = Math.max(source.scrollHeight || 340, 220);
    const dynamicHeightMm = Math.ceil(contentHeightPx * 0.264583) + 3;

    const iframe = document.createElement('iframe');
    iframe.id = 'thermal-print-iframe';
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) {
        window.print();
        return;
    }

    doc.open();
    doc.write(`<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Cetak Struk Thermal</title>
    <style>
        @page {
            size: ${pageWidthMm} ${dynamicHeightMm}mm !important;
            margin: 0mm !important;
        }
        * {
            box-sizing: border-box;
            color: #000000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            width: ${pageWidthMm} !important;
            max-width: ${pageWidthMm} !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-family: Consolas, 'JetBrains Mono', 'Courier New', monospace !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            -webkit-font-smoothing: none !important;
            text-rendering: geometricPrecision !important;
        }
        #printable-receipt {
            width: ${contentWidthMm} !important;
            max-width: ${contentWidthMm} !important;
            margin: ${marginTop} 0 0 0 !important;
            padding: 0mm ${padRight} 1.5mm ${padLeft} !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        .font-bold { font-weight: 700 !important; }
        .font-extrabold { font-weight: 900 !important; }
        .uppercase { text-transform: uppercase !important; }
        .tabular-nums { font-variant-numeric: tabular-nums !important; }
        .whitespace-nowrap { white-space: nowrap !important; }
        .shrink-0 { flex-shrink: 0 !important; }
        .flex { display: flex !important; }
        .flex-col { flex-direction: column !important; }
        .items-center { align-items: center !important; }
        .items-start { align-items: flex-start !important; }
        .justify-between { justify-content: space-between !important; }
        .gap-2 { gap: 4px !important; }
        .space-y-1 > * + * { margin-top: 2px !important; }
        .space-y-1\\.5 > * + * { margin-top: 3px !important; }
        .space-y-2 > * + * { margin-top: 4px !important; }
        .pb-2, .pb-3 { padding-bottom: 5px !important; }
        .pt-1 { padding-top: 3px !important; }
        .mb-1, .mb-1\\.5 { margin-bottom: 3px !important; }
        .mb-2, .mb-3 { margin-bottom: 5px !important; }
        .mt-0\\.5 { margin-top: 2px !important; }
        .mt-1 { margin-top: 3px !important; }
        .p-1 { padding: 2px !important; }
        .mx-auto { margin-left: auto !important; margin-right: auto !important; }
        .border-b-2 { border-bottom: 1.5px dashed #000000 !important; }
        .border-t { border-top: 1.5px solid #000000 !important; }
        .border { border: 1.5px solid #000000 !important; }
        .text-base { font-size: 13px !important; font-weight: 900 !important; }
        .text-sm { font-size: 12px !important; font-weight: 900 !important; }
        .text-xs { font-size: 10.5px !important; }
        .text-\\[11px\\] { font-size: 10px !important; }
        .text-\\[10px\\] { font-size: 9px !important; }
        .w-20, .w-24 { width: 76px !important; }
        .h-20, .h-24 { height: 76px !important; }
        .w-36 { width: 112px !important; }
        .h-36 { height: 112px !important; }
        img, svg {
            display: block !important;
            max-width: 100% !important;
            visibility: visible !important;
            opacity: 1 !important;
            filter: contrast(150%) !important;
        }
        .no-print { display: none !important; }
    </style>
</head>
<body>
    <div id="printable-receipt">${source.innerHTML}</div>
</body>
</html>`);
    doc.close();

    const triggerPrint = () => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
    };

    const images = Array.from(doc.querySelectorAll('img'));
    if (images.length === 0) {
        setTimeout(triggerPrint, 150);
        return;
    }

    let loadedCount = 0;
    const checkAllLoaded = () => {
        loadedCount++;
        if (loadedCount >= images.length) {
            setTimeout(triggerPrint, 100);
        }
    };

    images.forEach((img) => {
        if (img.complete) {
            checkAllLoaded();
        } else {
            img.addEventListener('load', checkAllLoaded, { once: true });
            img.addEventListener('error', checkAllLoaded, { once: true });
        }
    });
}
