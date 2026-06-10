<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    metodeLogin: { type: Array, default: () => [{ key: 'nis_pin' }] },
});

const form = useForm({ nis: '', password: '' });

const aktif = (k) => props.metodeLogin.some((m) => m.key === k);

// --- Scan QR kartu santri (payload = NIS) via BarcodeDetector (Android Chrome) ---
const scanning = ref(false);
const scanPesan = ref('');
const videoEl = ref(null);
let stream = null;
let rafId = null;

async function mulaiScan() {
    scanPesan.value = '';
    if (!('BarcodeDetector' in window)) {
        scanPesan.value = 'Perangkat ini belum mendukung pindai QR. Ketik NIS manual.';
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        scanning.value = true;
        await nextFrameVideo();
        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        const tick = async () => {
            if (!scanning.value || !videoEl.value) return;
            try {
                const codes = await detector.detect(videoEl.value);
                if (codes.length) {
                    const teks = String(codes[0].rawValue || '').trim();
                    const nis = (teks.match(/\d{3,}/) || [teks])[0];
                    form.nis = nis;
                    hentikanScan();
                    scanPesan.value = 'Kartu terbaca — masukkan PIN.';
                    return;
                }
            } catch { /* lanjut */ }
            rafId = requestAnimationFrame(tick);
        };
        rafId = requestAnimationFrame(tick);
    } catch {
        scanPesan.value = 'Tidak bisa membuka kamera. Ketik NIS manual.';
        scanning.value = false;
    }
}

function nextFrameVideo() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            if (videoEl.value && stream) {
                videoEl.value.srcObject = stream;
                videoEl.value.play().catch(() => {});
            }
            resolve();
        });
    });
}

function hentikanScan() {
    scanning.value = false;
    if (rafId) cancelAnimationFrame(rafId);
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
}

onBeforeUnmount(hentikanScan);

function submit() {
    form.post('/app/login');
}

const metodePerangkat = computed(() => props.metodeLogin.filter((m) => m.siap === false));
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-b from-emerald-900 to-emerald-700 px-4 py-8">
        <div class="w-full max-w-sm rounded-3xl bg-white p-7 shadow-xl">
            <div class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-2xl text-white">🎓</div>
                <h1 class="mt-4 text-xl font-bold text-stone-900">App Santri</h1>
                <p class="mt-1 text-sm text-stone-500">Masuk dengan NIS dan PIN kamu.</p>
            </div>

            <!-- Scan QR (bila diaktifkan pesantren) -->
            <div v-if="aktif('qr')" class="mt-5">
                <button
                    v-if="!scanning"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 py-3 text-sm font-semibold text-emerald-700"
                    @click="mulaiScan"
                >
                    📷 Scan Kartu Santri (QR)
                </button>
                <div v-else class="overflow-hidden rounded-xl border border-emerald-300">
                    <video ref="videoEl" class="h-44 w-full bg-black object-cover" playsinline muted></video>
                    <button type="button" class="w-full bg-stone-100 py-2 text-sm font-medium text-stone-600" @click="hentikanScan">
                        Tutup kamera
                    </button>
                </div>
                <p v-if="scanPesan" class="mt-1 text-center text-xs text-emerald-700">{{ scanPesan }}</p>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-semibold text-stone-600">NIS</label>
                    <input v-model="form.nis" type="text" inputmode="numeric" autocomplete="username"
                        class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-sm" placeholder="Nomor Induk Santri">
                </div>
                <div>
                    <label class="text-xs font-semibold text-stone-600">PIN</label>
                    <input v-model="form.password" type="password" inputmode="numeric" autocomplete="current-password"
                        class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-center text-lg tracking-[0.3em]" placeholder="••••••">
                </div>
                <p v-if="form.errors.nis" class="text-sm text-red-600">{{ form.errors.nis }}</p>
                <button type="submit" :disabled="form.processing"
                    class="w-full rounded-full bg-emerald-600 py-3 text-sm font-semibold text-white disabled:opacity-60">
                    Masuk
                </button>
                <p class="text-center text-xs text-stone-400">Lupa PIN? Minta orang tua atur ulang di aplikasi wali, atau hubungi admin.</p>
            </form>

            <!-- Metode butuh perangkat (sidik jari / NFC) — informatif -->
            <div v-if="metodePerangkat.length" class="mt-5 space-y-2 border-t border-stone-100 pt-4">
                <p class="text-center text-xs font-semibold text-stone-400">Metode lain (di perangkat pondok)</p>
                <div v-for="m in metodePerangkat" :key="m.key"
                    class="flex items-center gap-2 rounded-xl bg-stone-50 px-3 py-2 text-xs text-stone-500">
                    <span>{{ m.key === 'fingerprint' ? '🔒' : '💳' }}</span>
                    <span>{{ m.label }} — perlu perangkat pendukung</span>
                </div>
            </div>
        </div>
    </div>
</template>
