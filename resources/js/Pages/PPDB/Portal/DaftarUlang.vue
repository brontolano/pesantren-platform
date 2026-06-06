<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SpmbLayout from '../../../Layouts/SpmbLayout.vue';

const props = defineProps({
    nomorPendaftaran: { type: String, default: '' },
    nama: { type: String, default: '' },
    asalSekolah: { type: String, default: '' },
    noHpWali: { type: String, default: '' },
});

const form = useForm({});

function konfirmasi() {
    form.post('/spmb/daftar-ulang', { preserveScroll: true });
}
</script>

<template>
    <Head title="Daftar Ulang" />

    <SpmbLayout>
        <div class="px-4 py-5 space-y-4">
            <div class="rounded-2xl bg-emerald-50 px-4 py-4 text-center text-emerald-800">
                <p class="text-base font-semibold">Selamat, Anda DITERIMA! 🎉</p>
                <p class="mt-1 text-sm">Lengkapi daftar ulang untuk menjadi santri aktif.</p>
            </div>

            <div v-if="form.errors.daftar_ulang" class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ form.errors.daftar_ulang }}
            </div>

            <div class="rounded-xl border border-black/5 bg-white px-4 py-3 shadow-sm">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Data Anda</p>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">Nama</dt><dd class="font-medium text-stone-900">{{ nama }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-500">No. Pendaftaran</dt><dd class="font-medium text-stone-900">{{ nomorPendaftaran }}</dd></div>
                    <div v-if="asalSekolah" class="flex justify-between gap-3"><dt class="text-stone-500">Asal Sekolah</dt><dd class="text-stone-800">{{ asalSekolah }}</dd></div>
                    <div v-if="noHpWali" class="flex justify-between gap-3"><dt class="text-stone-500">No. WA Wali</dt><dd class="text-stone-800">{{ noHpWali }}</dd></div>
                </dl>
            </div>

            <p class="text-xs text-stone-500">
                Dengan menekan tombol di bawah, data Anda dikonversi menjadi akun santri aktif.
                Pastikan data sudah benar (perbarui di Dashboard bila perlu).
            </p>

            <button
                type="button"
                class="w-full rounded-xl bg-[var(--spmb-primary)] px-4 py-3 text-sm font-semibold text-white transition disabled:opacity-50"
                :disabled="form.processing"
                @click="konfirmasi"
            >
                {{ form.processing ? 'Memproses…' : 'Konfirmasi Daftar Ulang' }}
            </button>
        </div>
    </SpmbLayout>
</template>
